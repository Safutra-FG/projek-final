<?php
// laporan_sparepart.php
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();
// Logika otentikasi sederhana (opsional, untuk produksi gunakan yang lebih kuat)
// Jika Anda memiliki sistem role-based access, pastikan user_role adalah 'owner'
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
//     header("Location: ../login.php");
//     exit();
// }

$namaAkun = "Owner";

// Inisialisasi filter
$search_nama = isset($_GET['search_nama']) ? $koneksi->real_escape_string($_GET['search_nama']) : '';

// --- Ambil data stok barang dari database (dari tabel 'stok') ---
$dataStokBarang = []; // Nama variabel diubah agar lebih sesuai

$where_clause_stok = "WHERE 1=1";
if ($search_nama != '') {
    $where_clause_stok .= " AND nama_barang LIKE '%" . $search_nama . "%'";
}

// Mengambil data dari tabel 'stok' dengan kolom yang benar
// **PERHATIKAN: Komentar di dalam string SQL sudah dihapus!**
$sqlStokBarang = "SELECT
                        id_barang,
                        nama_barang,
                        stok,
                        harga
                      FROM
                        stok
                      " . $where_clause_stok . "
                      ORDER BY
                        nama_barang ASC";

$resultStokBarang = $koneksi->query($sqlStokBarang);

if ($resultStokBarang && $resultStokBarang->num_rows > 0) {
    while ($row = $resultStokBarang->fetch_assoc()) {
        $dataStokBarang[] = $row;
    }
}

// Tidak ada daftar kategori unik untuk filter karena kolom 'kategori' tidak ada di tabel 'stok'.

$koneksi->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Barang - Thraz Computer</title>
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
                <a class="nav-link active" aria-current="page" href="laporan_sparepart.php">
                    <i class="fas fa-boxes"></i>Laporan Stok Barang
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
            <h2 class="h4 text-dark mb-0">Laporan Stok Barang</h2> <div class="d-flex align-items-center">
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
                    <h5 class="card-title mb-3">Filter Laporan Stok Barang</h5>
                    <form method="GET" action="laporan_sparepart.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6 col-lg-6">
                                <label for="search_nama" class="form-label">Cari Nama Barang:</label>
                                <input type="text" class="form-control" id="search_nama" name="search_nama" value="<?php echo htmlspecialchars($search_nama); ?>" placeholder="Cari nama barang...">
                            </div>
                            <div class="col-md-3 col-lg-3">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-3 col-lg-3">
                                <a href="laporan_sparepart.php" class="btn btn-outline-secondary w-100">Reset Filter</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3 text-dark">Ringkasan Stok Barang Saat Ini</h2>
                    <p class="card-subtitle text-muted mb-4">Menampilkan daftar barang beserta jumlah stoknya yang tersedia.</p>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID Barang</th>
                                    <th scope="col">Nama Barang</th>
                                    <th scope="col">Stok Tersedia</th>
                                    <th scope="col">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dataStokBarang)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada data barang dengan filter ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataStokBarang as $barang): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($barang['id_barang']); ?></td>
                                            <td><?php echo htmlspecialchars($barang['nama_barang']); ?></td>
                                            <td>
                                                <span class="badge <?php echo ($barang['stok'] < 5 ? 'bg-danger' : ($barang['stok'] < 10 ? 'bg-warning text-dark' : 'bg-success')); ?>">
                                                    <?php echo htmlspecialchars($barang['stok']); ?>
                                                </span>
                                            </td>
                                            <td>Rp <?php echo number_format($barang['harga'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <p class="lead text-muted">Laporan ini memberikan gambaran tentang ketersediaan inventaris barang Anda.</p>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>