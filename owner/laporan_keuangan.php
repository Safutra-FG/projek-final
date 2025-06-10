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
$dataPendapatanHarian = []; // Data untuk grafik

$where_clause = "WHERE s.status = 'Selesai'";
if ($start_date && $end_date) {
    // Pastikan format tanggal aman untuk query
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause .= " AND DATE(s.tanggal_selesai) BETWEEN '$start_date_safe' AND '$end_date_safe'";
} elseif ($start_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $where_clause .= " AND DATE(s.tanggal_selesai) >= '$start_date_safe'";
} elseif ($end_date) {
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause .= " AND DATE(s.tanggal_selesai) <= '$end_date_safe'";
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
                    s.tanggal_selesai ASC"; // Order by ASC untuk grafik time-series

$resultTransaksi = $koneksi->query($sqlTransaksi);
if ($resultTransaksi && $resultTransaksi->num_rows > 0) {
    while ($row = $resultTransaksi->fetch_assoc()) {
        $dataTransaksi[] = $row;

        // Kumpulkan data pendapatan harian untuk grafik
        $tanggal = date('Y-m-d', strtotime($row['tanggal_transaksi']));
        if (!isset($dataPendapatanHarian[$tanggal])) {
            $dataPendapatanHarian[$tanggal] = 0;
        }
        $dataPendapatanHarian[$tanggal] += $row['jumlah'];
    }
}

// Urutkan data pendapatan harian berdasarkan tanggal
ksort($dataPendapatanHarian);

// Siapkan data untuk JavaScript
$labels = array_keys($dataPendapatanHarian);
$values = array_values($dataPendapatanHarian);

// Tutup koneksi database
$koneksi->close();

// Siapkan data untuk JavaScript.
// Jika $dataPendapatanPerTanggal kosong, kirim array kosong ke JavaScript
$labels = empty($dataPendapatanPerTanggal) ? '[]' : json_encode(array_keys($dataPendapatanPerTanggal));
$values = empty($dataPendapatanPerTanggal) ? '[]' : json_encode(array_values($dataPendapatanPerTanggal));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Thraz Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0/dist/chartjs-adapter-moment.min.js"></script> 
    
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
<body class="bg-gray-100 text-gray-900 font-sans antialiased">

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

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Laporan Keuangan</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <i class="fas fa-bell text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-circle text-xl text-gray-600"></i>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Laporan</h3>
                    <form method="GET" action="laporan_keuangan.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-end">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai:</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Akhir:</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="flex space-x-3">
                                <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Filter</button>
                                <a href="laporan_keuangan.php" class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                     <div class="bg-green-100 p-6 rounded-lg shadow-md text-center">
                        <h3 class="text-lg font-semibold text-green-800">Total Pendapatan</h3>
                        <p class="text-3xl font-bold text-green-700 mt-2">Rp <?php echo number_format($totalPendapatan ?? 0, 0, ',', '.'); ?></p>
                        <p class="text-sm text-green-600">dari servis selesai</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Grafik Pendapatan per Tanggal</h5>
                            <canvas id="pendapatanChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Detail Transaksi</h3>
                    <p class="text-sm text-gray-600 mb-4">Rincian pendapatan dari servis yang telah diselesaikan pada periode yang dipilih.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Transaksi</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($dataTransaksi)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data transaksi pada periode ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataTransaksi as $transaksi): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo htmlspecialchars(date('d M Y', strtotime($transaksi['tanggal_transaksi']))); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    <?php echo htmlspecialchars($transaksi['jenis_transaksi']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                Servis untuk <?php echo htmlspecialchars($transaksi['deskripsi']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                                                Rp <?php echo number_format($transaksi['jumlah'], 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="text-center mt-5">
                <p class="lead text-muted"></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data dari PHP untuk Chart.js
        const labels = <?php echo json_encode($labels); ?>;
        const values = <?php echo json_encode($values); ?>;

        // --- DEBUGGING CONSOLE LOGS ---
        console.log("-----------------------------------------");
        console.log("DEBUG: Data Labels dari PHP:", labels);
        console.log("DEBUG: Data Values dari PHP:", values);
        console.log("-----------------------------------------");

        const ctx = document.getElementById('pendapatanChart'); 
        
        // --- DEBUGGING: Periksa apakah elemen canvas ditemukan ---
        if (ctx) { 
            console.log("DEBUG: Elemen canvas 'pendapatanChart' Ditemukan.");
            const pendapatanChart = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: labels, 
                    datasets: [{
                        label: 'Pendapatan Harian (Rp)',
                        data: values, 
                        backgroundColor: 'rgba(75, 192, 192, 0.2)', 
                        borderColor: 'rgba(75, 192, 192, 1)', 
                        borderWidth: 2,
                        tension: 0.4, 
                        fill: true 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, 
                    scales: {
                        x: {
                            type: 'time', 
                            time: {
                                unit: 'day', 
                                tooltipFormat: 'dd MMMM YYYY', // Perbaiki format tooltip (YYYY)
                                displayFormats: {
                                    day: 'dd MMM' 
                                }
                            },
                            title: {
                                display: true,
                                text: 'Tanggal'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Pendapatan (Rp)'
                            },
                            ticks: {
                                callback: function(value, index, ticks) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        } else {
            console.error("DEBUG: Elemen canvas dengan ID 'pendapatanChart' TIDAK ditemukan. Pastikan ada di HTML.");
        }
    </script>
</body>
</html