<?php
session_start();
require 'koneksi.php';

// --- BAGIAN DEBUGGING ---
// Jika masih ada masalah, hapus tanda // pada baris di bawah ini.
// Ini akan membuat file 'debug_log.txt' yang berisi data apa saja yang diterima server.
// file_put_contents('debug_log.txt', print_r($_POST, true), FILE_APPEND);
// -----------------------

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ambil aksi dari request GET atau POST
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'update':
        $id_barang = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

        if ($id_barang > 0) {
            if ($quantity > 0) {
                // Validasi kuantitas dengan stok di database
                $stmt = $koneksi->prepare("SELECT stok FROM stok WHERE id_barang = ?");
                $stmt->bind_param("i", $id_barang);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                $stmt->close();

                if ($product) {
                    // Pastikan kuantitas tidak melebihi stok
                    $validated_qty = min($quantity, $product['stok']);
                    $_SESSION['cart'][$id_barang] = $validated_qty;
                }
            } else {
                // Hapus dari keranjang jika kuantitasnya 0
                unset($_SESSION['cart'][$id_barang]);
            }
        }
        renderCart(); // Tampilkan keranjang yang sudah diperbarui
        break;

    case 'view':
        renderCart();
        break;

    case 'get_cart_json':
        header('Content-Type: application/json');
        echo json_encode($_SESSION['cart']);
        break;

    case 'get_total_price':
        header('Content-Type: application/json');
        echo json_encode(['total' => calculateTotalPrice()]);
        break;
}

/**
 * Fungsi untuk menghitung total harga dari item di keranjang.
 * @return int Total harga.
 */
function calculateTotalPrice() {
    global $koneksi;
    $total_price = 0;
    $cart_items = $_SESSION['cart'] ?? [];

    if (empty($cart_items)) {
        return 0;
    }
    
    $product_ids = array_keys($cart_items);
    $ids_string = implode(',', $product_ids);
    
    // Ambil semua harga dalam satu query
    $sql = "SELECT id_barang, harga FROM stok WHERE id_barang IN ($ids_string)";
    $result = $koneksi->query($sql);
    
    $prices = [];
    while ($row = $result->fetch_assoc()) {
        $prices[$row['id_barang']] = $row['harga'];
    }
    
    foreach ($cart_items as $id => $qty) {
        if (isset($prices[$id])) {
            $total_price += $prices[$id] * $qty;
        }
    }
    
    return $total_price;
}

/**
 * Fungsi untuk menampilkan (render) HTML dari isi keranjang.
 */
function renderCart() {
    global $koneksi;
    $cart_items = $_SESSION['cart'] ?? [];

    if (empty($cart_items)) {
        echo '<p class="text-gray-500 italic">Keranjang belanja Anda kosong.</p>';
        return;
    }

    $product_ids = array_keys($cart_items);
    $ids_string = implode(',', $product_ids);

    // Ambil semua data produk di keranjang dalam satu query
    $sql = "SELECT id_barang, nama_barang, harga FROM stok WHERE id_barang IN ($ids_string)";
    $result = $koneksi->query($sql);

    $products_data = [];
    while ($row = $result->fetch_assoc()) {
        $products_data[$row['id_barang']] = $row;
    }

    echo '<div class="space-y-2">';
    foreach ($cart_items as $id_barang => $quantity) {
        if (isset($products_data[$id_barang])) {
            $item = $products_data[$id_barang];
            $subtotal = $item['harga'] * $quantity;
            
            echo '<div class="flex justify-between items-center text-gray-800">';
            echo '  <span>' . htmlspecialchars($item['nama_barang']) . ' <span class="text-sm text-gray-500">(x' . $quantity . ')</span></span>';
            echo '  <span class="font-semibold">Rp ' . number_format($subtotal, 0, ',', '.') . ',-</span>';
            echo '</div>';
        }
    }
    echo '</div>';
}

$koneksi->close();
?>