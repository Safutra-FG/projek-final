<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'update':
        $id_barang = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

        if ($id_barang > 0) {
            if ($quantity > 0) {
                // Ambil stok terbaru dari database untuk validasi sisi server
                $stmt = $koneksi->prepare("SELECT stok FROM stok WHERE id_barang = ?");
                $stmt->bind_param("i", $id_barang);
                $stmt->execute();
                $res = $stmt->get_result();
                $product = $res->fetch_assoc();

                if ($product && $quantity <= $product['stok']) {
                    $_SESSION['cart'][$id_barang] = $quantity;
                } else if ($product && $quantity > $product['stok']) {
                    $_SESSION['cart'][$id_barang] = $product['stok']; // Set ke stok maksimal
                    echo "<p class='text-red-500'>Jumlah barang melebihi stok yang tersedia untuk barang ID: " . $id_barang . ".</p>";
                } else {
                    unset($_SESSION['cart'][$id_barang]); // Hapus jika produk tidak ditemukan
                }
                $stmt->close();
            } else {
                unset($_SESSION['cart'][$id_barang]); // Hapus dari keranjang jika kuantitas 0
            }
        }
        renderCart(); // Render keranjang setelah update
        break;

    case 'view':
        renderCart();
        break;

    case 'get_cart_json':
        header('Content-Type: application/json');
        echo json_encode($_SESSION['cart']);
        exit(); // Penting: Hentikan eksekusi setelah mengirim JSON
        break;

    case 'get_total_price':
        header('Content-Type: application/json');
        $total_price = 0;
        if (!empty($_SESSION['cart'])) {
            $ids = implode(',', array_keys($_SESSION['cart']));
            $result_prices = $koneksi->query("SELECT id_barang, harga FROM stok WHERE id_barang IN ($ids)");
            $prices = [];
            while ($row = $result_prices->fetch_assoc()) {
                $prices[$row['id_barang']] = $row['harga'];
            }

            foreach ($_SESSION['cart'] as $id => $qty) {
                if (isset($prices[$id])) {
                    $total_price += $prices[$id] * $qty;
                }
            }
        }
        echo json_encode(['total' => $total_price]);
        exit(); // Penting: Hentikan eksekusi setelah mengirim JSON
        break;

    default:
        // Tampilkan keranjang secara default jika tidak ada action spesifik
        renderCart();
        break;
}

function renderCart() {
    global $koneksi;
    $cart_items = $_SESSION['cart'] ?? [];
    $total_all_items = 0;

    if (empty($cart_items)) {
        echo '<p>Keranjang belanja Anda kosong.</p>';
        return;
    }

    echo '<div class="space-y-3">';
    foreach ($cart_items as $id_barang => $quantity) {
        $stmt = $koneksi->prepare("SELECT nama_barang, harga FROM stok WHERE id_barang = ?");
        $stmt->bind_param("i", $id_barang);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        if ($item) {
            $subtotal = $item['harga'] * $quantity;
            $total_all_items += $subtotal;
            echo "<p>" . htmlspecialchars($item['nama_barang']) . " (" . $quantity . "x) - Rp " . number_format($subtotal, 0, ',', '.') . ",-</p>";
        } else {
            // Hapus item dari keranjang jika produk tidak lagi ada
            unset($_SESSION['cart'][$id_barang]);
        }
    }
    echo '</div>';
    // Anda bisa memilih untuk tidak menampilkan total di sini karena sudah ada di tampilan utama
    // echo '<p class="mt-4 font-bold text-lg">Total: Rp ' . number_format($total_all_items, 0, ',', '.') . ',-</p>';
}

$koneksi->close();
?>