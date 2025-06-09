<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Periksa koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Ambil semua data produk karena filtering akan dilakukan di JavaScript
// Pastikan Anda mengambil kolom 'gambar' juga dari tabel 'stok'
$sql = "SELECT id_barang, nama_barang, harga, stok, gambar FROM stok";
$result = $koneksi->query($sql);

// Pastikan direktori gambar produk ada
$productImageDir = 'icons/products/'; // Sesuaikan dengan lokasi folder gambar Anda
if (!is_dir($productImageDir)) {
    // Jika direktori tidak ada, buatlah (opsional, untuk development)
    mkdir($productImageDir, 0777, true);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Thar'z Computer - Beli Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            color: #333;
            /* Background gradient yang lebih menarik */
            background: linear-gradient(135deg, #a7e0f8 0%, #d8e5f2 50%, #f0f4f7 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container-wrapper {
            max-width: 960px;
            width: 100%;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            padding: 30px;
        }

        .header-section {
            display: flex; /* Kembali ke flex untuk baris atas */
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px; /* Kurangi margin bawah karena tombol di luar */
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        /* Hapus .header-top-row dan .header-left-top karena tidak lagi diperlukan dengan struktur baru */
        .header-left, .header-right {
            display: flex;
            align-items: center;
        }

        .header-logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a73e8;
            margin-right: 20px; /* Jarak antara logo dan search box */
        }

        .search-box {
            position: relative;
            width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #cbd5e0;
            border-radius: 20px;
            font-size: 1rem;
            outline: none;
            transition: all 0.2s ease-in-out;
        }

        .search-box input:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }

        .search-box .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .notification-icon {
            font-size: 1.5rem;
            color: #4a5568;
            cursor: pointer;
            transition: color 0.2s ease-in-out;
        }

        .notification-icon:hover {
            color: #1a73e8;
        }

        .customer-name {
            font-weight: 600;
            color: #2d3748;
        }

        /* Styling untuk tombol kembali ke dashboard */
        .back-to-dashboard-btn-wrapper {
            text-align: left; /* Sesuaikan posisi tombol */
            margin-bottom: 20px; /* Jarak dari daftar produk */
            margin-top: -10px; /* Tarik sedikit ke atas agar lebih dekat dengan garis */
        }

        .back-to-dashboard-btn {
            background-color: #6c757d; /* Abu-abu netral */
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out;
            display: inline-flex; /* Agar bisa berada di baris sendiri tapi konten di dalamnya sejajar */
            align-items: center;
            gap: 5px;
        }

        .back-to-dashboard-btn:hover {
            background-color: #5a6268;
        }

        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* Responsif */
            gap: 20px;
        }

        .product-item {
            background: #fdfdfd;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            flex-direction: column; /* Mengubah arah flex menjadi kolom */
            align-items: center; /* Pusatkan item secara horizontal */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: 1px solid #e0e0e0;
            text-align: center; /* Pusatkan teks */
        }

        .product-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .product-image-container {
            width: 150px; /* Atur lebar container gambar */
            height: 150px; /* Atur tinggi container gambar */
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden; /* Pastikan gambar tidak melebihi batas */
            margin-bottom: 15px;
            background-color: #f7fafc;
        }

        .product-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; /* Memastikan gambar pas di dalam container */
            display: block; /* Menghilangkan spasi ekstra di bawah gambar */
        }

        .product-info {
            flex-grow: 1; /* Memberi ruang yang tersisa */
            width: 100%; /* Agar info mengisi lebar penuh */
            margin-bottom: 15px; /* Jarak antara info dan kontrol kuantitas */
        }

        .product-name {
            font-weight: 700;
            color: #1a73e8;
            margin-bottom: 8px;
            font-size: 1.25rem;
        }

        .product-description {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .product-price {
            color: #e65100;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background-color: #edf2f7;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e0;
            width: fit-content; /* Sesuaikan lebar kontrol kuantitas dengan isinya */
            margin: 0 auto; /* Pusatkan kontrol kuantitas */
        }

        .quantity-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: #4299e1;
            color: white;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease-in-out;
            font-size: 1.2rem;
        }

        .quantity-btn:hover {
            background-color: #3182ce;
        }

        .quantity-input {
            width: 50px;
            height: 36px;
            text-align: center;
            margin: 0;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            background-color: #edf2f7;
            user-select: none;
        }
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        #cart-box {
            background: #fdfdfd;
            padding: 20px;
            border-radius: 12px;
            margin-top: 25px;
            min-height: 80px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            font-size: 0.95rem;
            color: #4a5568;
        }

        #cart-box p {
            margin-bottom: 8px;
        }

        #cart-box strong {
            color: #1d5fab;
        }

        #buy-btn {
            margin-top: 30px;
            padding: 14px 30px;
            background: #28a745;
            color: white;
            border: none;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            font-size: 1.1rem;
            display: block;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        #buy-btn:hover {
            background-color: #218838;
        }

        .text-gray-600 {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .total-price-display {
            font-size: 2.2rem; /* Ukuran lebih besar */
            font-weight: bold;
            color: #2c5282; /* Warna biru gelap */
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            align-items: baseline;
            gap: 10px;
        }

        .total-price-display span {
            font-size: 1.8rem; /* Sedikit lebih kecil untuk "Rp" */
            color: #4a5568;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-50">
    <div class="container-wrapper">
        <div class="header-section">
            <div class="header-left">
                <div class="header-logo">Thar'z computer</div>
                <div class="search-box">
                    <span class="search-icon">&#128269;</span>
                    <input type="text" id="search-input" placeholder="search">
                </div>
            </div>
            <div class="header-right">
                <span class="notification-icon">&#128276;</span>
                <div class="customer-name">Customer</div>
            </div>
        </div>
        <div class="back-to-dashboard-btn-wrapper">
            <a href="index.php" class="back-to-dashboard-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0L5 11.414a1 1 0 010-1.414l3.293-3.293a1 1 0 011.414 1.414L7.414 10H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
                Dashboard
            </a>
        </div>

        <h1 class="text-4xl font-extrabold text-blue-700 mb-8 text-center" style="display: none;">Daftar Produk</h1>
        <div class="product-list" id="product-list-container">
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()): ?>
                <div class="product-item" data-product-name="<?php echo htmlspecialchars(strtolower($row['nama_barang'])); ?>">
                    <div class="product-image-container">
                        <?php
                        $imagePath = $productImageDir . htmlspecialchars($row['gambar']);
                        if (!empty($row['gambar']) && file_exists($imagePath)) {
                            echo '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($row['nama_barang']) . '" class="product-image">';
                        } else {
                            echo '<span style="color: #a0aec0;">No Image</span>';
                        }
                        ?>
                    </div>
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                        <div class="product-description">Deskripsi Produk</div>
                        <div class="product-price">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?>,-</div>
                        <div class="text-gray-600">Stok Tersedia: <span class="font-semibold"><?php echo $row['stok']; ?></span></div>
                    </div>
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="updateCartItem(<?php echo $row['id_barang']; ?>, -1, <?php echo $row['stok']; ?>)">-</button>
                        <input type="text" id="qty-<?php echo $row['id_barang']; ?>" class="quantity-input" value="0" readonly />
                        <button class="quantity-btn" onclick="updateCartItem(<?php echo $row['id_barang']; ?>, 1, <?php echo $row['stok']; ?>)">+</button>
                    </div>
                </div>
                <?php endwhile;
            } else {
                echo "<p class='text-center text-gray-500 col-span-full' id='no-products-message'>Tidak ada produk ditemukan.</p>";
            }
            ?>
        </div>
        <p class='text-center text-gray-500 col-span-full' id='no-filtered-products-message' style="display: none;">Tidak ada produk yang cocok dengan pencarian Anda.</p>

        <h2 class="text-3xl font-bold text-gray-800 mt-10 mb-5 text-center">Keranjang Belanja</h2>
        <div id="cart-box">Memuat keranjang...</div>

        <div class="total-price-display">
            <span>Rp</span> <span id="total-price">0,-</span>
        </div>

        <button id="buy-btn" onclick="goToCheckout()">Bayar</button>

        <div class="flex justify-start mt-8">
            <span style="font-size: 24px; color: #4a5568;">&#128712;</span>
        </div>
    </div>

    <script>
        function updateCartItem(id_barang, change, stok) {
            const inputQty = document.getElementById('qty-' + id_barang);
            let currentQty = parseInt(inputQty.value);
            let newQty = currentQty + change;

            if (newQty < 0) newQty = 0;
            if (newQty > stok) {
                newQty = stok;
                alert('Stok tidak mencukupi!'); // Notifikasi jika melebihi stok
            }

            // Hanya update jika kuantitas berubah
            if (newQty === currentQty) {
                return;
            }

            inputQty.value = newQty;

            const data = new URLSearchParams();
            data.append('action', 'update');
            data.append('id', id_barang);
            data.append('quantity', newQty);

            fetch('checkout.php', {
                method: 'POST',
                body: data,
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('cart-box').innerHTML = html;
                loadTotalPrice(); // Perbarui total harga setelah update keranjang
            })
            .catch(() => {
                alert('Gagal update keranjang');
            });
        }

        function loadCart() {
            fetch('checkout.php?action=view')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('cart-box').innerHTML = html;
                    syncQuantities();
                    loadTotalPrice(); // Muat total harga saat keranjang dimuat
                })
                .catch(() => {
                    document.getElementById('cart-box').innerHTML = '<i>Gagal memuat keranjang</i>';
                });
        }

        function syncQuantities() {
            const inputs = document.querySelectorAll('.quantity-input');

            fetch('checkout.php?action=get_cart_json')
                .then(res => res.json())
                .then(cart => {
                    inputs.forEach(input => {
                        const id = parseInt(input.id.replace('qty-', ''));
                        input.value = cart[id] ?? 0;
                    });
                });
        }

        // Fungsi baru untuk memuat dan menampilkan total harga
        function loadTotalPrice() {
            fetch('checkout.php?action=get_total_price')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('total-price').innerText = formatRupiah(data.total);
                })
                .catch(() => {
                    document.getElementById('total-price').innerText = '0,-';
                });
        }

        // Fungsi untuk format rupiah
        function formatRupiah(angka) {
            let reverse = angka.toString().split('').reverse().join('');
            let ribuan = reverse.match(/\d{1,3}/g);
            let formatted = ribuan.join('.').split('').reverse().join('');
            return formatted + ',-';
        }

        function goToCheckout() {
            // Pastikan ada item di keranjang sebelum melanjutkan ke transaksi
            fetch('checkout.php?action=get_cart_json')
                .then(res => res.json())
                .then(cart => {
                    const hasItems = Object.keys(cart).some(id => cart[id] > 0);
                    if (hasItems) {
                        window.location.href = 'transaksi_barang.php';
                    } else {
                        alert('Keranjang belanja masih kosong!');
                    }
                })
                .catch(() => {
                    alert('Terjadi kesalahan saat memeriksa keranjang.');
                });
        }

        // --- Logika Live Search ---
        document.addEventListener('DOMContentLoaded', () => {
            loadCart(); // Muat keranjang seperti biasa

            const searchInput = document.getElementById('search-input');
            const productItems = document.querySelectorAll('.product-item');
            const noFilteredProductsMessage = document.getElementById('no-filtered-products-message');
            const noProductsMessage = document.getElementById('no-products-message'); // Pesan jika tidak ada produk sama sekali dari DB

            // Sembunyikan pesan "Tidak ada produk ditemukan" jika ada produk dari awal
            if (noProductsMessage && productItems.length > 0) {
                noProductsMessage.style.display = 'none';
            }

            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                let foundProducts = 0;

                productItems.forEach(item => {
                    const productName = item.dataset.productName; // Ambil nama produk dari data-attribute
                    if (productName.includes(searchTerm)) {
                        item.style.display = 'flex'; // Tampilkan elemen
                        foundProducts++;
                    } else {
                        item.style.display = 'none'; // Sembunyikan elemen
                    }
                });

                // Tampilkan atau sembunyikan pesan "Tidak ada produk yang cocok"
                if (foundProducts === 0 && productItems.length > 0) { // Hanya tampilkan jika ada produk yang difilter dan tidak ada yang cocok
                    noFilteredProductsMessage.style.display = 'block';
                } else {
                    noFilteredProductsMessage.style.display = 'none';
                }

                // Pastikan pesan "Tidak ada produk ditemukan" (dari PHP) juga disembunyikan jika pencarian aktif
                if (noProductsMessage) {
                    noProductsMessage.style.display = 'none';
                }
            });
        });
    </script>

</body>

</html>