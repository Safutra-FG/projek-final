<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Ambil data produk
$result = $koneksi->query("SELECT * FROM stok");
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
            width: 100%; /* Pastikan kontainer mengambil lebar penuh di layar kecil */
            background-color: #ffffff;
            border-radius: 16px; /* Lebih rounded */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); /* Shadow lebih dalam */
            padding: 30px; /* Padding lebih banyak */
        }

        .product-item {
            background: #fdfdfd; /* Sedikit lebih terang */
            padding: 20px; /* Padding lebih banyak */
            border-radius: 12px; /* Lebih rounded */
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); /* Shadow yang halus */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; /* Transisi untuk hover */
            border: 1px solid #e0e0e0; /* Border halus */
        }

        .product-item:hover {
            transform: translateY(-5px); /* Efek melayang */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); /* Shadow lebih kuat saat hover */
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 700; /* Lebih bold */
            color: #1a73e8; /* Warna biru yang lebih cerah */
            margin-bottom: 8px;
            font-size: 1.25rem; /* Lebih besar */
        }

        .product-price {
            color: #e65100; /* Warna oranye-merah yang menarik */
            font-size: 1.5rem; /* Lebih besar */
            font-weight: bold;
            margin-bottom: 8px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background-color: #edf2f7; /* Background kontrol kuantitas */
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e0;
        }

        .quantity-btn {
            width: 36px; /* Lebih lebar */
            height: 36px; /* Lebih tinggi */
            border: none;
            background: #4299e1; /* Biru Tailwind 500 */
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
            background-color: #3182ce; /* Biru Tailwind 600 */
        }

        .quantity-input {
            width: 50px; /* Lebih lebar */
            height: 36px;
            text-align: center;
            margin: 0; /* Hapus margin horizontal */
            border: none; /* Hapus border input */
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
            margin-top: 30px; /* Jarak lebih jauh */
            padding: 14px 30px; /* Padding lebih banyak */
            background: #28a745; /* Hijau cerah untuk tombol beli */
            color: white;
            border: none;
            font-weight: bold;
            border-radius: 8px; /* Lebih rounded */
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            font-size: 1.1rem;
            display: block; /* Pastikan tombol mengambil lebar penuh jika perlu */
            width: fit-content; /* Sesuaikan lebar tombol dengan konten */
            margin-left: auto; /* Pusatkan tombol jika ingin */
            margin-right: auto; /* Pusatkan tombol jika ingin */
        }

        #buy-btn:hover {
            background-color: #218838; /* Hijau lebih gelap saat hover */
        }

        /* Styling untuk teks "Stok" */
        .text-gray-600 {
            color: #6b7280; /* Warna abu-abu yang lebih kalem */
            font-size: 0.875rem; /* Ukuran lebih kecil */
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-100 to-indigo-50">
    <div class="container-wrapper">
        <h1 class="text-4xl font-extrabold text-blue-700 mb-8 text-center">Daftar Produk</h1>

        <div class="grid grid-cols-1 gap-5">
            <?php while ($row = $result->fetch_assoc()): ?>
            <div class="product-item">
                <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                    <div class="product-price">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?>,-</div>
                    <div class="text-gray-600">Stok Tersedia: <span class="font-semibold"><?php echo $row['stok']; ?></span></div>
                </div>
                <div class="quantity-control">
                    <button class="quantity-btn" onclick="updateCartItem(<?php echo $row['id_barang']; ?>, -1, <?php echo $row['stok']; ?>)">-</button>
                    <input type="text" id="qty-<?php echo $row['id_barang']; ?>" class="quantity-input" value="0" readonly />
                    <button class="quantity-btn" onclick="updateCartItem(<?php echo $row['id_barang']; ?>, 1, <?php echo $row['stok']; ?>)">+</button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <h2 class="text-3xl font-bold text-gray-800 mt-10 mb-5 text-center">Keranjang Belanja</h2>
        <div id="cart-box">Memuat keranjang...</div>

        <button id="buy-btn" onclick="goToCheckout()">Lanjutkan ke Pembayaran</button>
    </div>

    <script>
        function updateCartItem(id_barang, change, stok) {
            const inputQty = document.getElementById('qty-' + id_barang);
            let currentQty = parseInt(inputQty.value);
            let newQty = currentQty + change;

            if (newQty < 0) newQty = 0;
            if (newQty > stok) newQty = stok;

            // Update input dulu
            inputQty.value = newQty;

            // Kirim request update keranjang ke backend
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
                    // Sync quantity inputs dengan isi keranjang
                    syncQuantities();
                })
                .catch(() => {
                    document.getElementById('cart-box').innerHTML = '<i>Gagal memuat keranjang</i>';
                });
        }

        function syncQuantities() {
            // Ambil semua input quantity di produk
            const inputs = document.querySelectorAll('.quantity-input');

            fetch('checkout.php?action=get_cart_json')
                .then(res => res.json())
                .then(cart => {
                    inputs.forEach(input => {
                        // id barang ada di id input: qty-ID
                        const id = parseInt(input.id.replace('qty-', ''));
                        input.value = cart[id] ?? 0;
                    });
                });
        }

        function goToCheckout() {
            window.location.href = 'transaksi_barang.php';
        }

        // Load keranjang saat halaman siap
        document.addEventListener('DOMContentLoaded', () => {
            loadCart();
        });
    </script>

</body>

</html>