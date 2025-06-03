<?php
// dashboard.php (untuk peran Owner)
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();
// Logika otentikasi sederhana (opsional, untuk produksi gunakan yang lebih kuat)
// Jika Anda memiliki sistem role-based access, pastikan user_role adalah 'owner'
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
//     header("Location: ../login.php");
//     exit();
// }

$namaAkun = "Owner"; // Mengatur nama akun sebagai Owner

// --- Ambil data statistik dari database ---
$totalServisHariIni = 0;
$servisDalamProses = 0;
$servisMenungguSparepart = 0;
$servisSelesaiHariIni = 0;
$totalEstimasiPendapatanHariIni = 0;

$today = date("Y-m-d"); // Tanggal hari ini

// Query untuk total servis hari ini
$sqlTotal = "SELECT COUNT(*) AS total FROM service WHERE DATE(tanggal) = '$today'";
$resultTotal = $koneksi->query($sqlTotal);
if ($resultTotal && $resultTotal->num_rows > 0) {
    $row = $resultTotal->fetch_assoc();
    $totalServisHariIni = $row['total'];
}

// Query untuk servis dalam proses
$sqlDalamProses = "SELECT COUNT(*) AS total FROM service WHERE status = 'Dalam Proses'";
$resultDalamProses = $koneksi->query($sqlDalamProses);
if ($resultDalamProses && $resultDalamProses->num_rows > 0) {
    $row = $resultDalamProses->fetch_assoc();
    $servisDalamProses = $row['total'];
}

// Query untuk servis menunggu sparepart
$sqlMenungguSparepart = "SELECT COUNT(*) AS total FROM service WHERE status = 'Menunggu Sparepart'";
$resultMenungguSparepart = $koneksi->query($sqlMenungguSparepart);
if ($resultMenungguSparepart && $resultMenungguSparepart->num_rows > 0) {
    $row = $resultMenungguSparepart->fetch_assoc();
    $servisMenungguSparepart = $row['total'];
}

// Query untuk servis selesai hari ini
$sqlSelesaiHariIni = "SELECT COUNT(*) AS total FROM service WHERE status = 'Selesai' AND DATE(tanggal_selesai) = '$today'";
$resultSelesaiHariIni = $koneksi->query($sqlSelesaiHariIni);
if ($resultSelesaiHariIni && $resultSelesaiHariIni->num_rows > 0) {
    $row = $resultSelesaiHariIni->fetch_assoc();
    $servisSelesaiHariIni = $row['total'];
}

// Query untuk estimasi pendapatan hari ini
$sqlEstimasiPendapatanHariIni = "SELECT SUM(estimasi_harga) AS total_estimasi_pendapatan FROM service WHERE status = 'Selesai' AND DATE(tanggal_selesai) = '$today'";
$resultEstimasiPendapatanHariIni = $koneksi->query($sqlEstimasiPendapatanHariIni);
if ($resultEstimasiPendapatanHariIni && $resultEstimasiPendapatanHariIni->num_rows > 0) {
    $row = $resultEstimasiPendapatanHariIni->fetch_assoc();
    $totalEstimasiPendapatanHariIni = $row['total_estimasi_pendapatan'];
}

// --- Ambil data servis terbaru dari database ---
$latestServices = [];
$sqlLatestServices = "SELECT
                            s.id_service,
                            c.nama_customer,
                            s.device,
                            s.status,
                            s.tanggal
                          FROM
                            service s
                          JOIN
                            customer c ON s.id_customer = c.id_customer
                          ORDER BY
                            s.tanggal DESC, s.id_service DESC
                          LIMIT 5";
$resultLatestServices = $koneksi->query($sqlLatestServices);

if ($resultLatestServices && $resultLatestServices->num_rows > 0) {
    while ($row = $resultLatestServices->fetch_assoc()) {
        $latestServices[] = $row;
    }
}

