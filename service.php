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

// Dummy data untuk nama akun
$namaAkun = "Customer"; // Anda bisa menggantinya dengan data dari sesi login jika ada
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
            font-family: sans-serif;
            min-height: 100vh;
            background-color: #f8f9fa; /* Background color seperti template laporan keuangan */
        }
        .sidebar {
            width: 250px;
            background-color: #f8f9fa;
            padding: 20px;
            border-right: 1px solid #dee2e6;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .sidebar .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid #0d6efd;
        }
        .sidebar .logo-line,
        .sidebar .menu-line {
            width: 100%;
            height: 1px;
            background-color: #adb5bd;
            margin: 10px 0;
        }
        .sidebar .nav-link {
            padding: 10px 15px;
            color: #495057;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #007bff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .main-header {
            display: flex;
            justify-content: space-between; /* Menjaga agar elemen di kiri dan kanan terpisah */
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        /* Style untuk bagian branding di header utama */
        .main-header .branding {
            display: flex;
            align-items: center;
        }
        .main-header .branding .logo-img-header {
            width: 40px; /* Ukuran logo di header utama */
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #0d6efd;
        }
        .main-header .branding .company-name-header {
            font-size: 1.5rem; /* Ukuran teks nama perusahaan di header utama */
            font-weight: bold;
            color: #212529;
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
            background-color: #28a745; /* Warna hijau Bootstrap */
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
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .main-header .d-flex {
                width: 100%;
                justify-content: space-between;
            }
            .main-header .btn {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo text-center mb-4">
            <img src="../icons/logo.png" alt="logo Thar'z Computer" class="logo-img">
            <h1 class="h4 text-dark mt-2 fw-bold">THAR'Z COMPUTER</h1>
            <p class="text-muted small">Customer Panel</p>
            <div class="logo-line"></div>
        </div>

        <h2 class="h5 mb-3 text-dark">Menu</h2>
        <div class="menu-line"></div>
        <ul class="nav flex-column menu">
            <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="#">
                    <i class="fas fa-desktop"></i>Pengajuan Service
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pembayaran.php">
                    <i class="fas fa-cash-register"></i>Pembayaran
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-exclamation-circle"></i>Keluhan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-info-circle"></i>Keluhan yang Dijelaskan
                </a>
            </li>
        </ul>

        <div class="mt-auto p-4 border-top text-center text-muted small">
            &copy; Tharz Computer 2025
        </div>
    </div>

    <div class="main-content">
        <div class="main-header">
            <div class="branding">
                <img src="../icons/logo.png" alt="logo Thar'z Computer" class="logo-img-header">
                <span class="company-name-header">THAR'Z COMPUTER</span>
            </div>
            <div class="d-flex align-items-center">
                <h2 class="h4 text-dark mb-0 me-3">Pengajuan Service</h2> <button type="button" class="btn btn-outline-secondary btn-sm me-2" title="Pemberitahuan">
                    <i class="fas fa-bell"></i>
                </button>
                <span class="text-dark fw-semibold">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($namaAkun); ?>
                </span>
            </div>
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

            <div class="text-center mt-5">
                <a href="index.php" class="text-primary hover-underline fw-bold transition duration-200">&larr; Kembali ke Beranda</a>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body bg-light">
                    <h5 class="card-title mb-3">Informasi Penting</h5>
                    <p class="text-muted text-sm">Estimasi biaya akan diberikan setelah teknisi kami memeriksa perangkat Anda. Kami akan menghubungi Anda untuk konfirmasi sebelum melakukan perbaikan.</p>
                    <p class="text-muted text-sm">Pastikan data yang Anda masukkan sudah benar untuk kelancaran proses service.</p>
                </div>
            </div>

        </div>
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