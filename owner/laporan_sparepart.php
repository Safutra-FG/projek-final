<?php
// laporan_sparepart.php
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();

$namaAkun = "Owner";

// --- Inisialisasi filter DULU SEKALI sebelum digunakan ---
// Pastikan $search_nama selalu terdefinisi, bahkan jika $_GET['search_nama'] tidak ada
$search_nama = '';
if (isset($_GET['search_nama'])) {
    $search_nama = $_GET['search_nama'];
}

// --- Inisialisasi filter tanggal ---
$tanggal_dari = isset($_GET['tanggal_dari']) ? $_GET['tanggal_dari'] : '';
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? $_GET['tanggal_sampai'] : '';

// --- Ambil data stok barang dari database (dari tabel 'stok') ---
$dataStokBarang = [];

$sqlStokBarang = "SELECT id_barang, nama_barang, stok, harga FROM stok";
$params = [];
$types = '';

if ($search_nama != '') {
    $sqlStokBarang .= " WHERE nama_barang LIKE ?";
    $params[] = "%" . $search_nama . "%";
    $types .= "s";
}
$sqlStokBarang .= " ORDER BY nama_barang ASC";

$stmt = $koneksi->prepare($sqlStokBarang);

// Periksa apakah prepare berhasil
if ($stmt === false) {
    die("Error dalam menyiapkan statement: " . $koneksi->error);
}

// Menggunakan splat operator (...) untuk bind_param
// Ini adalah cara yang lebih disukai di PHP modern dan menghindari peringatan "by reference"
if (!empty($params)) {
    $stmt->bind_param($types, ...$params); // Ini baris yang dimodifikasi
}

$stmt->execute();
$resultStokBarang = $stmt->get_result();

if ($resultStokBarang && $resultStokBarang->num_rows > 0) {
    while ($row = $resultStokBarang->fetch_assoc()) {
        $dataStokBarang[] = $row;
    }
}

$stmt->close(); // Tutup statement setelah digunakan

// Ambil data pengeluaran service
$penggunaanService = [];
$sqlService = "SELECT ds.id_barang, COUNT(ds.id_barang) as jumlah FROM detail_service ds JOIN service s ON ds.id_service = s.id_service WHERE ds.id_barang IS NOT NULL";
if ($tanggal_dari != '' && $tanggal_sampai != '') {
    $sqlService .= " AND DATE(s.tanggal) BETWEEN '" . $koneksi->real_escape_string($tanggal_dari) . "' AND '" . $koneksi->real_escape_string($tanggal_sampai) . "'";
}
$sqlService .= " GROUP BY ds.id_barang";
$resultService = $koneksi->query($sqlService);
while($row = $resultService->fetch_assoc()) {
    $penggunaanService[$row['id_barang']] = (int)$row['jumlah'];
}

// Ambil data pengeluaran penjualan
$penjualanBarang = [];
$sqlPenjualan = "SELECT dt.id_barang, SUM(dt.jumlah) as jumlah FROM detail_transaksi dt JOIN transaksi t ON dt.id_transaksi = t.id_transaksi WHERE dt.id_barang IS NOT NULL";
if ($tanggal_dari != '' && $tanggal_sampai != '') {
    $sqlPenjualan .= " AND DATE(t.tanggal) BETWEEN '" . $koneksi->real_escape_string($tanggal_dari) . "' AND '" . $koneksi->real_escape_string($tanggal_sampai) . "'";
}
$sqlPenjualan .= " GROUP BY dt.id_barang";
$resultPenjualan = $koneksi->query($sqlPenjualan);
while($row = $resultPenjualan->fetch_assoc()) {
    $penjualanBarang[$row['id_barang']] = (int)$row['jumlah'];
}
$koneksi->close();

// Gabungkan semua nama barang dari kedua sumber
$allNamaBarang = array_unique(array_merge(array_keys($penggunaanService), array_keys($penjualanBarang)));

