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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                        <a href="laporan_keuangan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
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
                        <a href="laporan_pesanan.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                <h2 class="text-2xl font-bold text-gray-800">Laporan Pesanan (Servis)</h2>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-circle text-xl text-gray-600"></i>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Filter Laporan Pesanan</h3>
                    <form method="GET" action="laporan_pesanan.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-end">
                            <div class="lg:col-span-1">
                                <label for="status_filter" class="block text-sm font-medium text-gray-700">Status Servis</label>
                                <select id="status_filter" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="Semua" <?php echo ($filter_status == 'Semua' || $filter_status == '' ? 'selected' : ''); ?>>Semua</option>
                                    <option value="Dalam Proses" <?php echo ($filter_status == 'Dalam Proses' ? 'selected' : ''); ?>>Dalam Proses</option>
                                    <option value="Menunggu Sparepart" <?php echo ($filter_status == 'Menunggu Sparepart' ? 'selected' : ''); ?>>Menunggu Sparepart</option>
                                    <option value="Selesai" <?php echo ($filter_status == 'Selesai' ? 'selected' : ''); ?>>Selesai</option>
                                    <option value="Dibatalkan" <?php echo ($filter_status == 'Dibatalkan' ? 'selected' : ''); ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">Tgl Masuk (Mulai)</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">Tgl Masuk (Akhir)</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="flex space-x-3">
                                <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Filter</button>
                                <a href="laporan_pesanan.php" class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Pesanan Servis</h3>
                    <p class="text-sm text-gray-600 mb-4">Menampilkan semua data servis berdasarkan filter yang dipilih.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keluhan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Masuk</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Selesai</th>
                                </tr>
                            </thead>
                            <tbody id="pesanan-tbody" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($dataSemuaServis)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data pesanan servis yang cocok dengan filter.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dataSemuaServis as $service): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($service['id_service']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($service['nama_customer']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($service['device']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($service['keluhan']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                                $statusClass = '';
                                                switch ($service['status']) {
                                                    case 'Dalam Proses':
                                                        $statusClass = 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'Menunggu Sparepart':
                                                        $statusClass = 'bg-purple-100 text-purple-800'; break;
                                                    case 'Selesai':
                                                        $statusClass = 'bg-green-100 text-green-800'; break;
                                                    case 'Dibatalkan':
                                                        $statusClass = 'bg-red-100 text-red-800'; break;
                                                    default:
                                                        $statusClass = 'bg-gray-100 text-gray-800'; break;
                                                }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($service['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d M Y', strtotime($service['tanggal'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo $service['tanggal_selesai'] ? date('d M Y', strtotime($service['tanggal_selesai'])) : '-'; ?></td>
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
        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPage = 9;
            const tableBody = document.getElementById('pesanan-tbody');
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
                pageRows.forEach(row => row.style.display = ''); // Mengembalikan ke display default (table-row)
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