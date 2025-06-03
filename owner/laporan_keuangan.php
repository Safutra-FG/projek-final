<?php
// laporan_keuangan.php
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();
// Logika otentikasi sederhana (opsional, untuk produksi gunakan yang lebih kuat)
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
//     header("Location: ../login.php");
//     exit();
// }

$namaAkun = "Owner";

// Inisialisasi filter tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// --- Ambil data keuangan dari database ---
$totalPendapatan = 0;
$dataTransaksi = []; // Bisa berupa pendapatan atau pengeluaran

$where_clause = "WHERE s.status = 'Selesai'";
if ($start_date && $end_date) {
    $where_clause .= " AND DATE(s.tanggal_selesai) BETWEEN '$start_date' AND '$end_date'";
} elseif ($start_date) {
    $where_clause .= " AND DATE(s.tanggal_selesai) >= '$start_date'";
} elseif ($end_date) {
    $where_clause .= " AND DATE(s.tanggal_selesai) <= '$end_date'";
}


// Query untuk total pendapatan
$sqlPendapatan = "SELECT SUM(estimasi_harga) AS total_pendapatan FROM service s $where_clause";
$resultPendapatan = $koneksi->query($sqlPendapatan);
if ($resultPendapatan && $resultPendapatan->num_rows > 0) {
    $row = $resultPendapatan->fetch_assoc();
    $totalPendapatan = $row['total_pendapatan'];
}

// Query untuk detail transaksi (servis selesai sebagai pendapatan)
$sqlTransaksi = "SELECT
                    s.id_service AS id_transaksi,
                    c.nama_customer AS deskripsi,
                    s.estimasi_harga AS jumlah,
                    s.tanggal_selesai AS tanggal_transaksi,
                    'Pendapatan Servis' AS jenis_transaksi
                  FROM
                    service s
                  JOIN
                    customer c ON s.id_customer = c.id_customer
                  $where_clause
                  ORDER BY
                    s.tanggal_selesai DESC";

$resultTransaksi = $koneksi->query($sqlTransaksi);
if ($resultTransaksi && $resultTransaksi->num_rows > 0) {
    while ($row = $resultTransaksi->fetch_assoc()) {
        $dataTransaksi[] = $row;
    }
}

// Tambahkan data pengeluaran jika Anda punya tabel pengeluaran terpisah
// Contoh:
// $sqlPengeluaran = "SELECT id_pengeluaran AS id_transaksi, deskripsi, jumlah, tanggal_pengeluaran AS tanggal_transaksi, 'Pengeluaran' AS jenis_transaksi FROM pengeluaran";
// $resultPengeluaran = $koneksi->query($sqlPengeluaran);
// if ($resultPengeluaran && $resultPengeluaran->num_rows > 0) {
//     while ($row = $resultPengeluaran->fetch_assoc()) {
//         $dataTransaksi[] = $row;
//     }
// }

// Urutkan semua transaksi berdasarkan tanggal jika ada pengeluaran
// usort($dataTransaksi, function($a, $b) {
//     return strtotime($b['tanggal_transaksi']) - strtotime($a['tanggal_transaksi']);
// });

$koneksi->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Thraz Computer</title>
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
        /* Tambahkan warna spesifik untuk card */
        .card-green { background-color: #e8f5e9; color: #43a047; }
        .card-red { background-color: #ffebee; color: #d32f2f; }
        .card-blue-light { background-color: #e3f2fd; color: #2196f3; }

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
                <a class="nav-link active" aria-current="page" href="laporan_keuangan.php">
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
            <h2 class="h4 text-dark mb-0">Laporan Keuangan</h2> <div class="d-flex align-items-center">
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
                    <h5 class="card-title mb-3">Filter Laporan Keuangan</h5>
                    <form method="GET" action="laporan_keuangan.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4 col-lg-3">
                                <label for="start_date" class="form-label">Tanggal Mulai:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label for="end_date" class="form-label">Tanggal Akhir:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <a href="laporan_keuangan.php" class="btn btn-outline-secondary w-100">Reset Filter</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card-statistic card-green">
                        <h3>Total Pendapatan (Servis Selesai)</h3>
                        <p class="h1 mb-0">Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></p>
                    </div>
                </div>
                </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3 text-dark">Detail Transaksi</h2>
                    <p class="card-subtitle text-muted mb-4">Rincian pendapatan dari servis yang telah diselesaikan pada periode yang dipilih.</p>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Tanggal Transaksi</th>
                                    <th scope="col">Jenis Transaksi</th>
                                    <th scope="col">Deskripsi</th>
                                    <th scope="col">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dataTransaksi)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada data transaksi pada periode ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataTransaksi as $transaksi): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transaksi['tanggal_transaksi']); ?></td>
                                            <td>
                                                <span class="badge <?php echo ($transaksi['jenis_transaksi'] == 'Pendapatan Servis' ? 'bg-success' : 'bg-danger'); ?>">
                                                    <?php echo htmlspecialchars($transaksi['jenis_transaksi']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaksi['deskripsi']); ?></td>
                                            <td>Rp <?php echo number_format($transaksi['jumlah'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <p class="lead text-muted">Laporan ini dapat disempurnakan dengan kemampuan ekspor data atau grafik.</p>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>