// Siapkan data untuk grafik
$labels = [];
$serviceValues = [];
$penjualanValues = [];
foreach ($allNamaBarang as $nama) {
    $labels[] = $nama;
    $serviceValues[] = isset($penggunaanService[$nama]) ? $penggunaanService[$nama] : 0;
    $penjualanValues[] = isset($penjualanBarang[$nama]) ? $penjualanBarang[$nama] : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Barang - Thraz Computer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="laporan_sparepart.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                <h2 class="text-2xl font-bold text-gray-800">Laporan Stok Barang</h2>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-circle text-xl text-gray-600"></i>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Filter Laporan Stok</h3>
                    <form method="GET" action="laporan_sparepart.php">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                            <div class="md:col-span-1">
                                <label for="tanggal_dari" class="block text-sm font-medium text-gray-700">Dari Tanggal</label>
                                <input type="date" id="tanggal_dari" name="tanggal_dari" value="<?php echo htmlspecialchars($tanggal_dari); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="md:col-span-1">
                                <label for="tanggal_sampai" class="block text-sm font-medium text-gray-700">Sampai Tanggal</label>
                                <input type="date" id="tanggal_sampai" name="tanggal_sampai" value="<?php echo htmlspecialchars($tanggal_sampai); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="md:col-span-1">
                                <label for="search_nama" class="block text-sm font-medium text-gray-700">Cari Nama Barang</label>
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input type="text" id="search_nama" name="search_nama" value="<?php echo htmlspecialchars($search_nama); ?>" placeholder="Ketik nama barang..." class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div class="flex space-x-3 mt-4 md:mt-0">
                                <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Filter</button>
                                <a href="laporan_sparepart.php" class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Reset</a>
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

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Grafik Penggunaan & Penjualan Sparepart</h3>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <canvas id="sparepartChart"></canvas>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Ringkasan Stok Barang Saat Ini</h3>
                    <p class="text-sm text-gray-600 mb-4">Menampilkan daftar barang beserta jumlah stok dan harganya.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Tersedia</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pengeluaran Service (Unit)</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pengeluaran Penjualan (Unit)</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pengeluaran (Unit)</th>
                                </tr>
                            </thead>
                            <tbody id="laporan-stok-tbody" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($dataStokBarang)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data barang yang cocok dengan pencarian.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    // Siapkan array untuk diurutkan berdasarkan total pengeluaran
                                    $tabelData = [];
                                    foreach ($dataStokBarang as $barang) {
                                        $id_barang = $barang['id_barang'];
                                        $harga = $barang['harga'];
                                        $jml_service = isset($penggunaanService[$id_barang]) ? $penggunaanService[$id_barang] : 0;
                                        $jml_penjualan = isset($penjualanBarang[$id_barang]) ? $penjualanBarang[$id_barang] : 0;
                                        $total_pengeluaran = $jml_service + $jml_penjualan;
                                        $tabelData[] = [
                                            'barang' => $barang,
                                            'jml_service' => $jml_service,
                                            'jml_penjualan' => $jml_penjualan,
                                            'total_pengeluaran' => $total_pengeluaran
                                        ];
                                    }
                                    // Urutkan dari total_pengeluaran terbanyak ke terkecil
                                    usort($tabelData, function($a, $b) {
                                        return $b['total_pengeluaran'] <=> $a['total_pengeluaran'];
                                    });
                                    ?>
                                    <?php foreach ($tabelData as $row): ?>
                                    <?php
                                        $barang = $row['barang'];
                                        $jml_service = $row['jml_service'];
                                        $jml_penjualan = $row['jml_penjualan'];
                                        $total_pengeluaran = $row['total_pengeluaran'];
                                        $id_barang = $barang['id_barang'];
                                        $harga = $barang['harga'];
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($barang['id_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($barang['nama_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                            <?php
                                                $stok = $barang['stok'];
                                                $stokClass = '';
                                                if ($stok <= 5) {
                                                    $stokClass = 'bg-red-100 text-red-800'; // Menipis
                                                } elseif ($stok <= 10) {
                                                    $stokClass = 'bg-yellow-100 text-yellow-800'; // Hampir Habis
                                                } else {
                                                    $stokClass = 'bg-green-100 text-green-800'; // Cukup
                                                }
                                            ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $stokClass; ?>">
                                                <?php echo htmlspecialchars($stok); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right"><?php echo $jml_service; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right"><?php echo $jml_penjualan; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-bold"><?php echo $total_pengeluaran; ?></td>
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
            const tableBody = document.getElementById('laporan-stok-tbody');
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

            // Data untuk grafik gabungan
            const labels = <?php echo json_encode($labels); ?>;
            const serviceValues = <?php echo json_encode($serviceValues); ?>;
            const penjualanValues = <?php echo json_encode($penjualanValues); ?>;

            const ctx = document.getElementById('sparepartChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Penggunaan dalam Service',
                            data: serviceValues,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Penjualan',
                            data: penjualanValues,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>