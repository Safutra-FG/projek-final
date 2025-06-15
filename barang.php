<?php
session_start();
// Path ke koneksi.php sudah benar karena keduanya ada di root
require 'koneksi.php';

// Periksa koneksi database
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// --- PERUBAHAN 1: Mengubah Query untuk menyertakan data kategori ---
// Kita butuh 'JOIN' untuk mengambil nama kategori dari tabel lain.
// Asumsi: tabel produk Anda bernama 'stok' dan tabel kategori bernama 'kategori'.
// Asumsi: di tabel 'stok' ada kolom 'id_kategori' sebagai penghubung.
$sql = "SELECT stok.id_barang, stok.nama_barang, stok.harga, stok.stok, stok.gambar, kategori.jenis_kategori 
        FROM stok 
        JOIN kategori ON stok.id_kategori = kategori.id_kategori";
$result = $koneksi->query($sql);

// --- PERUBAHAN 2: Mengambil daftar semua kategori untuk dropdown filter ---
$kategori_sql = "SELECT * FROM kategori ORDER BY jenis_kategori ASC";
$kategori_result = $koneksi->query($kategori_sql);

// --- PERBAIKAN FINAL PATH GAMBAR ---
$uploadDir = 'uploads/';

// Dummy data untuk nama customer di header
$namaAkun = "Customer";

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Thar'z Computer - Beli Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS tidak diubah, hanya ditambahkan beberapa style untuk filter */
        body {
            font-family: 'Segoe UI', sans-serif;
            color: #333;
            background: linear-gradient(135deg, #a7e0f8 0%, #d8e5f2 50%, #f0f4f7 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container-wrapper {
            max-width: 1200px; /* Lebarkan container agar filter muat */
            width: 100%;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            padding: 30px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a73e8;
        }
        /* --- STYLE BARU untuk filter controls --- */
        .filter-controls {
            display: flex;
            gap: 15px;
        }
        .search-box {
            position: relative;
            width: 250px;
        }
        .search-box input, .category-filter select { /* Terapkan style ke select juga */
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #cbd5e0;
            border-radius: 20px;
            font-size: 1rem;
            outline: none;
            transition: all 0.2s ease-in-out;
            background-color: #fff;
        }
        .search-box input {
            padding-left: 40px;
        }
        .search-box input:focus, .category-filter select:focus {
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
        /* --- STYLE BARU untuk dropdown kategori --- */
        .category-filter {
            width: 200px;
        }
        .header-right {
            display: flex;
            align-items: center;
        }
        .customer-name {
            font-weight: 600;
            color: #2d3748;
        }
        /* Sisa CSS lainnya tetap sama */
        .back-to-dashboard-btn-wrapper { text-align: left; margin-bottom: 20px; margin-top: -10px; }
        .back-to-dashboard-btn { background-color: #6c757d; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background-color 0.2s ease-in-out; display: inline-flex; align-items: center; gap: 5px; }
        .back-to-dashboard-btn:hover { background-color: #5a6268; }
        .product-list { 
            /* --- PERUBAHAN BARU: Grid sekarang akan dibuat di dalam slide, bukan di sini --- */
            min-height: 800px; /* Beri tinggi minimum agar layout tidak "loncat" saat berpindah halaman */
            position: relative;
        }
        .product-grid-slide {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .product-item { background: #fdfdfd; padding: 20px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; border: 1px solid #e0e0e0; text-align: center; }
        .product-item:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); }
        .product-image-container { width: 150px; height: 150px; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; justify-content: center; align-items: center; overflow: hidden; margin-bottom: 15px; background-color: #f7fafc; }
        .product-image { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
        .product-info { flex-grow: 1; width: 100%; margin-bottom: 15px; }
        .product-name { font-weight: 700; color: #1a73e8; margin-bottom: 8px; font-size: 1.25rem; }
        .product-price { color: #e65100; font-size: 1.5rem; font-weight: bold; margin-bottom: 8px; }
        .quantity-control { display: flex; align-items: center; background-color: #edf2f7; border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e0; width: fit-content; margin: 0 auto; }
        .quantity-btn { width: 36px; height: 36px; border: none; background: #4299e1; color: white; font-weight: bold; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: background-color 0.2s ease-in-out; font-size: 1.2rem; }
        .quantity-btn:hover { background-color: #3182ce; }
        .quantity-input { width: 50px; height: 36px; text-align: center; margin: 0; border: none; font-size: 1rem; font-weight: 600; color: #2d3748; background-color: #edf2f7; user-select: none; }
        #cart-box { background: #fdfdfd; padding: 20px; border-radius: 12px; margin-top: 25px; min-height: 80px; border: 1px solid #e0e0e0; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); font-size: 0.95rem; color: #4a5568; }
        #buy-btn { margin-top: 30px; padding: 14px 30px; background: #28a745; color: white; border: none; font-weight: bold; border-radius: 8px; cursor: pointer; transition: background-color 0.2s ease-in-out; font-size: 1.1rem; display: block; width: fit-content; margin-left: auto; margin-right: auto; }
        #buy-btn:hover { background-color: #218838; }
        .text-gray-600 { color: #6b7280; font-size: 0.875rem; }
        .total-price-display { font-size: 2.2rem; font-weight: bold; color: #2c5282; text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: baseline; gap: 10px; }
        .total-price-display span { font-size: 1.8rem; color: #4a5568; }
        
        /* --- PERUBAHAN BARU: CSS untuk Navigasi Slide --- */
        .slider-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
        }
        .slider-btn {
            background-color: #1a73e8;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
            cursor: pointer;
            border: none;
        }
        .slider-btn:hover {
            background-color: #155cb0;
        }
        .slider-btn:disabled {
            background-color: #a0aec0; /* gray-400 */
            cursor: not-allowed;
            opacity: 0.7;
        }
        #slide-counter {
            font-weight: 600;
            color: #2d3748;
        }

    </style>
</head>
<body class="bg-gradient-to-br from-blue-100 to-indigo-50">
    <div class="container-wrapper">
        <div class="header-section">
            <div class="header-left">
                <div class="header-logo">Thar'z computer</div>
                
                <div class="filter-controls">
                    <div class="search-box">
                        <span class="search-icon">&#128269;</span>
                        <input type="text" id="search-input" placeholder="Cari barang...">
                    </div>

                    <div class="category-filter">
                        <select id="category-select">
                            <option value="all">Semua Kategori</option>
                            <?php if ($kategori_result->num_rows > 0): ?>
                                <?php while ($kategori = $kategori_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars(strtolower($kategori['jenis_kategori'])); ?>">
                                        <?php echo htmlspecialchars($kategori['jenis_kategori']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

            </div>
            <div class="header-right">
                <div class="customer-name"><?php echo htmlspecialchars($namaAkun); ?></div>
            </div>
        </div>
        <div class="back-to-dashboard-btn-wrapper">
            <a href="index.php" class="back-to-dashboard-btn"> <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0L5 11.414a1 1 0 010-1.414l3.293-3.293a1 1 0 011.414 1.414L7.414 10H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
                Dashboard
            </a>
        </div>

        <h1 class="text-4xl font-extrabold text-blue-700 mb-8 text-center" style="display: none;">Daftar Produk</h1>
        
        <div class="product-list" id="product-list-container">
            <?php
            // PHP loop tidak perlu diubah. JavaScript akan menangani pembagian slide.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()):
                    
                    $imageFilename = $row['gambar'] ?? null;
                    $htmlImagePath = $uploadDir . $imageFilename; // Path untuk HTML src
            ?>
                    <div class="product-item" 
                         data-product-name="<?php echo htmlspecialchars(strtolower($row['nama_barang'])); ?>"
                         data-product-category="<?php echo htmlspecialchars(strtolower($row['jenis_kategori'])); ?>">
                        
                        <div class="product-image-container">
                            <?php
                            if ($imageFilename && file_exists($htmlImagePath)) {
                                echo '<img src="' . htmlspecialchars($htmlImagePath) . '" alt="' . htmlspecialchars($row['nama_barang']) . '" class="product-image">';
                            } else {
                                echo '<span style="color: #a0aec0;">Gambar tidak tersedia</span>'; 
                            }
                            ?>
                        </div>
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
                <?php endwhile;
            } else {
                echo "<p class='text-center text-gray-500 col-span-full' id='no-products-message'>Tidak ada produk ditemukan.</p>";
            }
            ?>
        </div>
        <p class='text-center text-gray-500 col-span-full' id='no-filtered-products-message' style="display: none; margin-top: 20px;">Tidak ada produk yang cocok dengan filter Anda.</p>
        
        <div id="slider-navigation" class="slider-nav" style="display: none;">
            <button id="prev-slide-btn" class="slider-btn">Sebelumnya</button>
            <span id="slide-counter"></span>
            <button id="next-slide-btn" class="slider-btn">Berikutnya</button>
        </div>


        <h2 class="text-3xl font-bold text-gray-800 mt-10 mb-5 text-center">Keranjang Belanja</h2>
        <div id="cart-box">Memuat keranjang...</div>

        <div class="total-price-display">
            <span>Rp</span> <span id="total-price">0,-</span>
        </div>

        <button id="buy-btn" onclick="goToCheckout()">Bayar</button>
        
        </div>

    <script>
        // Fungsi-fungsi lama seperti updateCartItem, loadCart, dll. tetap sama.
        function updateCartItem(id_barang, change, stok) {
            const inputQty = document.getElementById('qty-' + id_barang);
            let currentQty = parseInt(inputQty.value);
            let newQty = currentQty + change;
            if (newQty < 0) newQty = 0;
            if (newQty > stok) {
                newQty = stok;
                alert('Stok tidak mencukupi!');
            }
            if (newQty === currentQty) { return; }
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
                loadTotalPrice();
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
                    loadTotalPrice();
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
        function formatRupiah(angka) {
            if (!angka) return '0,-';
            let reverse = angka.toString().split('').reverse().join('');
            let ribuan = reverse.match(/\d{1,3}/g);
            let formatted = ribuan.join('.').split('').reverse().join('');
            return formatted + ',-';
        }
        function goToCheckout() {
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
        
        document.addEventListener('DOMContentLoaded', () => {
            loadCart();

            // --- PERUBAHAN BARU: Logika JavaScript untuk Filter dan Paginasi (Slide) ---
            
            // Ambil semua elemen yang dibutuhkan
            const searchInput = document.getElementById('search-input');
            const categorySelect = document.getElementById('category-select');
            const productListContainer = document.getElementById('product-list-container');
            const noFilteredProductsMessage = document.getElementById('no-filtered-products-message');
            const noProductsMessage = document.getElementById('no-products-message');
            
            // Simpan semua item produk dalam satu array untuk performa lebih baik
            const allProductItems = Array.from(document.querySelectorAll('.product-item'));

            // Elemen-elemen untuk navigasi slide
            const sliderNav = document.getElementById('slider-navigation');
            const prevBtn = document.getElementById('prev-slide-btn');
            const nextBtn = document.getElementById('next-slide-btn');
            const slideCounterEl = document.getElementById('slide-counter');

            let currentSlide = 0;
            let slides = [];
            const itemsPerSlide = 9;

            // Fungsi untuk membuat dan merender slide berdasarkan item yang terlihat
            function setupSlider(visibleItems) {
                // Kosongkan kontainer dan reset state
                productListContainer.innerHTML = '';
                slides = [];
                currentSlide = 0;

                // Tampilkan pesan jika tidak ada hasil filter
                if (visibleItems.length === 0 && allProductItems.length > 0) {
                    noFilteredProductsMessage.style.display = 'block';
                    sliderNav.style.display = 'none';
                    return;
                } else {
                    noFilteredProductsMessage.style.display = 'none';
                }

                if (noProductsMessage) {
                    noProductsMessage.style.display = 'none';
                }
                
                // Hitung jumlah total slide yang dibutuhkan
                const totalSlides = Math.ceil(visibleItems.length / itemsPerSlide);

                // Buat setiap slide dan isi dengan produk
                for (let i = 0; i < totalSlides; i++) {
                    const slideElement = document.createElement('div');
                    slideElement.className = 'product-grid-slide';
                    
                    const slideItems = visibleItems.slice(i * itemsPerSlide, (i + 1) * itemsPerSlide);
                    slideItems.forEach(item => {
                        slideElement.appendChild(item);
                    });
                    
                    productListContainer.appendChild(slideElement);
                    slides.push(slideElement);
                }

                // Tampilkan navigasi hanya jika ada lebih dari satu slide
                if (totalSlides > 1) {
                    sliderNav.style.display = 'flex';
                } else {
                    sliderNav.style.display = 'none';
                }

                showCurrentSlide();
            }

            // Fungsi untuk menampilkan slide yang aktif dan menyembunyikan yang lain
            function showCurrentSlide() {
                slides.forEach((slide, index) => {
                    slide.style.display = (index === currentSlide) ? 'grid' : 'none';
                });
                updateNavButtons();
            }

            // Fungsi untuk memperbarui status tombol navigasi (disabled/enabled) dan teks counter
            function updateNavButtons() {
                const totalSlides = slides.length;
                prevBtn.disabled = (currentSlide === 0);
                nextBtn.disabled = (currentSlide >= totalSlides - 1);

                if (totalSlides > 0) {
                    slideCounterEl.textContent = `Halaman ${currentSlide + 1} dari ${totalSlides}`;
                } else {
                    slideCounterEl.textContent = '';
                }
            }
            
            // Event listener untuk tombol berikutnya
            nextBtn.addEventListener('click', () => {
                if (currentSlide < slides.length - 1) {
                    currentSlide++;
                    showCurrentSlide();
                }
            });

            // Event listener untuk tombol sebelumnya
            prevBtn.addEventListener('click', () => {
                if (currentSlide > 0) {
                    currentSlide--;
                    showCurrentSlide();
                }
            });

            // Fungsi utama yang menggabungkan filter dan paginasi
            function filterAndPaginateProducts() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedCategory = categorySelect.value.toLowerCase();

                // Saring produk berdasarkan input pencarian dan kategori
                const visibleItems = allProductItems.filter(item => {
                    const productName = item.dataset.productName;
                    const productCategory = item.dataset.productCategory;

                    const nameMatch = productName.includes(searchTerm);
                    const categoryMatch = (selectedCategory === 'all' || productCategory === selectedCategory);

                    return nameMatch && categoryMatch;
                });

                // Setelah produk disaring, buat ulang slide dengan hasil yang ada
                setupSlider(visibleItems);
            }

            // Panggil fungsi filter saat ada input di search box ATAU perubahan di dropdown kategori
            searchInput.addEventListener('keyup', filterAndPaginateProducts);
            categorySelect.addEventListener('change', filterAndPaginateProducts);

            // Inisialisasi slide saat halaman pertama kali dimuat
            filterAndPaginateProducts();
        });

    </script>

</body>
</html>