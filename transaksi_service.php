<?php
// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tharz_computer');

// Inisialisasi variabel
$koneksi = null;
$id_service_input = null;
$amount_to_pay = null;
$service_data = null;
$error_message = null;
$namaAkun = "Customer"; // Default account name for this page

// Fungsi untuk membuat koneksi database
function connect_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // Catat error koneksi ke log server
        error_log("Koneksi database gagal: " . $conn->connect_error);
        // Tampilkan pesan error umum kepada pengguna
        die("Terjadi masalah koneksi database. Silakan coba beberapa saat lagi atau hubungi administrator.");
    }
    return $conn;
}

// 1. Ambil dan validasi parameter dari URL
if (isset($_GET['id_service']) && isset($_GET['amount'])) {
    $id_service_input = trim($_GET['id_service']);
    // Validasi jumlah tagihan sebagai float, pastikan tidak negatif
    $amount_to_pay = filter_var($_GET['amount'], FILTER_VALIDATE_FLOAT);

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong atau tidak valid.";
    } elseif ($amount_to_pay === false || $amount_to_pay < 0) {
        $error_message = "Jumlah tagihan tidak valid.";
    } else {
        $koneksi = connect_db(); // Buat koneksi database

        // 2. Ambil data service dan customer
        $sql = "SELECT s.id_service, s.device, c.nama_customer
                FROM service s
                JOIN customer c ON s.id_customer = c.id_customer
                WHERE s.id_service = ?";
        $stmt = $koneksi->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $id_service_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $service_data = $result->fetch_assoc();
            } else {
                $error_message = "Data service dengan ID '" . htmlspecialchars($id_service_input) . "' tidak ditemukan.";
            }
            $stmt->close();
        } else {
            // Catat error persiapan query ke log server
            error_log("Gagal menyiapkan query: " . $koneksi->error);
            $error_message = "Terjadi kesalahan dalam mengambil data service. Silakan coba lagi nanti.";
        }
        $koneksi->close(); // Tutup koneksi setelah selesai
    }
} else {
    $error_message = "Informasi ID Service atau jumlah tagihan tidak lengkap. Silakan kembali ke halaman tracking dan coba lagi.";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instruksi Pembayaran - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            justify-content: center;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .total-tagihan-display {
            font-size: 1.25em;
            font-weight: bold;
            color: #dc3545;
        }
        .label-summary {
            min-width: 140px;
            display: inline-block;
            font-weight: 500;
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
                    <a class="nav-link" href="pengajuan_service.php">
                        <i class="fas fa-desktop"></i>Pengajuan Service
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="pembayaran.php">
                        <i class="fas fa-cash-register"></i>Pembayaran
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
        <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Instruksi Pembayaran Service</h2>
    </div>

    <div class="flex-grow-1 p-3">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <div class="text-center mt-4">
                <?php if ($id_service_input && !$service_data): // Error karena ID tidak ditemukan, tapi ID ada ?>
                    <a href="tracking.php?id_service=<?php echo htmlspecialchars($id_service_input); ?>" class="btn btn-primary"><i class="bi bi-arrow-left-circle"></i> Coba Lagi / Cek Status</a>
                <?php else: // Error umum atau parameter tidak lengkap ?>
                    <a href="tracking.php" class="btn btn-primary"><i class="bi bi-search"></i> Kembali ke Halaman Tracking</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>
        <?php elseif ($service_data && $amount_to_pay !== null): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <h4 class="my-0 fw-normal">Ringkasan Tagihan</h4>
                </div>
                <div class="card-body">
                    <p class="card-text fs-5"><span class="label-summary">ID Service:</span> <?php echo htmlspecialchars($service_data['id_service']); ?></p>
                    <p class="card-text fs-5"><span class="label-summary">Nama Customer:</span> <?php echo htmlspecialchars($service_data['nama_customer']); ?></p>
                    <p class="card-text fs-5"><span class="label-summary">Perangkat:</span> <?php echo htmlspecialchars($service_data['device']); ?></p>
                    <hr>
                    <p class="card-text fs-5"><span class="label-summary">Total Tagihan:</span> <span class="total-tagihan-display">Rp <?php echo number_format($amount_to_pay, 0, ',', '.'); ?></span></p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="my-0"><i class="bi bi-credit-card"></i> Metode Pembayaran</h4>
                </div>
                <div class="card-body p-lg-4">
                    <p class="text-center lead mb-4">Silakan selesaikan pembayaran Anda melalui salah satu metode berikut:</p>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-shop"></i> 1. Bayar Langsung di Toko</h3>
                                <p>Anda dapat melakukan pembayaran secara tunai atau metode lain yang tersedia di toko kami:</p>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><strong class="d-block">Alamat Toko:</strong><strong>[Isi Alamat Toko Anda Disini]</strong></li>
                                    <li class="mb-2"><strong class="d-block">Jam Operasional:</strong>[Isi Jam Operasional, misal: 09:00 - 20:00 WIB]</li>
                                    <li>Harap informasikan **ID Service (<?php echo htmlspecialchars($service_data['id_service']); ?>)** kepada staf kami.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 h-100">
                                <h3 class="mb-3"><i class="bi bi-bank"></i> 2. Transfer Bank / E-Wallet Manual</h3>
                                <p>Lakukan transfer ke salah satu rekening resmi berikut:</p>
                                <ul class="list-unstyled">
                                    <li class="mb-2"><strong>Bank BCA</strong>
                                        <ul class="list-unstyled ps-3">
                                            <li>No. Rekening: **123-456-7890**</li>
                                            <li>Atas Nama: **Tharz Computer**</li>
                                        </ul>
                                    </li>
                                    <li class="mb-2"><strong>Dana (E-Wallet)</strong>
                                        <ul class="list-unstyled ps-3">
                                            <li>No. Dana: **0812-3456-7890**</li>
                                            <li>Atas Nama: **Tharz Computer**</li>
                                        </ul>
                                    </li>
                                    <li class="mb-2"><strong>Bank Mandiri</strong>
                                        <ul class="list-unstyled ps-3">
                                            <li>No. Rekening: **987-654-3210**</li>
                                            <li>Atas Nama: **Tharz Computer**</li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4" role="alert">
                        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Penting: Konfirmasi Pembayaran!</h4>
                        <p>Setelah melakukan transfer, mohon segera lakukan konfirmasi pembayaran dengan mengirimkan **bukti transfer** Anda beserta informasi berikut:</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-patch-check-fill text-success"></i> ID Service: **<?php echo htmlspecialchars($service_data['id_service']); ?>**</li>
                            <li><i class="bi bi-person-fill text-primary"></i> Nama Pengirim (sesuai rekening bank/e-wallet):</li>
                            <li><i class="bi bi-wallet-fill text-info"></i> Jumlah Transfer: Rp <?php echo number_format($amount_to_pay, 0, ',', '.'); ?></li>
                        </ul>
                        <hr>
                        <p class="mb-1">Konfirmasi dapat dikirimkan melalui salah satu kontak berikut:</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-whatsapp text-success"></i> WhatsApp: **+62 812-3456-7890**</li>
                            <li><i class="bi bi-envelope-at-fill text-danger"></i> Email: **info@tharzcomputer.com**</li>
                        </ul>
                    </div>
                    <p class="mt-3 fst-italic text-center text-muted">Service Anda akan kami proses/siapkan untuk pengambilan setelah pembayaran Anda berhasil diverifikasi oleh tim kami.</p>
                </div>
            </div>

            <div class="text-center mt-4 py-4 border-top">
                <a href="tracking.php?id_service=<?php echo htmlspecialchars($id_service_input); ?>" class="btn btn-primary btn-lg"><i class="bi bi-eye"></i> Lihat Status Service Saya</a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Data instruksi pembayaran tidak dapat ditampilkan. Silakan coba lagi dari halaman tracking.
            </div>
            <div class="text-center mt-3">
                <a href="tracking.php" class="btn btn-primary"><i class="bi bi-search"></i> Kembali ke Halaman Tracking</a>
                <a href="index.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-house"></i> Kembali ke Beranda</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="mt-auto p-4 border-top text-center text-muted small">
        <p>&copy; <?php echo date("Y"); ?> Thar'z Computer. All rights reserved.</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>