<?php
// laporan_keuangan.php
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();

$namaAkun = "Owner";

// Inisialisasi filter tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// --- Ambil data keuangan dari database ---
$totalPendapatan = 0;
$dataTransaksi = [];
$dataPendapatanHarian = [];

// Siapkan filter WHERE
$where_clause = "WHERE t.status = 'lunas'";
if ($start_date && $end_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause .= " AND DATE(t.tanggal) BETWEEN '$start_date_safe' AND '$end_date_safe'";
} elseif ($start_date) {
    $start_date_safe = $koneksi->real_escape_string($start_date);
    $where_clause .= " AND DATE(t.tanggal) >= '$start_date_safe'";
} elseif ($end_date) {
    $end_date_safe = $koneksi->real_escape_string($end_date);
    $where_clause .= " AND DATE(t.tanggal) <= '$end_date_safe'";
}

// Query total pendapatan
$sqlPendapatan = "SELECT SUM(total) AS total_pendapatan FROM transaksi t $where_clause";
$resultPendapatan = $koneksi->query($sqlPendapatan);
if ($resultPendapatan && $resultPendapatan->num_rows > 0) {
    $row = $resultPendapatan->fetch_assoc();
    $totalPendapatan = $row['total_pendapatan'];
}

// Query detail transaksi
$sqlTransaksi = "SELECT t.id_transaksi, t.jenis AS jenis_transaksi, t.total AS jumlah, t.tanggal AS tanggal_transaksi, c.nama_customer AS deskripsi FROM transaksi t JOIN customer c ON t.id_customer = c.id_customer $where_clause ORDER BY t.tanggal DESC";
$resultTransaksi = $koneksi->query($sqlTransaksi);
if ($resultTransaksi && $resultTransaksi->num_rows > 0) {
    while ($row = $resultTransaksi->fetch_assoc()) {
        $dataTransaksi[] = $row;
    }
}

// Kumpulkan data untuk grafik (diurutkan per tanggal)
$dataPendapatanGrafik = [];
foreach ($dataTransaksi as $transaksi) {
    $tanggal = date('Y-m-d', strtotime($transaksi['tanggal_transaksi']));
    if (!isset($dataPendapatanGrafik[$tanggal])) {
        $dataPendapatanGrafik[$tanggal] = 0;
    }
    $dataPendapatanGrafik[$tanggal] += $transaksi['jumlah'];
}
ksort($dataPendapatanGrafik);

// Siapkan data untuk JavaScript
$labels = json_encode(array_keys($dataPendapatanGrafik));
$values = json_encode(array_values($dataPendapatanGrafik));

