<?php
session_start();

// Asumsi 'koneksi.php' berisi:
// $koneksi = new mysqli("localhost", "root", "", "tharz_computer");
// if ($koneksi->connect_error) {
//     die("Koneksi database gagal: " . $koneksi->connect_error);
// }
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Aktifkan pelaporan error MySQLi
include 'koneksi.php'; 

// --- Konfigurasi dan Inisialisasi ---
// Definisi status transaksi yang valid (sesuaikan dengan ENUM di DB Anda)
define('STATUS_MENUNGGU_PEMBAYARAN', 'menunggu pembayaran');
define('JENIS_TRANSAKSI_PENJUALAN', 'penjualan');

$cart = $_SESSION['cart'] ?? []; 
$error_checkout = null; 
$id_transaksi_baru = null; 
$total_belanja_final = 0; // Untuk ditampilkan di ringkasan keranjang

// Fungsi helper untuk mencatat error ke log file
function log_error($message) {
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, 'error_log.txt');
}

// --- Proses Checkout (jika ada POST request dan keranjang tidak kosong) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input dasar
    $nama = trim($_POST['nama'] ?? '');
    $nohp = trim($_POST['nohp'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL); // Validasi format email

    if (empty($nama) || empty($nohp) || !$email) { // Gunakan !empty untuk $nama dan $nohp, dan !filter_var untuk $email
        $error_checkout = "Nama, No HP, dan Email wajib diisi dengan format yang benar.";
    } elseif (empty($cart)) {
        $error_checkout = "Keranjang belanja kosong. Silakan tambahkan barang terlebih dahulu.";
    } else {
        // Mulai transaksi database
        $koneksi->begin_transaction();

        try {
            // 1. Hitung total belanja & siapkan detail item (ambil harga dan stok terbaru dari DB)
            $items_to_process = [];
            $total_belanja = 0;

            $placeholders = implode(',', array_fill(0, count($cart), '?'));
            $types = str_repeat('s', count($cart)); // 's' karena id_barang mungkin string/varchar di DB
            $item_ids = array_keys($cart);

            $sql_harga = "SELECT id_barang, nama_barang, harga, stok FROM stok WHERE id_barang IN ($placeholders) FOR UPDATE"; // FOR UPDATE untuk mengunci baris
            $stmt_harga = $koneksi->prepare($sql_harga);
            if (!$stmt_harga) {
                throw new mysqli_sql_exception("Prepare statement harga gagal: " . $koneksi->error);
            }

            $stmt_harga->bind_param($types, ...$item_ids);
            $stmt_harga->execute();
            $result_harga = $stmt_harga->get_result();

            // Cek jika ada item di keranjang yang tidak ditemukan di DB
            if ($result_harga->num_rows !== count($cart)) {
                throw new Exception("Beberapa item di keranjang tidak ditemukan di database.");
            }

            while ($barang_db = $result_harga->fetch_assoc()) {
                $id_b = $barang_db['id_barang'];
                $qty_pesan = $cart[$id_b];

                if ($qty_pesan <= 0) { // Pastikan kuantitas positif
                    throw new Exception("Kuantitas untuk barang \"" . htmlspecialchars($barang_db['nama_barang']) . "\" tidak valid.");
                }
                if ($qty_pesan > $barang_db['stok']) {
                    throw new Exception("Stok untuk barang \"" . htmlspecialchars($barang_db['nama_barang']) . "\" tidak mencukupi (tersisa: " . $barang_db['stok'] . ", dipesan: " . $qty_pesan . ").");
                }

                $subtotal_item = $qty_pesan * $barang_db['harga'];
                $total_belanja += $subtotal_item;
                $items_to_process[] = [
                    'id_barang' => $id_b,
                    'jumlah' => $qty_pesan,
                    'harga_satuan' => $barang_db['harga'],
                    'subtotal' => $subtotal_item
                ];
            }
            $stmt_harga->close();
            
            // Simpan total belanja untuk ditampilkan di halaman setelah sukses
            $total_belanja_final = $total_belanja;

            // 2. Cek/Buat Customer
            $id_customer = null;
            $sql_cek_customer = "SELECT id_customer FROM customer WHERE nama_customer = ? AND no_telepon = ? AND email = ?";
            $stmt_cek = $koneksi->prepare($sql_cek_customer);
            if (!$stmt_cek) {
                throw new mysqli_sql_exception("Prepare statement cek customer gagal: " . $koneksi->error);
            }
            $stmt_cek->bind_param("sss", $nama, $nohp, $email);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();

            if ($result_cek->num_rows > 0) {
                $row_cust = $result_cek->fetch_assoc();
                $id_customer = $row_cust['id_customer'];
            } else {
                $sql_insert_customer = "INSERT INTO customer (nama_customer, no_telepon, email) VALUES (?, ?, ?)";
                $stmt_insert_cust = $koneksi->prepare($sql_insert_customer);
                if (!$stmt_insert_cust) {
                    throw new mysqli_sql_exception("Prepare statement insert customer gagal: " . $koneksi->error);
                }
                $stmt_insert_cust->bind_param("sss", $nama, $nohp, $email);
                if (!$stmt_insert_cust->execute()) {
                    throw new mysqli_sql_exception("Insert customer baru gagal: " . $stmt_insert_cust->error);
                }
                $id_customer = $koneksi->insert_id;
                $stmt_insert_cust->close();
            }
            $stmt_cek->close();
            
            if (!$id_customer) {
                throw new Exception("Gagal mendapatkan ID Customer.");
            }

            // 3. Buat Transaksi
            $tanggal_transaksi = date('Y-m-d H:i:s');
            $status_awal = STATUS_MENUNGGU_PEMBAYARAN; 
            $jenis_transaksi = JENIS_TRANSAKSI_PENJUALAN; 

            $sql_insert_transaksi = "INSERT INTO transaksi (id_customer, jenis, status, tanggal, total, id_service)  
                                     VALUES (?, ?, ?, ?, ?, NULL)"; // id_service NULL untuk penjualan
            $stmt_trans = $koneksi->prepare($sql_insert_transaksi);
            if (!$stmt_trans) {
                throw new mysqli_sql_exception("Prepare statement insert transaksi gagal: " . $koneksi->error);
            }
            $stmt_trans->bind_param("isssd", $id_customer, $jenis_transaksi, $status_awal, $tanggal_transaksi, $total_belanja);
            if (!$stmt_trans->execute()) {
                throw new mysqli_sql_exception("Insert transaksi gagal: " . $stmt_trans->error);
            }
            $id_transaksi_baru = $koneksi->insert_id;
            $stmt_trans->close();

            // 4. Buat Detail Transaksi & Kurangi Stok
            $sql_insert_detail = "INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, subtotal) VALUES (?, ?, ?, ?)"; 
            $stmt_detail = $koneksi->prepare($sql_insert_detail);
            if (!$stmt_detail) {
                throw new mysqli_sql_exception("Prepare statement insert detail_transaksi gagal: " . $koneksi->error);
            }

            $sql_update_stok = "UPDATE stok SET stok = stok - ? WHERE id_barang = ?"; 
            $stmt_stok = $koneksi->prepare($sql_update_stok);
            if (!$stmt_stok) {
                throw new mysqli_sql_exception("Prepare statement update stok gagal: " . $koneksi->error);
            }

            foreach ($items_to_process as $item) {
                // Insert detail transaksi
                $stmt_detail->bind_param("iiid", $id_transaksi_baru, $item['id_barang'], $item['jumlah'], $item['subtotal']);
                if (!$stmt_detail->execute()) {
                    throw new mysqli_sql_exception("Insert detail_transaksi untuk barang ID " . $item['id_barang'] . " gagal: " . $stmt_detail->error);
                }

                // Kurangi stok
                $stmt_stok->bind_param("ii", $item['jumlah'], $item['id_barang']);
                if (!$stmt_stok->execute()) {
                    throw new mysqli_sql_exception("Update stok untuk barang ID " . $item['id_barang'] . " gagal: " . $stmt_stok->error);
                }
            }
            $stmt_detail->close(); 
            $stmt_stok->close(); 

            // Jika semua berhasil, commit transaksi dan kosongkan keranjang
            $koneksi->commit(); 
            $_SESSION['cart'] = []; 

            // Redirect ke halaman sukses dengan membawa ID transaksi
            header("Location: pesanan_sukses.php?id_transaksi=" . $id_transaksi_baru . "&total=" . $total_belanja_final);
            exit; 

        } catch (mysqli_sql_exception $e) {
            $koneksi->rollback(); 
            log_error("MySQL Error during checkout: " . $e->getMessage() . " - SQLSTATE: " . $e->getSqlState());
            $error_checkout = "Terjadi masalah database saat memproses pesanan Anda. Mohon coba lagi. (Error Code: " . $e->getCode() . ")";
        } catch (Exception $e) { 
            $koneksi->rollback(); 
            log_error("Application Error during checkout: " . $e->getMessage());
            $error_checkout = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage(); 
        } finally {
            // Pastikan koneksi ditutup jika tidak di handle oleh try-catch
            if ($koneksi && !$koneksi->connect_error) { // Cek koneksi masih aktif
                $koneksi->close(); 
            }
        }
    }
}
// --- Ambil data keranjang untuk tampilan HTML (diluar blok POST) ---
$cart_display_items = [];
$total_untuk_display = 0;

