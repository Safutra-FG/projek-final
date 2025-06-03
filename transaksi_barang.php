<?php
session_start();
$cart = $_SESSION['cart'] ?? [];
include 'koneksi.php';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cart)) {
    $nama = $_POST['nama'] ?? '';
    $nohp = $_POST['nohp'] ?? '';
    $email = $_POST['email'] ?? '';
    $jenis = 'penjualan';
    $tanggal = date('Y-m-d H:i:s');
    $total = 0;

    // Hitung total
    $ids = implode(',', array_keys($cart));
    $result = $koneksi->query("SELECT id_barang, harga FROM stok WHERE id_barang IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $qty = $cart[$row['id_barang']];
        $subtotal = $qty * $row['harga'];
        $total += $subtotal;
    }

    $cekCustomer = $koneksi->query("SELECT id_customer FROM customer WHERE nama_customer = '$nama' AND no_telepon = '$nohp'");
    if ($cekCustomer->num_rows > 0) {
        $row = $cekCustomer->fetch_assoc();
        $id_customer = $row['id_customer'];
    } else {
        // Insert customer baru
        $koneksi->query("INSERT INTO customer (nama_customer, no_telepon, email) VALUES ('$nama', '$nohp', '$email')");
        $id_customer = $koneksi->insert_id;
    }

    // Simpan transaksi
    $koneksi->query("INSERT INTO transaksi (id_customer, jenis, total, tanggal) VALUES ($id_customer, '$jenis', $total, '$tanggal')");
    $id_transaksi = $koneksi->insert_id;

    // Simpan detail & kurangin stok
    $result = $koneksi->query("SELECT * FROM stok WHERE id_barang IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $id_barang = $row['id_barang'];
        $harga = $row['harga'];
        $qty = $cart[$id_barang];
        $subtotal = $qty * $harga;

        $koneksi->query("INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, subtotal) VALUES ($id_transaksi, $id_barang, $qty, $subtotal)");

        // Kurangi stok
        $koneksi->query("UPDATE stok SET stok = stok - $qty WHERE id_barang = $id_barang");
    }

    // Kosongin keranjang
    $_SESSION['cart'] = [];

    echo "<script>alert('Transaksi berhasil!'); location.href='transaksi.php';</script>";
    exit;
}
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

            <button type="submit" class="btn btn-primary mt-3">Bayar Sekarang</button>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
