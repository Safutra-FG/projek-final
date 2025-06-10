<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Inisialisasi variabel untuk pesan
$message = '';

// Proses data pembayaran
if (isset($_POST['submit_pembayaran'])) {
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $total_harga = $_POST['total_harga'];
    $estimasi_waktu = $_POST['estimasi_waktu'];

    // Validasi sederhana untuk mencegah SQL Injection (Sangat disarankan menggunakan Prepared Statements di produksi!)
    $metode_pembayaran = $koneksi->real_escape_string($metode_pembayaran);
    $total_harga = $koneksi->real_escape_string($total_harga);
    $estimasi_waktu = $koneksi->real_escape_string($estimasi_waktu);

    // Simpan ke database
    $sql = "INSERT INTO pembayaran (metode, total_harga, estimasi_waktu)
            VALUES ('$metode_pembayaran', '$total_harga', '$estimasi_waktu')";

    if ($koneksi->query($sql) === TRUE) {
        $message = "Pembayaran berhasil diproses!";
    } else {
        $message = "Error: " . $sql . "<br>" . $koneksi->error;
    }
}

// Dummy data untuk bagian "Sparepart yang digunakan"
// Dalam aplikasi nyata, data ini akan diambil dari database berdasarkan servis yang sedang diproses
$sparepart_digunakan = [
    ['nama' => 'RAM DDR4 8GB', 'harga' => 'Rp 500.000'],
    ['nama' => 'SSD 256GB', 'harga' => 'Rp 350.000'],
    ['nama' => 'Thermal Paste Arctic MX-4', 'harga' => 'Rp 75.000']
];

$namaAkun = "Customer"; // Ganti dengan data user yang login jika ada sistem login
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Service - Thar'z Computer</title>
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
            justify-content: space-between;
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
            background-color: #0d6efd; /* Warna biru Bootstrap */
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
            background-color: #0b5ed7;
        }
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .sparepart-item {
            padding: 8px 0;
            border-bottom: 1px dashed #ddd;
            display: flex;
            justify-content: space-between;
        }
        .sparepart-item:last-child {
            border-bottom: none;
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
            <img src="icons/logo.png" alt="logo Thar'z Computer" class="logo-img">
            <h1 class="h4 text-dark mt-2 fw-bold">THAR'Z COMPUTER</h1>
            <p class="text-muted small">Customer Panel</p>
            <div class="logo-line"></div>
        </div>

        <h2 class="h5 mb-3 text-dark">Menu</h2>
        <div class="menu-line"></div>
        <ul class="nav flex-column menu">
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-desktop"></i>Pengajuan Service
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="#">
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
            <h2 class="h4 text-dark mb-0">Pembayaran Service</h2>
            <div class="d-flex align-items-center">
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" title="Pemberitahuan">
                    <i class="fas fa-bell"></i>
                </button>
                <span class="text-dark fw-semibold ms-2 me-2">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($namaAkun); ?>
                </span>
            </div>
        </div>

        <div class="flex-grow-1 p-3">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, 'berhasil') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Detail Biaya dan Estimasi</h5>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Sparepart yang digunakan dan harganya:</label>
                            <div class="sparepart-list">
                                <?php foreach ($sparepart_digunakan as $item): ?>
                                    <div class="sparepart-item">
                                        <span>- <?php echo htmlspecialchars($item['nama']); ?></span>
                                        <span><?php echo htmlspecialchars($item['harga']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3 row align-items-center">
                            <label for="total_harga" class="col-sm-4 col-form-label">Estimasi Harga:</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <span class="input-group-text">Rp.</span>
                                    <input type="text" class="form-control" id="total_harga" name="total_harga" placeholder="Masukkan total harga" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 row align-items-center">
                            <label for="estimasi_waktu" class="col-sm-4 col-form-label">Estimasi Waktu:</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="estimasi_waktu" name="estimasi_waktu" placeholder="Contoh: 2" required>
                                    <span class="input-group-text">Hari</span>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Metode Pembayaran:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="dp" name="metode_pembayaran" value="DP" checked>
                                <label class="form-check-label" for="dp">DP (Down Payment)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="lunas" name="metode_pembayaran" value="Lunas">
                                <label class="form-check-label" for="lunas">Lunas</label>
                            </div>
                        </div>

                        <button type="submit" name="submit_pembayaran" class="btn-submit w-100">Proses Pembayaran</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Informasi Pelanggan (Buyer)</h5>
                    <p class="text-muted">Informasi ini akan terisi secara otomatis atau dapat diisi oleh pelanggan.</p>
                    <p><strong>Nama Pelanggan:</strong> [Nama Pelanggan]</p>
                    <p><strong>Alamat:</strong> [Alamat Pelanggan]</p>
                    <p><strong>Telepon:</strong> [Nomor Telepon Pelanggan]</p>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>