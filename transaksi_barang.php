<?php
session_start();
include 'koneksi.php'; 

define('JENIS_TRANSAKSI_PENJUALAN', 'penjualan');

$cart = $_SESSION['cart'] ?? []; 
$error_message = null; 
$cart_display_items = [];
$total_untuk_display = 0;
$namaAkun = "Customer"; // Default account name for this page

// Ambil error message dari session jika ada
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Hapus dari session setelah diambil
}

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

    if (empty($nama) || empty($nohp) || !$email) {
        $error_message = "Nama, No HP, dan Email wajib diisi dengan format yang benar.";
    } elseif (empty($cart)) {
        $error_message = "Keranjang belanja kosong. Silakan tambahkan barang terlebih dahulu.";
    } else {
        // --- Debugging: Log cart contents ---
        log_error("Cart contents (POST): " . json_encode($cart));
        $item_ids = array_keys($cart);
        log_error("Item IDs from cart (POST): " . json_encode($item_ids));
        // --- End Debugging ---

        // Mulai transaksi database
        // Pastikan koneksi aktif sebelum memulai transaksi
        if ($koneksi && !$koneksi->connect_error) { // Lanjutkan hanya jika koneksi berhasil
            $koneksi->begin_transaction();

            try {
                // 1. Hitung total belanja & siapkan detail item (ambil harga dan stok terbaru dari DB)
                $items_to_process = [];
                $total_belanja = 0;

                $placeholders = implode(',', array_fill(0, count($cart), '?'));
                $types = str_repeat('i', count($cart)); // Mengubah 's' menjadi 'i' untuk id_barang
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
                    // --- Debugging: Log found item ---
                    log_error("Found item in DB (POST): " . json_encode($barang_db));
                    // --- End Debugging ---
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
                $jenis_transaksi = JENIS_TRANSAKSI_PENJUALAN; 

                $sql_insert_transaksi = "INSERT INTO transaksi (id_customer, jenis, tanggal, total, id_service) 
                                         VALUES (?, ?, ?, ?, NULL)"; // id_service NULL untuk penjualan
                $stmt_trans = $koneksi->prepare($sql_insert_transaksi);
                if (!$stmt_trans) {
                    throw new mysqli_sql_exception("Prepare statement insert transaksi gagal: " . $koneksi->error);
                }
                $stmt_trans->bind_param("issd", $id_customer, $jenis_transaksi, $tanggal_transaksi, $total_belanja);
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
                $error_message = "Terjadi masalah database saat memproses pesanan Anda. Mohon coba lagi. (Error Code: " . $e->getCode() . ")";
            } catch (Exception $e) { 
                $koneksi->rollback(); 
                log_error("Application Error during checkout: " . $e->getMessage());
                $error_message = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage(); 
            } finally {
                // Tidak menutup koneksi di sini agar bisa digunakan lagi di bagian bawah script jika perlu.
                // Koneksi akan ditutup di akhir script.
            }
        }
    }
}