if (!empty($cart)) {
    // Ambil detail barang dari database untuk tampilan
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $types = str_repeat('s', count($cart));
    $item_ids = array_keys($cart);

    // Buat koneksi baru untuk tampilan jika belum ada atau sudah ditutup dari proses POST sebelumnya
    if (!$koneksi || $koneksi->connect_error) {
        $koneksi = new mysqli("localhost", "root", "", "tharz_computer");
        if ($koneksi->connect_error) {
            log_error("Koneksi database gagal untuk tampilan keranjang: " . $koneksi->connect_error);
            // Handle error for display, maybe show an empty cart or a message
        }
    }

    if ($koneksi && !$koneksi->connect_error) {
        $stmt_display = $koneksi->prepare("SELECT id_barang, nama_barang, harga FROM stok WHERE id_barang IN ($placeholders)");
        if ($stmt_display) {
            $stmt_display->bind_param($types, ...$item_ids);
            $stmt_display->execute();
            $result_display = $stmt_display->get_result();

            while ($row = $result_display->fetch_assoc()) {
                $qty = $cart[$row['id_barang']];
                $subtotal = $row['harga'] * $qty;
                $total_untuk_display += $subtotal;
                $cart_display_items[] = [
                    'nama_barang' => $row['nama_barang'],
                    'harga' => $row['harga'],
                    'jumlah' => $qty,
                    'subtotal' => $subtotal
                ];
            }
            $stmt_display->close();
        } else {
            log_error("Gagal menyiapkan query untuk tampilan keranjang: " . $koneksi->error);
            $error_checkout = "Terjadi kesalahan saat memuat detail keranjang.";
        }
        $koneksi->close(); // Tutup koneksi setelah ambil data untuk tampilan
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Pesanan - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Konfirmasi Pesanan Anda</h2>

        <?php if (!empty($error_checkout)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_checkout); ?>
                <br>
                <a href="index.php" class="alert-link">Kembali ke Beranda</a> atau <a href="cart.php" class="alert-link">Cek Keranjang</a>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_display_items)): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-shopping-cart"></i> Keranjang belanja Anda kosong. <a href="index.php" class="alert-link">Mulai belanja sekarang!</a>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="my-0 font-weight-normal">Informasi Kontak</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" id="nama" class="form-control" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="nohp" class="form-label">Nomor HP</label>
                            <input type="tel" name="nohp" id="nohp" class="form-control" value="<?= htmlspecialchars($_POST['nohp'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h4 class="my-0 font-weight-normal">Detail Keranjang</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">Nama Barang</th>
                                        <th scope="col" class="text-end">Harga Satuan</th>
                                        <th scope="col" class="text-end">Jumlah</th>
                                        <th scope="col" class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_display_items as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                            <td class="text-end">Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                                            <td class="text-end"><?= $item['jumlah'] ?></td>
                                            <td class="text-end">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total Belanja</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($total_untuk_display, 0, ',', '.') ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-money-check-alt"></i> Selesaikan Pesanan
                    </button>
                    <a href="cart.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-arrow-left"></i> Kembali ke Keranjang
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>