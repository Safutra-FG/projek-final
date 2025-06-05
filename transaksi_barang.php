<?php
session_start();
$cart = $_SESSION['cart'] ?? [];
include 'koneksi.php';

// Pastikan kolom status sudah ada di tabel transaksi
// ENUM contoh: ('Menunggu Pembayaran', 'Pembayaran Diverifikasi', 'Diproses', 'Siap Diambil', 'Dikirim', 'Selesai', 'Dibatalkan')

$error_checkout = null;
$id_transaksi_baru = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cart)) {
    $nama = trim($_POST['nama'] ?? '');
    $nohp = trim($_POST['nohp'] ?? '');
    $email = trim($_POST['email'] ?? ''); // Ambil email
    $total_belanja = 0;

    // Validasi input dasar
    if (empty($nama) || empty($nohp) || empty($email)) {
        $error_checkout = "Nama, No HP, dan Email wajib diisi.";
    } else {
        // Mulai transaksi database
        $koneksi->begin_transaction();

        try {
            // 1. Hitung total belanja & siapkan detail item (gunakan prepared statement untuk ambil harga terbaru)
            $items_to_process = [];
            if (!empty($cart)) {
                $placeholders = implode(',', array_fill(0, count($cart), '?'));
                $types = str_repeat('s', count($cart));
                $item_ids = array_keys($cart);

                $sql_harga = "SELECT id_barang, nama_barang, harga, stok FROM stok WHERE id_barang IN ($placeholders)";
                $stmt_harga = $koneksi->prepare($sql_harga);
                if (!$stmt_harga) throw new Exception("Prepare statement harga gagal: " . $koneksi->error);

                $stmt_harga->bind_param($types, ...$item_ids);
                $stmt_harga->execute();
                $result_harga = $stmt_harga->get_result();

                while ($barang_db = $result_harga->fetch_assoc()) {
                    $id_b = $barang_db['id_barang'];
                    $qty_pesan = $cart[$id_b];

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
                if (empty($items_to_process)) throw new Exception("Keranjang belanja menjadi kosong atau item tidak valid.");
            } else {
                throw new Exception("Keranjang belanja kosong.");
            }


            // 2. Cek/Buat Customer (gunakan prepared statement)
            // Sesuaikan dengan logikamu: nama, nohp, DAN email
            $sql_cek_customer = "SELECT id_customer FROM customer WHERE nama_customer = ? AND no_telepon = ? AND email = ?";
            $stmt_cek = $koneksi->prepare($sql_cek_customer);
            if (!$stmt_cek) throw new Exception("Prepare statement cek customer gagal: " . $koneksi->error);

            $stmt_cek->bind_param("sss", $nama, $nohp, $email);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            $id_customer = null;

            if ($result_cek->num_rows > 0) {
                $row_cust = $result_cek->fetch_assoc();
                $id_customer = $row_cust['id_customer'];
            } else {
                $sql_insert_customer = "INSERT INTO customer (nama_customer, no_telepon, email) VALUES (?, ?, ?)";
                $stmt_insert_cust = $koneksi->prepare($sql_insert_customer);
                if (!$stmt_insert_cust) throw new Exception("Prepare statement insert customer gagal: " . $koneksi->error);

                $stmt_insert_cust->bind_param("sss", $nama, $nohp, $email);
                if (!$stmt_insert_cust->execute()) throw new Exception("Insert customer baru gagal: " . $stmt_insert_cust->error);
                $id_customer = $koneksi->insert_id;
                $stmt_insert_cust->close();
            }
            $stmt_cek->close();
            if (!$id_customer) throw new Exception("Gagal mendapatkan ID Customer.");


            // 3. Buat Transaksi (gunakan prepared statement)
            $tanggal_transaksi = date('Y-m-d H:i:s');
            $status_awal = 'menunggu pembayaran'; // Pastikan ini ada di ENUM tabel transaksi
            $jenis_transaksi = 'penjualan';

            $sql_insert_transaksi = "INSERT INTO transaksi (id_customer, jenis,status, tanggal, total, id_service) 
                                     VALUES (?, ?, ?, ?, ?, NULL)"; // id_service NULL untuk penjualan
            $stmt_trans = $koneksi->prepare($sql_insert_transaksi);
            if (!$stmt_trans) throw new Exception("Prepare statement insert transaksi gagal: " . $koneksi->error);

            $stmt_trans->bind_param("isdsd", $id_customer, $jenis_transaksi, $status_awal, $tanggal_transaksi, $total_belanja);
            if (!$stmt_trans->execute()) throw new Exception("Insert transaksi gagal: " . $stmt_trans->error);
            $id_transaksi_baru = $koneksi->insert_id;
            $stmt_trans->close();


            // 4. Buat Detail Transaksi & Kurangi Stok (gunakan prepared statement)
            $sql_insert_detail = "INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, subtotal) VALUES (?, ?, ?, ?)";
            $stmt_detail = $koneksi->prepare($sql_insert_detail);
            if (!$stmt_detail) throw new Exception("Prepare statement insert detail_transaksi gagal: " . $koneksi->error);

            $sql_update_stok = "UPDATE stok SET stok = stok - ? WHERE id_barang = ?";
            $stmt_stok = $koneksi->prepare($sql_update_stok);
            if (!$stmt_stok) throw new Exception("Prepare statement update stok gagal: " . $koneksi->error);

            foreach ($items_to_process as $item) {
                // Insert detail transaksi
                $stmt_detail->bind_param("iiid", $id_transaksi_baru, $item['id_barang'], $item['jumlah'], $item['subtotal']);
                if (!$stmt_detail->execute()) throw new Exception("Insert detail_transaksi untuk barang ID " . $item['id_barang'] . " gagal: " . $stmt_detail->error);

                // Kurangi stok
                $stmt_stok->bind_param("ii", $item['jumlah'], $item['id_barang']);
                if (!$stmt_stok->execute()) throw new Exception("Update stok untuk barang ID " . $item['id_barang'] . " gagal: " . $stmt_stok->error);
            }
            $stmt_detail->close();
            $stmt_stok->close();

            // Jika semua berhasil
            $koneksi->commit();
            $_SESSION['cart'] = []; // Kosongkan keranjang

            // Redirect ke halaman sukses dengan membawa ID transaksi
            header("Location: pesanan_sukses.php?id_transaksi=" . $id_transaksi_baru);
            exit;
        } catch (Exception $e) {
            $koneksi->rollback();
            $error_checkout = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage();
        }
    }
}
// ... (HTML form tetap sama) ...
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Checkout</h2>

        <?php if (empty($cart)): ?>
            <div class="alert alert-warning">Keranjang kosong</div>
        <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nama Pembeli</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">No HP</label>
                    <input type="text" name="nohp" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <h5>Detail Keranjang:</h5>
                <table class="table table-bordered table-striped mt-3">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $ids = implode(',', array_keys($cart));
                        $result = $koneksi->query("SELECT * FROM stok WHERE id_barang IN ($ids)");
                        $total = 0;

                        while ($row = $result->fetch_assoc()):
                            $qty = $cart[$row['id_barang']];
                            $subtotal = $row['harga'] * $qty;
                            $total += $subtotal;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                                <td><?= $qty ?></td>
                                <td>Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <tr>
                            <td colspan="3"><strong>Total</strong></td>
                            <td><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                <?php if ($error_checkout): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_checkout); ?></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary mt-3">Bayar Sekarang</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>