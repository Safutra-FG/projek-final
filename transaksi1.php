<?php
session_start();
$cart = $_SESSION['cart'] ?? [];
include 'koneksi.php';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cart)) {
    $nama = $_POST['nama'] ?? '';
    $nohp = $_POST['nohp'] ?? '';
    $email = $_POST['email'] ?? '';
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
    $koneksi->query("INSERT INTO transaksi (id_customer, total, tanggal) VALUES ($id_customer, $total, '$tanggal')");
    $id_transaksi = $koneksi->insert_id;

    // Simpan detail & kurangin stok
    $result = $koneksi->query("SELECT * FROM stok WHERE id_barang IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $id_barang = $row['id_barang'];
        $harga = $row['harga'];
        $qty = $cart[$id_barang];
        $subtotal = $qty * $harga;

        $koneksi->query("INSERT INTO detail_transaksi (id_transaksi, id_barang, qty, subtotal) VALUES ($id_transaksi, $id_barang, $qty, $subtotal)");

        // Kurangi stok
        $koneksi->query("UPDATE stok SET stok = stok - $qty WHERE id_barang = $id_barang");
    }

    // Kosongin keranjang
    $_SESSION['cart'] = [];

    echo "<script>alert('Transaksi berhasil!'); location.href='transaksi.php';</script>";
    exit;
}
?>

<h2>Checkout</h2>

<?php if (empty($cart)): ?>
    <p><i>Keranjang kosong</i></p>
<?php else: ?>
    <form method="post">
        <label>Nama Pembeli:<br>
            <input type="text" name="nama" required>
        </label><br><br>

        <label>No HP:<br>
            <input type="text" name="nohp" required>
        </label><br><br>

        <label >Email<br>
            <input type="email" name="email" required>
        </label><br>

        <br><table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Harga</th>
                    <th>jumlah</th>
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
        <br>
        <button type="submit">Bayar Sekarang</button>
    </form>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
</head>
<body>

    
</body>
</html>