// Tutup koneksi database
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Thraz Computer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0/dist/chartjs-adapter-moment.min.js"></script> 
</head>
<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
                    <p class="text-sm text-gray-400">Owner Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <i class="fas fa-home w-6 text-center"></i>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                           <i class="fas fa-users w-6 text-center"></i>
                            <span class="font-medium">Kelola Akun</span>
                        </a>
                    </li>
                    <li>
                        <a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <i class="fas fa-wrench w-6 text-center"></i>
                            <span class="font-medium">Kelola Sparepart</span>
                        </a>
                    </li>
                    <li>
                        <a href="kelola_kategori.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <i class="fas fa-tags w-6 text-center"></i>
                            <span class="font-medium">Kelola Kategori</span>
                        </a>
                    </li>
                     <li>
                        <a href="kelola_jasa.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <i class="fas fa-concierge-bell w-6 text-center"></i>
                            <span class="font-medium">Kelola Jasa</span>
                        </a>
                    </li>
                    <li>
                        <a href="laporan_keuangan.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                               <i class="fas fa-chart-line w-6 text-center"></i>
                            <span class="font-medium">Laporan Keuangan</span>
                        </a>
                    </li>
                    <li>
                        <a href="laporan_sparepart.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                               <i class="fas fa-boxes w-6 text-center"></i>
                            <span class="font-medium">Laporan Stok Barang</span>
                        </a>
                    </li>
                    <li>
                        <a href="laporan_pesanan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                               <i class="fas fa-clipboard-list w-6 text-center"></i>
                            <span class="font-medium">Laporan Pesanan</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Thar'z Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Laporan Keuangan</h2>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-circle text-xl text-gray-600"></i>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Filter Laporan</h3>
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
                            <div>
                                <a href="laporan_keuangan_cetak.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" target="_blank"
                                   class="w-full inline-flex justify-center items-center py-2 px-4 border border-green-500 shadow-sm text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                   <i class="fas fa-print mr-2"></i>Cetak Laporan
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-green-100 p-6 rounded-lg shadow-md text-center">
                        <h3 class="text-lg font-semibold text-green-800">Total Pendapatan</h3>
                        <p class="text-3xl font-bold text-green-700 mt-2">Rp <?php echo number_format($totalPendapatan ?? 0, 0, ',', '.'); ?></p>
                        <p class="text-sm text-green-600">dari servis selesai</p>
                    </div>
                    </div>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Grafik Pendapatan per Tanggal</h3>
                    <div class="relative h-80"> <canvas id="pendapatanChart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Detail Transaksi</h3>
                    <p class="text-sm text-gray-600 mb-4">Rincian pendapatan dari servis yang telah diselesaikan pada periode yang dipilih.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Transaksi</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Customer</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody id="transaksi-tbody" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($dataTransaksi)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data transaksi pada periode ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataTransaksi as $transaksi): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($transaksi['tanggal_transaksi']))); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    <?php echo htmlspecialchars($transaksi['jenis_transaksi']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($transaksi['deskripsi']); ?>
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

                    <div id="pagination-controls" class="mt-6 flex justify-center items-center space-x-4" style="display: none;">
                        <button id="prev-page-btn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Sebelumnya
                        </button>
                        <span id="page-counter" class="text-sm text-gray-700"></span>
                        <button id="next-page-btn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Berikutnya
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Data dari PHP untuk Chart.js
        const labels = <?php echo $labels; ?>;
        const values = <?php echo $values; ?>;

        const ctx = document.getElementById('pendapatanChart'); 
        
        if (ctx) { 
            const pendapatanChart = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: labels, 
                    datasets: [{
                        label: 'Pendapatan Harian (Rp)',
                        data: values, 
                        backgroundColor: 'rgba(59, 130, 246, 0.2)', 
                        borderColor: 'rgba(59, 130, 246, 1)', 
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
                                tooltipFormat: 'DD MMMM YYYY',
                                displayFormats: {
                                    day: 'DD MMM' 
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
        }

        // --- PERUBAHAN BARU: Logika Paginasi untuk Tabel Transaksi ---
        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPage = 9;
            const tableBody = document.getElementById('transaksi-tbody');
            if (!tableBody) return;

            const allRows = Array.from(tableBody.querySelectorAll('tr'));
            const totalRows = allRows.length;
            
            // Cek jika barisnya hanya berisi pesan "tidak ada data"
            if (totalRows === 1 && allRows[0].querySelectorAll('td').length === 1) {
                return;
            }

            const totalPages = Math.ceil(totalRows / rowsPerPage);
            let currentPage = 1;

            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            const pageCounter = document.getElementById('page-counter');
            const paginationControls = document.getElementById('pagination-controls');

            function displayPage(page) {
                allRows.forEach(row => row.style.display = 'none');
                const startIndex = (page - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                const pageRows = allRows.slice(startIndex, endIndex);
                pageRows.forEach(row => row.style.display = '');
            }

            function updatePaginationControls() {
                if (totalPages <= 1) {
                    paginationControls.style.display = 'none';
                    return;
                }
                paginationControls.style.display = 'flex';
                prevBtn.disabled = (currentPage === 1);
                nextBtn.disabled = (currentPage === totalPages);
                pageCounter.textContent = `Halaman ${currentPage} dari ${totalPages}`;
            }

            nextBtn.addEventListener('click', function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(currentPage);
                    updatePaginationControls();
                }
            });

            prevBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(currentPage);
                    updatePaginationControls();
                }
            });
            
            if (totalRows > 0) {
                displayPage(1);
                updatePaginationControls();
            }
        });
    </script>
</body>
</html>