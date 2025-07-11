<?php
// pengajuan.php

// Mulai session untuk bisa membaca pesan alert
session_start();

// Ambil pesan dari session jika ada
$alert_message = '';
$alert_type = '';
if (isset($_SESSION['alert_message'])) {
    $alert_message = $_SESSION['alert_message'];
    $alert_type = $_SESSION['alert_type'];

    // Hapus pesan dari session agar tidak muncul lagi saat halaman di-refresh
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
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
    <link rel="stylesheet" href="style.css">
    <style>
        /* Anda bisa memindahkan CSS ini ke file style.css terpisah */
        body {
            display: flex;
            flex-direction: column;
            font-family: sans-serif;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #ffffff;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .navbar .logo-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #0d6efd;
        }
        .navbar .nav-link {
            padding: 10px 15px;
            color: #495057;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .navbar .nav-link.active,
        .navbar .nav-link:hover {
            background-color: #e9ecef;
            color: #007bff;
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
            background-color: #28a745;
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
            background-color: #218838;
        }
        .alert-dismissible .btn-close {
            position: absolute;
            right: 0;
            padding: 0.5rem 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Responsive adjustments */
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
                align-items: center; /* Pusat di mobile */
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

<div class="main-content" >
    <div class="main-header">
        <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Pengajuan Service</h2>
    </div>

    <div class="flex-grow-1 p-3">
        <?php if (!empty($alert_message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"> <h5 class="my-0 font-weight-normal">Data Pelanggan & Perangkat</h5> </div>
            <div class="card-body">
                <form action="proses/tambah_service.php" method="POST" id="serviceForm">
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama lengkap" required>
                    </div>
                    <div class="mb-3">
                            <label for="nohp" class="form-label">Nomor HP</label>
                            <input type="text" class="form-control" id="nomor_telepon" name="nomor_telepon" pattern="\d{10,12}" maxlength="12" placeholder="Contoh: 081234567890" required value="<?= htmlspecialchars($_POST['nomor_telepon'] ?? '') ?>" 
                                pattern="[0-9]{12,13}" 
                                maxlength="13" 
                                minlength="12"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                required>
                        </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Contoh: nama@email.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="device" class="form-label">Jenis Perangkat <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="device" name="device" placeholder="Contoh: Laptop ASUS ROG" required>
                    </div>

                    <div class="mb-4">
                        <label for="keluhan" class="form-label">Keluhan/Kerusakan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="keluhan" name="keluhan" rows="5" placeholder="Jelaskan keluhan atau kerusakan yang dialami secara detail" required></textarea>
                    </div>

                    <button type="submit" name="submit" class="btn-submit w-100">Ajukan Service</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-body bg-light">
                <h5 class="card-title mb-3">Informasi Penting</h5>
                <p class="text-muted text-sm">Estimasi biaya akan diberikan setelah teknisi kami memeriksa perangkat Anda. Kami akan menghubungi Anda untuk konfirmasi sebelum melakukan perbaikan.</p>
                <p class="text-muted text-sm">Pastikan data yang Anda masukkan sudah benar untuk kelancaran proses service.</p>
            </div>
        </div>

    </div>

    <footer class="mt-auto p-4 border-top text-center text-muted small">
        &copy; Tharz Computer 2025
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("serviceForm").addEventListener("submit", function(e) {
        const noHpInput = document.getElementById('nomor_telepon');
        const emailInput = document.getElementById('email');

        const hpValid = /^\d{10,12}$/.test(noHpInput.value);
        const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);

        if (!hpValid) {
            alert("Nomor handphone harus terdiri dari 10 hingga 12 digit angka.");
            e.preventDefault();
        }

        if (!emailValid) {
            alert("Alamat email tidak valid. Pastikan mengandung '@' dan domain.");
            e.preventDefault();
        }
    });
</script>
</body>
</html>