<?php
include 'koneksi.php'; // koneksi ke DB ente

// Inisialisasi variabel untuk pesan alert
$alert_message = '';
$alert_type = ''; // 'success' atau 'danger'

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
        $insertService = mysqli_query($koneksi, "INSERT INTO service (id_customer, tanggal, device, keluhan)
                                                 VALUES ('$id_customer', '$tanggal', '$device', '$keluhan')");
        $id_service = mysqli_insert_id($koneksi); // Ambil ID service yang baru saja di-insert

        if ($insertService) {
            $alert_message = "Data berhasil diajukan! ID Service kamu: #$id_service";
            $alert_type = 'success';
        } else {
            $alert_message = "Gagal input service: " . mysqli_error($koneksi);
            $alert_type = 'danger';
            // Rollback customer insertion if service insertion fails (opsional, untuk konsistensi data)
            // mysqli_query($koneksi, "DELETE FROM customer WHERE id_customer = '$id_customer'");
        }
    } else {
        $alert_message = "Gagal input customer: " . mysqli_error($koneksi);
        $alert_type = 'danger';
    }
}

// Dummy data untuk nama akun (tidak lagi digunakan di tampilan, tapi tetap ada jika Anda ingin menggunakannya di tempat lain)
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
            justify-content: center; /* Mengubah ini untuk memusatkan konten di header */
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
                align-items: flex-start; /* Tetap flex-start untuk mobile jika ada elemen lain selain judul */
                gap: 15px;
            }
            /* Jika hanya ada judul di main-header saat mobile, bisa di-centerkan juga */
            .main-header h2 {
                width: 100%; /* Pastikan elemen mengambil seluruh lebar */
                text-align: center; /* Tengah teks */
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

    <div class="flex-grow-1 p-3">
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
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama lengkap" required>
                    </div>

                    <div class="mb-3">
                        <label for="nomor_telepon" class="form-label">Nomor Handphone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nomor_telepon" name="nomor_telepon" pattern="\d{10,12}" maxlength="12" placeholder="Contoh: 081234567890" required>
                        <div class="form-text">* Nomor harus 10-12 digit angka</div>
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