<?php
// laporan_pesanan.php
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();
// Logika otentikasi sederhana (opsional, untuk produksi gunakan yang lebih kuat)
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
//     header("Location: ../login.php");
//     exit();
// }

$namaAkun = "Owner";

// Inisialisasi filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// --- Ambil data semua servis dari database ---
$dataSemuaServis = [];

$where_clause = "WHERE 1=1"; // Kondisi awal yang selalu benar

if ($filter_status != '' && $filter_status != 'Semua') {
    $where_clause .= " AND s.status = '" . $koneksi->real_escape_string($filter_status) . "'";
}

if ($start_date && $end_date) {
    $where_clause .= " AND DATE(s.tanggal) BETWEEN '" . $koneksi->real_escape_string($start_date) . "' AND '" . $koneksi->real_escape_string($end_date) . "'";
} elseif ($start_date) {
    $where_clause .= " AND DATE(s.tanggal) >= '" . $koneksi->real_escape_string($start_date) . "'";
} elseif ($end_date) {
    $where_clause .= " AND DATE(s.tanggal) <= '" . $koneksi->real_escape_string($end_date) . "'";
}

$sqlSemuaServis = "SELECT
                        s.id_service,
                        c.nama_customer,
                        s.device,
                        s.keluhan,
                        s.status,
                        s.tanggal,
                        s.tanggal_selesai
                      FROM
                        service s
                      JOIN
                        customer c ON s.id_customer = c.id_customer
                      $where_clause
                      ORDER BY
                        s.tanggal DESC, s.id_service DESC";
$resultSemuaServis = $koneksi->query($sqlSemuaServis);

if ($resultSemuaServis && $resultSemuaServis->num_rows > 0) {
    while ($row = $resultSemuaServis->fetch_assoc()) {
        $dataSemuaServis[] = $row;
    }
}

$koneksi->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pesanan - Thraz Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            font-family: sans-serif;
            min-height: 100vh;
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
                <a class="nav-link" href="dashboard.php">
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
                <a class="nav-link active" aria-current="page" href="laporan_pesanan.php">
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
            <h2 class="h4 text-dark mb-0">Laporan Pesanan (Servis)</h2> <div class="d-flex align-items-center">
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
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Filter Laporan Pesanan</h5>
                    <form method="GET" action="laporan_pesanan.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4 col-lg-3">
                                <label for="status_filter" class="form-label">Status Servis:</label>
                                <select class="form-select" id="status_filter" name="status">
                                    <option value="Semua" <?php echo ($filter_status == 'Semua' ? 'selected' : ''); ?>>Semua</option>
                                    <option value="Dalam Proses" <?php echo ($filter_status == 'Dalam Proses' ? 'selected' : ''); ?>>Dalam Proses</option>
                                    <option value="Menunggu Sparepart" <?php echo ($filter_status == 'Menunggu Sparepart' ? 'selected' : ''); ?>>Menunggu Sparepart</option>
                                    <option value="Selesai" <?php echo ($filter_status == 'Selesai' ? 'selected' : ''); ?>>Selesai</option>
                                    <option value="Dibatalkan" <?php echo ($filter_status == 'Dibatalkan' ? 'selected' : ''); ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="start_date" class="form-label">Tanggal Mulai (Masuk):</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="end_date" class="form-label">Tanggal Akhir (Masuk):</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <a href="laporan_pesanan.php" class="btn btn-outline-secondary w-100">Reset Filter</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3 text-dark">Daftar Pesanan Servis</h2>
                    <p class="card-subtitle text-muted mb-4">Menampilkan daftar servis berdasarkan filter yang dipilih.</p>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID Servis</th>
                                    <th scope="col">Pelanggan</th>
                                    <th scope="col">Device</th>
                                    <th scope="col">Keluhan</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Tanggal Masuk</th>
                                    <th scope="col">Tanggal Selesai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dataSemuaServis)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Tidak ada data pesanan servis dengan filter ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataSemuaServis as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['id_service']); ?></td>
                                            <td><?php echo htmlspecialchars($service['nama_customer']); ?></td>
                                            <td><?php echo htmlspecialchars($service['device']); ?></td>
                                            <td><?php echo htmlspecialchars($service['keluhan']); ?></td>
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
                                            <td><?php echo htmlspecialchars($service['tanggal_selesai'] ? $service['tanggal_selesai'] : '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <p class="lead text-muted">Gunakan filter untuk menemukan pesanan servis yang Anda cari.</p>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>