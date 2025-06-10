<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// proses pembelian
$message = "";
if (isset($_POST['beli'])) {
    $id_barang = $_POST['id_barang'];
    $jumlah = intval($_POST['jumlah']);

    // cek stok barang
    $cekStok = $koneksi->query("SELECT stok FROM stok WHERE id_barang = $id_barang");
    $data = $cekStok->fetch_assoc();

    if ($data && $data['stok'] >= $jumlah) {
        // update stok
        $koneksi->query("UPDATE stok SET stok = stok - $jumlah WHERE id_barang = $id_barang");
        // simpan transaksi
        $koneksi->query("INSERT INTO transaksi (id_barang, jumlah) VALUES ($id_barang, $jumlah)");

        $message = "Pembelian berhasil!";
    } else {
        $message = "Stok tidak cukup!";
    }
}

// ambil data barang
$result = $koneksi->query("SELECT * FROM stok");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thar'z Computer - Produk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #a7e0f8 0%, #d8e5f2 50%, #f0f4f7 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #333; /* Default text color */
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
            display: flex;
            flex-direction: column; /* Ubah menjadi kolom untuk centering */
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #a7e0f8; /* Warna biru yang lebih terang */
        }

        .header-logo img {
            width: 80px; /* Logo lebih besar */
            height: 80px;
            object-fit: contain;
            margin-bottom: 10px; /* Jarak antara logo dan nama perusahaan */
        }

        .company-name {
            font-size: 2.5rem; /* Ukuran font lebih besar */
            font-weight: 800; /* Lebih tebal */
            color: #1d5fab; /* Warna biru utama */
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1); /* Sedikit bayangan teks */
        }

        .search-bar-container {
            margin-bottom: 25px;
        }

        .search-input {
            width: 100%;
            padding: 12px 18px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 1rem;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .search-input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }

        .section-title {
            background-color: #1d5fab; /* Warna biru utama */
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: bold;
            font-size: 1.25rem; /* Ukuran font lebih besar */
            text-align: center;
        }

        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Responsive grid */
            gap: 20px; /* Jarak antar item */
        }

        .product-item {
            background-color: #fdfdfd;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column; /* Tata letak vertikal */
            justify-content: space-between;
            align-items: flex-start; /* Align info ke kiri */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: 1px solid #e0e0e0;
        }

        .product-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-name-display {
            font-weight: 700;
            font-size: 1.3rem; /* Ukuran nama produk lebih besar */
            margin-bottom: 8px;
            color: #1a73e8; /* Warna biru yang cerah */
        }

        .product-price-display {
            font-weight: bold;
            font-size: 1.6rem; /* Ukuran harga lebih besar */
            color: #e65100; /* Warna oranye-merah */
            margin-bottom: 10px;
        }

        .product-stock-display {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .product-action {
            display: flex;
            align-items: center;
            justify-content: space-between; /* Untuk meletakkan kontrol dan tombol beli */
            width: 100%; /* Agar memenuhi lebar item */
            margin-top: auto; /* Dorong ke bawah */
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background-color: #edf2f7;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e0;
        }

        .quantity-btn {
            width: 38px; /* Lebih lebar */
            height: 38px; /* Lebih tinggi */
            background-color: #4299e1;
            color: white;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease-in-out;
        }

        .quantity-btn:hover {
            background-color: #3182ce;
        }

        .quantity-input {
            width: 50px; /* Lebih lebar */
            height: 38px;
            text-align: center;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            background-color: #edf2f7;
        }
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .add-to-cart-btn {
            background-color: #28a745; /* Hijau untuk tombol tambah */
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s ease-in-out;
            font-size: 0.95rem;
        }

        .add-to-cart-btn:hover {
            background-color: #218838;
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: #1d5fab;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            transition: color 0.2s ease-in-out;
        }

        .back-link:hover {
            color: #1a4f89;
            text-decoration: underline;
        }

        .cart-section-title {
            font-size: 2rem; /* Judul keranjang lebih besar */
            font-weight: bold;
            color: #2d3748;
            margin-top: 40px;
            margin-bottom: 20px;
            text-align: center;
        }

        #cart-box {
            background: #fdfdfd;
            padding: 25px;
            border-radius: 12px;
            min-height: 100px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            font-size: 0.95rem;
            color: #4a5568;
        }

        #cart-box p {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        #cart-box strong {
            color: #1d5fab;
        }

        .checkout-btn {
            margin-top: 30px;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            border: none;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            font-size: 1.2rem;
            display: block;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .checkout-btn:hover {
            background-color: #218838;
        }
    </style>
</head>

<body>
    <div class="container-wrapper">
        <div class="header-section">
            <div class="header-logo">
                <img src="icons/logo.png" alt="Thar'z Computer Logo">
            </div>
            <div class="company-name">THAR'Z COMPUTER</div>
        </div>

        <div class="search-bar-container">
            <input type="text" placeholder="Cari sparepart..." class="search-input">
        </div>

        <div class="section-title">Daftar Sparepart Tersedia</div>

        <?php if ($message): ?>
            <div class="message-box <?php echo strpos($message, 'berhasil') !== false ? 'message-success' : 'message-error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="product-list">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="product-item">
                    <div class="product-info">
                        <div class="product-name-display"><?php echo $row['nama_barang']; ?></div>
                        <div class="product-price-display">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?>,-</div>
                        <div class="product-stock-display">Stok: <?php echo $row['stok']; ?></div>
                    </div>
                    <div class="product-action">
                        <form onsubmit="event.preventDefault(); addToCart(<?php echo $row['id_barang']; ?>, '<?php echo htmlspecialchars($row['nama_barang']); ?>', <?php echo $row['harga']; ?>, this.querySelector('.quantity-input').value);">
                            <input type="hidden" name="id_barang" value="<?php echo $row['id_barang']; ?>">
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn minus">-</button>
                                <input type="number" name="jumlah" class="quantity-input" value="1" min="1" max="<?php echo $row['stok']; ?>" required>
                                <button type="button" class="quantity-btn plus">+</button>
                            </div>
                            <button type="submit" name="tambah_ke_keranjang" class="add-to-cart-btn">Tambahkan ke Keranjang</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <a href="index.php" class="back-link">← Kembali ke Beranda</a>

        <h4 class="cart-section-title">Keranjang Belanja Anda</h4>
        <div id="cart-box">Memuat keranjang...</div>

        <button onclick="window.location.href='checkout.php'" class="checkout-btn">Lanjutkan ke Pembayaran</button>
    </div>

    <script>
        // JavaScript untuk kontrol kuantitas di setiap item produk
        document.querySelectorAll('.product-item').forEach(item => {
            const minusBtn = item.querySelector('.quantity-btn.minus');
            const plusBtn = item.querySelector('.quantity-btn.plus');
            const inputQty = item.querySelector('.quantity-input');

            if (minusBtn) {
                minusBtn.addEventListener('click', function() {
                    let value = parseInt(inputQty.value);
                    if (value > parseInt(inputQty.min)) {
                        inputQty.value = value - 1;
                    }
                });
            }

            if (plusBtn) {
                plusBtn.addEventListener('click', function() {
                    let value = parseInt(inputQty.value);
                    if (value < parseInt(inputQty.max)) {
                        inputQty.value = value + 1;
                    }
                });
            }
        });


        // Fungsi untuk mengelola keranjang (menggunakan jQuery sesuai script Anda)
        function loadCart() {
            $.get('cart_handler.php?action=view', function(data) {
                $('#cart-box').html(data);
            });
        }

        function addToCart(id, name, price, quantity) {
            // Periksa ketersediaan stok di sini sebelum mengirim ke cart_handler.php
            // Anda mungkin perlu mengambil stok lagi dari server atau menyimpannya di DOM/JS
            // Untuk kesederhanaan, saya akan menggunakan input.max sebagai referensi awal.
            const inputElement = document.getElementById('qty-' + id);
            const currentStock = parseInt(inputElement ? inputElement.max : 9999); // Ambil dari atribut max input

            if (quantity > currentStock) {
                alert('Jumlah yang Anda masukkan melebihi stok yang tersedia!');
                return;
            }

            $.post('cart_handler.php', {
                action: 'add',
                id: id,
                name: name,
                price: price,
                quantity: quantity // Kirim kuantitas yang dipilih
            }, function() {
                loadCart(); // Muat ulang keranjang setelah item ditambahkan
                // Reset quantity input menjadi 1 setelah ditambahkan (opsional)
                if (inputElement) {
                    inputElement.value = 1;
                }
            });
        }

        function updateCart(id, type) {
            $.post('cart_handler.php', {
                action: type,
                id: id
            }, function() {
                loadCart();
            });
        }

        // Load keranjang saat dokumen siap
        $(document).ready(function() {
            loadCart();
        });
    </script>
</body>

</html>