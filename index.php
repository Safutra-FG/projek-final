<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thar'z Computer</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .navbar-custom {
            background-color: rgb(29, 95, 171);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* --- PERUBAHAN FONT NAVBAR --- */
        .navbar-brand h1,
        .navbar-nav .nav-link,
        .navbar-nav .btn {
            font-family: 'Poppins', sans-serif; /* Menerapkan font Poppins */
        }

        .navbar-brand img {
            height: 50px;
            width: 50px;
            margin-right: 10px;
            border-radius: 50%;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            color: white;
        }

        .navbar-nav .nav-link.active {
            font-weight: 700;
            color: white;
        }
        
        .hero-section {
            /* Perubahan di sini untuk background */
            background: linear-gradient(rgba(0, 70, 170, 0.6), rgba(0, 70, 170, 0.6)), url('icons/Logoo.png') no-repeat center 80%;
            background-size: cover;
            color: white;
            padding: 100px 20px 20px 20px; /* Padding atas ditambah agar teks tidak terlalu menempel ke navbar */
            text-align: center;
            display: flex;
            min-height: 90vh; 
            flex-direction: column;
            position: relative;
            
            /* --- PERUBAHAN POSISI TEKS "SELAMAT DATANG" --- */
            justify-content: flex-start; /* Mengubah posisi vertikal ke atas */
            align-items: center;
        }

        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .hero-section p {
            font-size: 1.5rem;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        /* --- PERUBAHAN POSISI MENU LAYANAN --- */
        .hero-menu-container {
            position: absolute;
            bottom: 40px; /* Posisi dinaikkan agar tidak terpotong */
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            max-width: 960px;
            z-index: 10;
        }

        /* Section umum */
        .section-padding {
            padding: 80px 20px; /* Padding ditambah agar ada jarak dari menu */
        }
        .section-heading {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 3rem;
        }
        
        .about-section, .why-choose-us-section, .testimonials-section, .contact-section {
            text-align: center;
            color: #343a40;
            /* Menghapus padding-top spesifik karena sudah diatur di .section-padding */
        }

        .about-section { background-color: #f8f9fa; }
        .why-choose-us-section { background-color: #e9ecef; }
        .testimonials-section { background-color: #f8f9fa; }
        .contact-section { background-color: #e9ecef; }

        .box-link {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: rgb(29, 95, 171);
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            min-height: 220px;
        }

        .box-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(29, 95, 171, 0.3);
            color: rgb(29, 95, 171);
        }

        .box-link i { font-size: 4rem; margin-bottom: 15px; color: rgb(29, 95, 171); }
        .box-link .menu-title { font-size: 1.5rem; margin-bottom: 10px; color: rgb(29, 95, 171); }
        .box-link .menu-description { font-size: 0.9rem; color: #6c757d; text-align: center; font-weight: normal; }
        .feature-icon { font-size: 3rem; color: #1e3a8a; margin-bottom: 1rem; }
        
        .testimonial-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
        }
        .testimonial-card img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid #1e3a8a;
        }
        .testimonial-card .quote { font-style: italic; color: #555; margin-bottom: 10px; }
        .testimonial-card .author { font-weight: bold; color: #1e3a8a; }

        .contact-info p { margin-bottom: 10px; font-size: 1.1rem; }
        .contact-info i { margin-right: 10px; color: #1e3a8a; }

        .footer {
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-top: auto;
        }

        /* Media Queries untuk responsif */
        @media (max-width: 992px) {
            .hero-section h1 { font-size: 2.25rem; }
            .hero-menu-container {
                bottom: 30px;
                width: 90%;
            }
        }

        @media (max-width: 768px) {
            .hero-section { padding: 80px 15px 20px 15px; }
            .hero-section h1 { font-size: 2rem; }
            .hero-section p { font-size: 1.2rem; }
            .hero-menu-container {
                bottom: 20px;
                width: 95%;
            }
            .box-link { min-height: 200px; }
            .box-link i { font-size: 3.5rem; }
            .box-link .menu-title { font-size: 1.3rem; }
            .box-link .menu-description { font-size: 0.85rem; }
            .section-padding { padding: 60px 15px; }
            .section-heading { font-size: 2rem; }
        }

        @media (max-width: 576px) {
            .hero-section {
                min-height: auto; /* Dibuat auto agar menyesuaikan konten */
                justify-content: center; /* Kembali ke tengah untuk layar sangat kecil */
            }
            .hero-section h1 { font-size: 1.75rem; }
            .hero-section p { font-size: 1rem; }
            .hero-menu-container {
                position: static;
                width: 100%;
                transform: none;
                margin-top: 30px; 
                padding: 0 15px;
            }
            .box-link { min-height: 180px; padding: 20px; }
            .box-link i { font-size: 3rem; }
            .box-link .menu-title { font-size: 1.2rem; }
            .box-link .menu-description { font-size: 0.8rem; }
            .navbar-brand h1 { font-size: 1.2rem; }
            .navbar-brand img { height: 40px; width: 40px; }
            .section-padding { padding-top: 60px; }
            .section-heading { font-size: 1.75rem; }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="#">
                    <img src="icons/logo.png" alt="Logo Thar'z" class="img-fluid me-2">
                    <h1 class="h5 mb-0 text-white">Thar'z Computer</h1>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="#layanan-kami">Layanan Kami</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#tentang-kami">Tentang Thar'z Computer</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#mengapa-memilih-kami">Mengapa Memilih Kami?</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#hubungi-kami">Hubungi Kami</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-light ms-lg-3" href="login.php">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header> 

    <main>
        <section class="hero-section">
            <h1 class="display-4">Selamat Datang<br>Di Website Thar'z Computer</h1>
            <p class="lead">Solusi terdepan untuk kebutuhan servis dan komponen Laptop Anda.</p>
            
            <div class="hero-menu-container" id="layanan-kami">
                <div class="container">
                    <div class="row row-cols-1 row-cols-md-3 g-4 justify-content-center">
                        <div class="col">
                            <a href="service.php" class="box-link">
                                <i class="fas fa-tools"></i>
                                <div class="menu-title">Service</div>
                                <div class="menu-description">Ajukan perbaikan atau perawatan perangkat Anda dengan mudah.</div>
                            </a>
                        </div>
                        <div class="col">
                            <a href="tracking.php" class="box-link">
                                <i class="fas fa-search-location"></i>
                                <div class="menu-title">Tracking</div>
                                <div class="menu-description">Lacak status perbaikan perangkat Anda secara real-time.</div>
                            </a>
                        </div>
                        <div class="col">
                            <a href="barang.php" class="box-link">
                                <i class="fas fa-microchip"></i>
                                <div class="menu-title">Pembelian Sparepart</div>
                                <div class="menu-description">Lihat dan beli komponen komputer berkualitas tinggi yang kami sediakan.</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-section section-padding" id="tentang-kami">
            <div class="container">
                <h2 class="section-heading">Tentang Thar'z Computer</h2>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <p class="lead mb-4">
                            Thar'z Computer adalah UMKM yang didirikan pada awal Mei 2024 dan berlokasi di Jl. Brigjen Katamso (Pertigaan Ciereng – Nusa Indah – Wera), Kelurahan Dangdeur, Kecamatan/Kabupaten Subang, Jawa Barat. Kami bergerak di bidang jasa servis perangkat elektronik serta penjualan aksesoris komputer.
                        </p>
                        <p class="mb-4">
                            Saat ini, Thar'z Computer memiliki tiga karyawan: Owner, Admin, dan Teknisi. Pelanggan kami umumnya datang untuk memperbaiki perangkat elektronik, membeli sparepart, atau sekadar berkonsultasi mengenai masalah perangkat mereka.
                        </p>
                        <p class="mb-4">
                            Dalam operasionalnya, kami menghadapi beberapa kendala seperti kesulitan mengelola antrean servis, pencatatan stok barang yang masih manual, dan komunikasi yang kurang efektif dengan pelanggan. Hal ini sering menyebabkan pelanggan harus datang langsung ke konter untuk menanyakan status servis, yang dapat mengganggu efisiensi kerja teknisi dan admin.
                        </p>
                        <p class="mb-0">
                            Untuk meningkatkan layanan dan mengatasi kendala tersebut, kami bekerja sama dengan tim pengembangan untuk membangun sistem tracking servis berbasis web. Sistem ini bertujuan untuk meningkatkan efisiensi layanan, mengoptimalkan manajemen stok, serta mempermudah transaksi dan pencatatan keuangan, sekaligus meningkatkan transparansi informasi kepada pelanggan.
                            Selain itu, kami juga telah menjalin kerja sama dengan V-GEN dan ROBOT sebagai distributor produk tertentu, seperti sparepart servis dan aksesoris komputer, untuk memastikan ketersediaan komponen berkualitas.
                        </p>
                    </div>
                </div>
            </div>
        </section>


        <section class="why-choose-us-section section-padding" id="mengapa-memilih-kami">
            <div class="container">
                <h2 class="section-heading">Mengapa Memilih Kami?</h2>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100 p-4 shadow-sm border-0">
                            <div class="card-body">
                                <i class="fas fa-award feature-icon mb-3"></i>
                                <h5 class="card-title fw-bold">Profesional & Berpengalaman</h5>
                                <p class="card-text text-muted">Tim teknisi kami ahli di bidangnya, memastikan perangkat Anda ditangani dengan tepat.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 p-4 shadow-sm border-0">
                            <div class="card-body">
                                <i class="fas fa-cogs feature-icon mb-3"></i>
                                <h5 class="card-title fw-bold">Kualitas Sparepart Terjamin</h5>
                                <p class="card-text text-muted">Kami hanya menggunakan sparepart original atau setara dengan garansi.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 p-4 shadow-sm border-0">
                            <div class="card-body">
                                <i class="fas fa-handshake feature-icon mb-3"></i>
                                <h5 class="card-title fw-bold">Layanan Transparan</h5>
                                <p class="card-text text-muted">Anda akan mendapatkan update status service dan estimasi biaya yang jelas.</p>
                            </div>
                        </div>
                    </div> 
                </div>
            </div>
        </section>
        
        <section class="contact-section section-padding" id="hubungi-kami">
            <div class="container">
                <h2 class="section-heading">Hubungi Kami</h2>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <p class="mb-4 lead">
                            Ada pertanyaan atau butuh bantuan? Jangan ragu untuk menghubungi tim kami!
                        </p>
                        <div class="contact-info">
                            <p><i class="fas fa-map-marker-alt"></i> Jl. Brigjen Katamso (Pertigaan Ciereng – Nusa Indah – Wera), Kelurahan Dangdeur, Kecamatan/Kabupaten Subang, Jawa Barat.</p>
                            <p><i class="fas fa-phone"></i> (0231) 123456</p>
                            <p><i class="fab fa-whatsapp"></i> +62 812-3456-7890</p>
                            <p><i class="fas fa-envelope"></i> info@tharzcomputer.com</p>
                            <p><i class="fas fa-clock"></i> Senin - Sabtu: 09.00 - 18.00 WIB</p>
                        </div>
                        <div class="mt-4">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3963.864239828237!2d107.76615527476839!3d-6.538747493453881!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e693b723153c519%3A0x5a31c52184408169!2sJl.%20Brigjen%20Katamso%2C%20Dangdeur%2C%20Kec.%20Subang%2C%20Kabupaten%20Subang%2C%20Jawa%20Barat%2041211!5e0!3m2!1sid!2sid!4v1717822989182!5m2!1sid!2sid" 
                                width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p class="mb-0">© 2025 Thar'z Computer. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>