$koneksi->close(); // Tutup koneksi database
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Owner - Thraz Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom CSS untuk mempertahankan beberapa gaya asli atau menyesuaikan Bootstrap */
        body {
            display: flex;
            font-family: sans-serif;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #f8f9fa; /* Warna latar belakang sidebar Bootstrap */
            padding: 20px;
            border-right: 1px solid #dee2e6; /* Border kanan Bootstrap */
            display: flex;
            flex-direction: column;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); /* Shadow ringan */
        }

        .sidebar .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid #0d6efd; /* Border biru Bootstrap primary, disesuaikan untuk logo */
        }

        .sidebar .logo-line,
        .sidebar .menu-line {
            width: 100%;
            height: 1px;
            background-color: #adb5bd; /* Warna garis Bootstrap */
            margin: 10px 0;
        }

        /* Styling untuk menu sidebar Owner */
        .sidebar .nav-link {
            padding: 10px 15px; /* Padding untuk area klik */
            color: #495057; /* Warna teks lebih gelap dari default nav-link */
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex; /* Untuk ikon sejajar dengan teks */
            align-items: center;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #e9ecef; /* Latar belakang hover/active */
            color: #007bff; /* Warna teks primary Bootstrap */
        }

        .sidebar .nav-link i {
            margin-right: 10px; /* Spasi antara ikon dan teks */
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

        /* Gaya untuk card statistik */
        .card-statistic {
            background-color: #fff;
            padding: 24px;
            border-radius: 0.75rem;
            text-align: center;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease-in-out;
            border: 1px solid rgba(0, 0, 0, 0.125);
        }

        .card-statistic:hover {
            transform: translateY(-5px);
        }

        .card-statistic h3 {
            margin-top: 0;
            color: #6c757d;
            font-size: 1.125rem;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .card-statistic p {
            font-size: 2.5em;
            font-weight: bold;
            color: #212529;
        }

        /* Warna spesifik untuk card statistik */
        .card-blue { background-color: #e0f7fa; color: #007bbd; }
        .card-yellow { background-color: #fffde7; color: #ffb300; }
        .card-purple { background-color: #f3e5f5; color: #9c27b0; }
        .card-green { background-color: #e8f5e9; color: #43a047; }
        .card-indigo { background-color: #e8eaf6; color: #3f51b5; }

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
            <h1 class="h4 text-dark mt-2 fw-bold">Thraz Computer</h1>
            <p class="text-muted small">Owner Panel</p> <div class="logo-line"></div>
        </div>

        <h2 class="h5 mb-3 text-dark">Menu</h2>
        <div class="menu-line"></div>
        <ul class="nav flex-column menu">
            <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="dashboard.php">
                    <i class="fas fa-home"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="register.php">
                    <i class="fas fa-users"></i>Kelola Akun
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="stok.php">
                    <i class="fas fa-wrench"></i>Kelola Sparepart
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="laporan_keuangan.php">
                    <i class="fas fa-chart-line"></i>Laporan Keuangan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="laporan_sparepart.php">
                    <i class="fas fa-boxes"></i>Laporan Sparepart
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="laporan_pesanan.php">
                    <i class="fas fa-clipboard-list"></i>Laporan Pesanan
                </a>
            </li>
        </ul>

        <div class="mt-auto p-4 border-top text-center text-muted small">
            &copy; Tharz Computer 2025
        </div>
    </div>

    <div class="main-content">
        <div class="main-header">
            <h2 class="h4 text-dark mb-0">Dashboard Owner</h2> <div class="d-flex align-items-center">
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
            <h1 class="h2 text-dark mb-4 text-center">Selamat Datang Owner, Pantau Seluruh Operasi!</h1> <div class="row g-4 mb-4">
                <div class="col-12 col-md-6 col-lg-4 col-xl-2dot4">
                    <div class="card-statistic card-blue">
                        <h3>Total Servis Hari Ini</h3>
                        <p class="h1 mb-0"><?php echo $totalServisHariIni; ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4 col-xl-2dot4">
                    <div class="card-statistic card-yellow">
                        <h3>Servis Dalam Proses</h3>
                        <p class="h1 mb-0"><?php echo $servisDalamProses; ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4 col-xl-2dot4">
                    <div class="card-statistic card-purple">
                        <h3>Servis Menunggu Sparepart</h3>
                        <p class="h1 mb-0"><?php echo $servisMenungguSparepart; ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4 col-xl-2dot4">
                    <div class="card-statistic card-green">
                        <h3>Servis Selesai Hari Ini</h3>
                        <p class="h1 mb-0"><?php echo $servisSelesaiHariIni; ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4 col-xl-2dot4">
                    <div class="card-statistic card-indigo">
                        <h3>Estimasi Pendapatan Hari Ini</h3>
                        <p class="h1 mb-0">Rp <?php echo number_format($totalEstimasiPendapatanHariIni, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3 text-dark">Servis Terbaru</h2>
                    <p class="card-subtitle text-muted mb-4">Berikut adalah daftar servis yang baru saja masuk atau diperbarui.</p>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID Servis</th>
                                    <th scope="col">Pelanggan</th>
                                    <th scope="col">Device</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($latestServices)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Tidak ada data servis terbaru.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($latestServices as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['id_service']); ?></td>
                                            <td><?php echo htmlspecialchars($service['nama_customer']); ?></td>
                                            <td><?php echo htmlspecialchars($service['device']); ?></td>
                                            <td>
                                                <?php
                                                    $statusClass = '';
                                                    switch ($service['status']) {
                                                        case 'Dalam Proses':
                                                            $statusClass = 'bg-warning text-dark';
                                                            break;
                                                        case 'Menunggu Sparepart':
                                                            $statusClass = 'bg-info text-dark';
                                                            break;
                                                        case 'Selesai':
                                                            $statusClass = 'bg-success text-white';
                                                            break;
                                                        case 'Dibatalkan':
                                                            $statusClass = 'bg-danger text-white';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-secondary text-white';
                                                            break;
                                                    }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($service['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($service['tanggal']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <p class="lead text-muted">Gunakan menu di samping untuk mengelola data dan operasi secara detail.</p>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>