<?php
session_start();

// Koneksi ke database
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Periksa koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'add') {
    $id_barang = intval($_POST['id'] ?? 0);
    $nama_barang = $_POST['name'] ?? '';
    $harga = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);

    // Pastikan ID barang valid
    if ($id_barang > 0 && $quantity > 0) {
        // Cek stok barang di database
        $cekStok = $koneksi->query("SELECT stok FROM stok WHERE id_barang = $id_barang");
        $data = $cekStok->fetch_assoc();

        if ($data && $data['stok'] >= $quantity) {
            // Jika barang sudah ada di keranjang, tambahkan kuantitasnya
            if (isset($_SESSION['cart'][$id_barang])) {
                $_SESSION['cart'][$id_barang] += $quantity;
            } else {
                $_SESSION['cart'][$id_barang] = $quantity;
            }
            echo "Item berhasil ditambahkan ke keranjang.";
        } else {
            echo "Stok tidak cukup untuk jumlah yang diminta.";
        }
    } else {
        echo "Data barang tidak valid.";
    }
} elseif ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $qty = intval($_POST['quantity'] ?? 0);

    // Cek stok barang di database sebelum update
    if ($id > 0) {
        $cekStok = $koneksi->query("SELECT stok FROM stok WHERE id_barang = $id");
        $data = $cekStok->fetch_assoc();

        if ($data && $data['stok'] >= $qty) {
            if ($qty > 0) {
                $_SESSION['cart'][$id] = $qty;
            } else {
                unset($_SESSION['cart'][$id]);
            }
            echo cartView();
        } else {
            echo "Stok tidak cukup untuk memperbarui kuantitas.";
        }
    }
} elseif ($action === 'remove') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        unset($_SESSION['cart'][$id]);
    }
    echo cartView();
} elseif ($action === 'view') {
    echo cartView();
} elseif ($action === 'get_cart_json') {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['cart']);
    exit;
}

function cartView() {
    global $koneksi; // Menggunakan koneksi yang sudah ada

    if (empty($_SESSION['cart'])) {
        return '<i>Keranjang kosong</i>';
    }

    $ids = array_keys($_SESSION['cart']);
    // Pastikan semua ID adalah integer untuk keamanan
    $ids = array_map('intval', $ids);
    $ids_str = implode(',', $ids);
    if (!$ids_str) {
        return '<i>Keranjang kosong</i>'; // Seharusnya tidak terjadi jika $_SESSION['cart'] tidak kosong
    }

    $result = $koneksi->query("SELECT id_barang, nama_barang, harga FROM stok WHERE id_barang IN ($ids_str)");
    if (!$result) {
        return '<i>Gagal mengambil data produk: ' . $koneksi->error . '</i>';
    }

    $html = '<ul style="padding-left: 20px; margin: 0;">';
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $qty = $_SESSION['cart'][$row['id_barang']];
        $subtotal = $row['harga'] * $qty;
        $total += $subtotal;
        $html .= "<li>{$row['nama_barang']} x {$qty} = Rp " . number_format($subtotal, 0, ',', '.') . "</li>";
    }
    $html .= '</ul>';
    $html .= "<b>Total: Rp " . number_format($total, 0, ',', '.') . "</b>";

    return $html;
}

$koneksi->close();
?>