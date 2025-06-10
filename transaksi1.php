<?php
session_start();
$cart = $_SESSION['cart'] ?? [];
include 'koneksi.php'; // Make sure koneksi.php is correctly configured for your database connection.

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

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thar'z Computer - Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .checkout-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        h2 {
            color: #2c3e50;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
        }
        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid #dcdcdc;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s ease;
        }
        input[type="text"]:focus,
        input[type="email"]:focus {
            border-color: #3498db;
            outline: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background-color: #f8f9fa;
        }
        th, td {
            border: 1px solid #e0e0e0;
            padding: 12px 15px;
            text-align: left;
            font-size: 0.95rem;
        }
        th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 700;
        }
        tfoot td {
            font-weight: 700;
            background-color: #f1f3f5;
        }
        button[type="submit"] {
            display: block;
            width: 100%;
            padding: 15px 25px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 30px;
        }
        button[type="submit"]:hover {
            background-color: #218838;
        }
        p.empty-cart-message {
            text-align: center;
            font-size: 1.1rem;
            color: #7f8c8d;
            padding: 20px;
            border: 1px dashed #bdc3c7;
            border-radius: 8px;
            margin-top: 20px;
        }
        .back-to-shop-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.2s ease;
            font-weight: 500;
        }
        .back-to-shop-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <h2>Checkout</h2>

        <?php if (empty($cart)): ?>
            <p class="empty-cart-message"><i>Keranjang belanja Anda kosong. Silakan kembali ke halaman produk untuk memilih barang.</i></p>
            <div class="text-center">
                <a href="beli_barang.php" class="back-to-shop-btn">Kembali Belanja</a>
            </div>
        <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label for="nama">Nama Pembeli:</label>
                    <input type="text" class="form-control" id="nama" name="nama" required>
                </div>

                <div class="mb-3">
                    <label for="nohp">No HP:</label>
                    <input type="text" class="form-control" id="nohp" name="nohp" required>
                </div>

                <div class="mb-3">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <h3 class="mt-4 mb-3 text-lg font-semibold text-gray-700">Detail Pesanan:</h3>
                <table class="table table-bordered">
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
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total Belanja:</strong></td>
                            <td><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
                
                <button type="submit" class="btn btn-success">Bayar Sekarang</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>