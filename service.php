<?php
include 'koneksi.php'; // koneksi ke DB ente

// Inisialisasi variabel untuk pesan alert
$alert_message = '';
$alert_type = ''; // 'success' atau 'danger'
$form_submitted_successfully = false; // Flag untuk reset form

if (isset($_POST['submit'])) {
    $nama        = $_POST['nama'];
    $no_hp       = $_POST['nomor_telepon'];
    $email       = $_POST['email'];
    $device      = $_POST['device'];
    $keluhan     = $_POST['keluhan'];

    // Escape string untuk mencegah SQL Injection (sangat direkomendasikan menggunakan Prepared Statements)
    $nama = mysqli_real_escape_string($koneksi, $nama);
    $no_hp = mysqli_real_escape_string($koneksi, $no_hp);
    $email = mysqli_real_escape_string($koneksi, $email);
    $device = mysqli_real_escape_string($koneksi, $device);
    $keluhan = mysqli_real_escape_string($koneksi, $keluhan);

    // 1. Masukin data ke tabel customer
    $insertCustomer = mysqli_query($koneksi, "INSERT INTO customer (nama_customer, no_telepon, email)
                                               VALUES ('$nama', '$no_hp', '$email')");

    if ($insertCustomer) {
        // 2. Ambil ID customer yang barusan dimasukin
        $id_customer = mysqli_insert_id($koneksi);

        // 3. Masukin ke tabel service
        $tanggal = date('Y-m-d');
        // Hapus 'tracking_id' dari query INSERT INTO service
        $insertService = mysqli_query($koneksi, "INSERT INTO service (id_customer, tanggal, device, keluhan)
                                                 VALUES ('$id_customer', '$tanggal', '$device', '$keluhan')");
        $id_service = mysqli_insert_id($koneksi); // Ambil ID service yang baru saja di-insert

        if ($insertService) {
            // Gunakan $id_service untuk pesan sukses
            $alert_message = "Pengajuan berhasil! Nomor Service Anda: <strong>#$id_service</strong>. Harap simpan nomor ini untuk melacak status perbaikan Anda.";
            $alert_type = 'success';
            $form_submitted_successfully = true; // Set flag
        } else {
            $alert_message = "Gagal input service: " . mysqli_error($koneksi);
            $alert_type = 'danger';
            // Rollback customer insertion if service insertion fails (opsional, untuk konsistensi data)
            mysqli_query($koneksi, "DELETE FROM customer WHERE id_customer = '$id_customer'");
        }
    } else {
        $alert_message = "Gagal input customer: " . mysqli_error($koneksi);
        $alert_type = 'danger';
    }
}

// Dummy data untuk nama akun
$namaAkun = "Customer";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Service - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            font-family: sans-serif;
            min-height: 100vh;
            background-color: #f8f9fa; /* Latar belakang umum yang cerah */
        }
        .navbar {
            background-color: #ffffff; /* Navbar tetap putih */
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .navbar .logo-img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        .navbar .company-name-header {
            font-weight: bold;
            font-size: 1.25rem;
            color: #343a40; /* Nama perusahaan hitam/abu-abu gelap */
        }
        .navbar .nav-link {
            padding: 10px 15px;
            color: #495057; /* Warna teks link default abu-abu */
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .navbar .nav-link.active,
        .navbar .nav-link:hover {
            background-color: #e9ecef; /* Background hover abu-abu muda */
            color: #495057; /* Warna teks hover tetap abu-abu atau sedikit lebih gelap */
        }
        .navbar .nav-link i {
            margin-right: 8px;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .main-header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .card {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .btn-submit {
            background-color: #28a745; /* Tombol submit hijau */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.2s ease;
        }
        .btn-submit:hover {
            background-color: #218838; /* Hover tombol submit hijau lebih gelap */
        }
        .alert-dismissible .btn-close {
            position: absolute;
            right: 0;
            padding: 0.5rem 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Custom styles for new sections */
        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .feature-item i {
            font-size: 1.5rem;
            color: #6c757d; /* Ikon fitur kembali ke abu-abu muted */
            margin-right: 15px;
            flex-shrink: 0;
            margin-top: 5px;
        }
        .feature-item .text-content h6 {
            margin-bottom: 5px;
            color: #343a40;
        }
        .feature-item .text-content p {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .faq-item {
            margin-bottom: 10px;
        }
        .faq-item .faq-question {
            font-weight: bold;
            color: #343a40;
            cursor: pointer;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .faq-item .faq-answer {
            display: none;
            padding: 10px 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .faq-item.active .faq-answer {
            display: block;
        }
        .faq-item .faq-question i {
            transition: transform 0.2s ease-in-out;
            color: #6c757d; /* Ikon chevron kembali ke abu-abu muted */
        }
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* Footer styles */
        footer {
            background-color: #ffffff; /* Footer kembali ke putih */
            border-top: 1px solid #dee2e6;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        footer .footer-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px 40px;
            margin-bottom: 15px;
        }
        footer .footer-info div {
            flex-basis: auto;
        }
        footer .footer-info div strong {
            display: block;
            margin-bottom: 5px;
            color: #343a40; /* Judul info footer kembali ke hitam/abu-abu gelap */
            font-size: 1rem;
        }
        footer .footer-info div p {
            margin: 0;
            color: #6c757d; /* Teks paragraf info footer kembali ke abu-abu muted */
        }
        footer .social-icons a {
            color: #6c757d; /* Ikon sosial kembali ke abu-abu muted */
            margin: 0 8px;
            font-size: 1.2rem;
            transition: color 0.2s;
        }
        footer .social-icons a:hover {
            color: #28a745; /* Ikon sosial hover hijau */
        }
        footer p a { /* Link Kebijakan Privasi dll. */
            color: #6c757d !important; /* Teks link kembali ke abu-abu muted */
        }
        footer p a:hover {
            color: #28a745 !important; /* Link hover hijau */
        }

        /* Responsive adjustments (tetap sama) */
        @media (max-width: 768px) {
            .navbar .navbar-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            .navbar .navbar-toggler {
                display: block;
            }
            .navbar .navbar-collapse {
                display: none;
            }
            .navbar .navbar-collapse.show {
                display: flex;
                flex-direction: column;
            }
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .main-header h2 {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="logo Thar'z Computer" class="logo-img">
            <span class="company-name-header">THAR'Z COMPUTER</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="#">
                        <i class="fas fa-desktop"></i>Pengajuan Service
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tracking.php">
                        <i class="fas fa-search-location"></i>Tracking Service
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home"></i>Kembali ke Beranda
                    </a>
                </li>
            </ul>
        </div>

        <div class="d-flex align-items-center">
            <span class="text-dark fw-semibold">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($namaAkun); ?>
            </span>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="main-header">
        <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Pengajuan Service</h2>
    </div>

    <div class="container py-3">
        <?php if (!empty($alert_message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4 pb-2 border-bottom">Data Pelanggan & Perangkat</h5>
                <form method="POST" id="serviceForm">
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama lengkap Anda" required>
                        <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                    </div>

                    <div class="mb-3">
                        <label for="nomor_telepon" class="form-label">Nomor Handphone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nomor_telepon" name="nomor_telepon" pattern="\d{10,12}" maxlength="12" placeholder="Contoh: 081234567890" required>
                        <div class="form-text text-muted">* Nomor harus 10-12 digit angka.</div>
                        <div class="invalid-feedback">Nomor handphone harus 10-12 digit angka.</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Contoh: nama@email.com" required>
                        <div class="invalid-feedback">Alamat email tidak valid.</div>
                    </div>

                    <div class="mb-3">
                        <label for="device" class="form-label">Jenis Perangkat <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="device" name="device" placeholder="Contoh: Laptop ASUS ROG, PC Rakitan, Printer Epson L3110" required>
                        <div class="invalid-feedback">Jenis perangkat wajib diisi.</div>
                    </div>

                    <div class="mb-4">
                        <label for="keluhan" class="form-label">Keluhan/Kerusakan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="keluhan" name="keluhan" rows="5" placeholder="Jelaskan detail masalahnya, contoh: Layar bergaris, tidak bisa menyala, keyboard tidak berfungsi." required></textarea>
                        <div class="invalid-feedback">Detail keluhan wajib diisi.</div>
                    </div>

                    <button type="submit" name="submit" class="btn-submit w-100">Ajukan Service</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-body bg-light">
                <h5 class="card-title mb-3">Informasi Penting</h5>
                <p class="text-muted text-sm mb-2">Estimasi biaya akan diberikan setelah teknisi kami memeriksa perangkat Anda. Kami akan menghubungi Anda untuk konfirmasi sebelum melakukan perbaikan.</p>
                <p class="text-muted text-sm mb-0">Pastikan data yang Anda masukkan sudah benar dan lengkap untuk kelancaran proses service.</p>
                <hr class="my-3">
                <p class="text-muted text-sm mb-0">Untuk pertanyaan lebih lanjut, silakan hubungi kami via WhatsApp: <a href="https://wa.me/6281234567890" target="_blank" class="text-decoration-none"><i class="fab fa-whatsapp"></i> 0812-3456-7890</a></p>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h5 class="card-title mb-4 pb-2 border-bottom text-center">Mengapa Memilih Thar'z Computer?</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="feature-item">
                            <i class="fas fa-tools"></i>
                            <div class="text-content">
                                <h6>Teknisi Berpengalaman & Profesional</h6>
                                <p>Tim teknisi kami telah bersertifikasi dan memiliki pengalaman bertahun-tahun dalam menangani berbagai jenis kerusakan perangkat.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <div class="text-content">
                                <h6>Garansi Perbaikan</h6>
                                <p>Kami memberikan garansi 30 hari untuk setiap perbaikan yang kami lakukan, memberikan Anda ketenangan pikiran.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <div class="text-content">
                                <h6>Proses Cepat & Transparan</h6>
                                <p>Diagnosa cepat dan update status yang transparan agar Anda selalu tahu perkembangan service perangkat Anda.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-item">
                            <i class="fas fa-cogs"></i>
                            <div class="text-content">
                                <h6>Suku Cadang Berkualitas</h6>
                                <p>Hanya menggunakan suku cadang asli dan berkualitas tinggi untuk memastikan performa optimal perangkat Anda.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h5 class="card-title mb-4 pb-2 border-bottom text-center">Pertanyaan Yang Sering Diajukan (FAQ)</h5>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            Bagaimana cara melacak status perbaikan saya? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Anda dapat melacak status perbaikan dengan memasukkan nomor service yang Anda dapatkan setelah mengajukan service di halaman "Tracking Service". Nomor service ini adalah ID unik perbaikan Anda.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            Berapa lama waktu yang dibutuhkan untuk perbaikan? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Waktu perbaikan bervariasi tergantung jenis kerusakan dan ketersediaan suku cadang. Setelah diagnosa, kami akan memberikan estimasi waktu yang lebih akurat.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            Apakah ada biaya diagnosa? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Biaya diagnosa akan dikenakan jika Anda memutuskan untuk tidak melanjutkan perbaikan setelah kami memberikan estimasi biaya. Namun, jika Anda setuju untuk melanjutkan perbaikan, biaya diagnosa akan dihapuskan.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            Metode pembayaran apa saja yang diterima? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Kami menerima pembayaran tunai, transfer bank, dan e-wallet (OVO, GoPay, Dana). Detail lebih lanjut akan diberikan setelah perbaikan selesai.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <footer class="mt-auto">
        <div class="container footer-info">
            <div>
                <strong>Alamat Kami</strong>
                <p>Jl. Perintis Kemerdekaan No. 100</p>
                <p>Tasikmalaya, Jawa Barat 46123</p>
            </div>
            <div>
                <strong>Jam Operasional</strong>
                <p>Senin - Jumat: 09:00 - 18:00 WIB</p>
                <p>Sabtu: 09:00 - 15:00 WIB</p>
                <p>Minggu: Tutup</p>
            </div>
            <div>
                <strong>Hubungi Kami</strong>
                <p>Telepon: (0265) 123456</p>
                <p>Email: info@tharizcomputer.com</p>
                <p><a href="https://wa.me/6281234567890" target="_blank" class="text-decoration-none text-muted"><i class="fab fa-whatsapp"></i> WhatsApp</a></p>
            </div>
        </div>
        <div class="social-icons mb-2">
            <a href="#" target="_blank" title="Facebook"><i class="fab fa-facebook"></i></a>
            <a href="#" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Thar'z Computer. All rights reserved. | <a href="#" class="text-muted text-decoration-none">Kebijakan Privasi</a> | <a href="#" class="text-muted text-decoration-none">Syarat & Ketentuan</a></p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const serviceForm = document.getElementById("serviceForm");
        const namaInput = document.getElementById('nama');
        const noHpInput = document.getElementById('nomor_telepon');
        const emailInput = document.getElementById('email');
        const deviceInput = document.getElementById('device');
        const keluhanInput = document.getElementById('keluhan');

        // Fungsi untuk menambahkan/menghapus kelas invalid
        function setValidationState(inputElement, isValid) {
            if (isValid) {
                inputElement.classList.remove('is-invalid');
                inputElement.classList.add('is-valid');
            } else {
                inputElement.classList.remove('is-valid');
                inputElement.classList.add('is-invalid');
            }
        }

        // Validasi real-time saat input berubah
        namaInput.addEventListener('input', function() {
            setValidationState(namaInput, namaInput.value.trim() !== '');
        });

        noHpInput.addEventListener('input', function() {
            const hpValid = /^\d{10,12}$/.test(noHpInput.value);
            setValidationState(noHpInput, hpValid);
        });

        emailInput.addEventListener('input', function() {
            const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
            setValidationState(emailInput, emailValid);
        });

        deviceInput.addEventListener('input', function() {
            setValidationState(deviceInput, deviceInput.value.trim() !== '');
        });

        keluhanInput.addEventListener('input', function() {
            setValidationState(keluhanInput, keluhanInput.value.trim() !== '');
        });


        // Validasi saat form disubmit
        serviceForm.addEventListener("submit", function(e) {
            let formIsValid = true;

            // Trigger validation for all fields on submit
            setValidationState(namaInput, namaInput.value.trim() !== '');
            if (namaInput.value.trim() === '') formIsValid = false;

            const hpValid = /^\d{10,12}$/.test(noHpInput.value);
            setValidationState(noHpInput, hpValid);
            if (!hpValid) formIsValid = false;

            const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
            setValidationState(emailInput, emailValid);
            if (!emailValid) formIsValid = false;

            setValidationState(deviceInput, deviceInput.value.trim() !== '');
            if (deviceInput.value.trim() === '') formIsValid = false;

            setValidationState(keluhanInput, keluhanInput.value.trim() !== '');
            if (keluhanInput.value.trim() === '') formIsValid = false;

            if (!formIsValid) {
                e.preventDefault(); // Stop form submission if validation fails
                // Optionally scroll to the first invalid field
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        // Reset form jika submit berhasil
        <?php if ($form_submitted_successfully): ?>
            serviceForm.reset();
            // Hapus kelas validasi setelah reset
            namaInput.classList.remove('is-valid', 'is-invalid');
            noHpInput.classList.remove('is-valid', 'is-invalid');
            emailInput.classList.remove('is-valid', 'is-invalid');
            deviceInput.classList.remove('is-valid', 'is-invalid');
            keluhanInput.classList.remove('is-valid', 'is-invalid');
        <?php endif; ?>

        // FAQ Accordion
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            item.querySelector('.faq-question').addEventListener('click', () => {
                // Tutup semua item FAQ yang sedang aktif kecuali yang diklik
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });
                // Toggle kelas 'active' pada item yang diklik
                item.classList.toggle('active');
            });
        });

        // Adjust company name header font size for responsiveness
        function adjustCompanyNameSize() {
            const companyName = document.querySelector('.company-name-header');
            if (window.innerWidth <= 576) { // Bootstrap's 'sm' breakpoint
                companyName.style.fontSize = '1rem';
            } else {
                companyName.style.fontSize = '1.25rem';
            }
        }

        adjustCompanyNameSize(); // Call on load
        window.addEventListener('resize', adjustCompanyNameSize); // Call on resize
    });
</script>
</body>
</html>