// --- Ambil data keranjang untuk tampilan HTML (diluar blok POST) ---
if (!empty($cart)) {
    // Ambil detail barang dari database untuk tampilan
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $types = str_repeat('i', count($cart)); // Mengubah 's' menjadi 'i' untuk id_barang
    $item_ids = array_keys($cart);

    // --- Debugging: Log cart contents for display ---
    log_error("Cart contents (Display): " . json_encode($cart));
    log_error("Item IDs from cart (Display): " . json_encode($item_ids));
    // --- End Debugging ---

    // **Perbaikan di sini:** Buat koneksi baru untuk tampilan jika belum ada atau sudah ditutup
    if ($koneksi && !$koneksi->connect_error) { // Lanjutkan hanya jika koneksi berhasil
        $stmt_display = $koneksi->prepare("SELECT id_barang, nama_barang, harga FROM stok WHERE id_barang IN ($placeholders)");
        if ($stmt_display) {
            $stmt_display->bind_param($types, ...$item_ids);
            $stmt_display->execute();
            $result_display = $stmt_display->get_result();

            while ($row = $result_display->fetch_assoc()) {
                // --- Debugging: Log found item for display ---
                log_error("Found item for display in DB: " . json_encode($row));
                // --- End Debugging ---
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
            $error_message = "Terjadi kesalahan saat memuat detail keranjang.";
        }
    }
}

// **Penting:** Tutup koneksi di akhir script setelah semua operasi database selesai.
if (isset($koneksi) && !$koneksi->connect_error) {
    $koneksi->close(); 
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Pembayaran - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { display: flex; flex-direction: column; font-family: sans-serif; min-height: 100vh; background-color: #f8f9fa; }
        .navbar { background-color: #ffffff; padding: 15px 20px; border-bottom: 1px solid #dee2e6; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .navbar .logo-img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; border: 2px solid #0d6efd; }
        .navbar .nav-link { padding: 10px 15px; color: #495057; font-weight: 500; transition: background-color 0.2s, color 0.2s; border-radius: 0.25rem; display: flex; align-items: center; }
        .navbar .nav-link.active, .navbar .nav-link:hover { background-color: #e9ecef; color: #007bff; }
        .navbar .nav-link i { margin-right: 8px; }
        .main-content { flex: 1; padding: 20px; display: flex; flex-direction: column; }
        .main-header { display: flex; justify-content: center; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .total-tagihan-display { font-size: 1.25em; font-weight: bold; color: #dc3545; }
        .label-summary { min-width: 140px; display: inline-block; font-weight: 500; }
        @media (max-width: 768px) {
            .main-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .main-header h2 { width: 100%; text-align: center; }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-light">
    </nav>

<div class="main-content">
    <div class="main-header">
        <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Instruksi Pembayaran Barang</h2>
    </div>

    <div class="flex-grow-1 p-3">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif (empty($cart_display_items)): ?>
            <div class="alert alert-warning text-center" role="alert">
                <i class="fas fa-shopping-cart"></i> Keranjang belanja Anda kosong. <a href="index.php" class="alert-link">Mulai belanja sekarang!</a>
            </div>
        <?php else: ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <h4 class="my-0 fw-normal">Ringkasan Pesanan</h4>
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
                                    <td class="text-end"><strong class="total-tagihan-display">Rp <?= number_format($total_untuk_display, 0, ',', '.') ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="my-0"><i class="bi bi-credit-card"></i> Metode Pembayaran</h4>
                </div>
                <div class="card-body p-lg-4">
                    <p class="text-center lead mb-4">Silakan selesaikan pembayaran Anda melalui salah satu metode berikut:</p>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-shop"></i> 1. Bayar Langsung di Toko</h3>
                                <p>Anda dapat melakukan pembayaran secara tunai atau metode lain yang tersedia di toko kami dan tunjukkan halaman ini.</p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-bank"></i> 2. Transfer Bank</h3>
                                <p>Lakukan transfer ke salah satu rekening resmi berikut:</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-warning">
                            <h4 class="alert-heading mb-0"><i class="bi bi-check-circle-fill"></i> Penting: Konfirmasi Pembayaran Anda di Sini</h4>
                        </div>
                        <div class="card-body">
                            <p>Setelah melakukan pembayaran, mohon segera lakukan konfirmasi dengan mengisi formulir di bawah ini agar pesanan Anda dapat segera kami proses.</p>
                            
                            <form action="proses_konfirmasi_barang.php" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="nama" class="form-label">Nama Lengkap:</label>
                                    <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" placeholder="Contoh: Budi Santoso" required>
                                </div>

                                <div class="mb-3">
                                    <label for="nohp" class="form-label">Nomor HP:</label>
                                    <input type="tel" class="form-control" id="nohp" name="nohp" value="<?= htmlspecialchars($_POST['nohp'] ?? '') ?>" 
                                        pattern="[0-9]{12,13}" 
                                        maxlength="13" 
                                        minlength="12"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        placeholder="Contoh: 081234567890" required>
                                    <div class="form-text">Masukkan 12-13 digit nomor telepon (hanya angka)</div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email:</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Contoh: budi@email.com" required>
                                </div>

                                <div class="mb-3">
                                    <label for="metode_transfer" class="form-label">Metode Pembayaran:</label>
                                    <select class="form-select" id="metode_transfer" name="metode_transfer" required>
                                        <option value="" disabled selected>-- Pilih Metode Pembayaran --</option>
                                        <option value="cash">Cash di Toko</option>
                                        <option value="transfer">Transfer Bank</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="nama_pengirim" class="form-label">Nama Pemilik Rekening Pengirim:</label>
                                    <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim" placeholder="Contoh: Budi Santoso" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bukti_pembayaran" class="form-label">Unggah Bukti Transfer:</label>
                                    <input class="form-control" type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/jpeg, image/png, application/pdf" required>
                                    <div class="form-text">Format file yang diizinkan: JPG, PNG, atau PDF. Ukuran maks: 5MB.</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="submit_konfirmasi" class="btn btn-success btn-lg">
                                        <i class="bi bi-send-check"></i> Kirim Konfirmasi Pembayaran
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 py-4 border-top">
                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2"><i class="bi bi-house"></i> Kembali ke Beranda</a>
                <a href="barang.php" class="btn btn-outline-primary btn-lg ms-2"><i class="bi bi-cart"></i> Kembali ke Keranjang</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="mt-auto p-4 border-top text-center text-muted small">
        <p>&copy; <?php echo date("Y"); ?> Thar'z Computer. All rights reserved.</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Hanya jalankan script jika elemen nohp ada
    const nohpElement = document.getElementById('nohp');
    if (nohpElement) {
        nohpElement.addEventListener('input', function(e) {
            // Hapus karakter non-angka
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Batasi panjang maksimal 13 digit
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
    }
</script>
</body>

</html>