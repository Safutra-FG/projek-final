<?php
// dashboard.php (untuk peran Owner)
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar

session_start();
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
$sqlEstimasiPendapatanHariIni = "SELECT SUM(estimasi_harga) AS total_estimasi_pendapatan FROM service WHERE status = 'selesai' || status = 'sudah diambil' || status = 'siap diambil' AND DATE(tanggal_selesai) = '$today'";
$resultEstimasiPendapatanHariIni = $koneksi->query($sqlEstimasiPendapatanHariIni);
if ($resultEstimasiPendapatanHariIni && $resultEstimasiPendapatanHariIni->num_rows > 0) {
    $row = $resultEstimasiPendapatanHariIni->fetch_assoc();
    $totalEstimasiPendapatanHariIni = $row['total_estimasi_pendapatan'];
}

// --- PERUBAHAN BARU: Menghapus LIMIT 5 agar semua data servis terbaru bisa dipaginasi ---
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
                                s.tanggal DESC, s.id_service DESC"; // LIMIT 5 dihapus
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
    <title>Dashboard Owner - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya dasar untuk card, agar lebih menarik dan konsisten dengan Tailwind */
        .card {
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            margin-top: 0;
            color: #4A5568;
            /* Warna teks yang lebih gelap */
            font-size: 1.125rem;
            /* Ukuran font lebih proporsional */
            margin-bottom: 12px;
            font-weight: 600;
            /* Sedikit lebih tebal */
        }

        .card p {
            font-size: 2.25em;
            /* Ukuran angka lebih besar */
            font-weight: bold;
            color: #2D3748;
            /* Warna angka lebih gelap */
        }
    </style>
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
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                <h2 class="text-2xl font-bold text-gray-800">Dashboard Owner</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <i class="fas fa-bell text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-circle text-xl text-gray-600"></i>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">
                            <i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">
                <h1 class="text-3xl font-extrabold mb-8 text-center text-gray-800">Selamat Datang Owner, Pantau Seluruh Operasi!</h1>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-10">
                    <div class="card bg-blue-100 text-blue-800">
                        <h3>Total Servis Hari Ini</h3>
                        <p class="text-blue-700"><?php echo $totalServisHariIni; ?></p>
                    </div>
                    <div class="card bg-yellow-100 text-yellow-800">
                        <h3>Servis Dalam Proses</h3>
                        <p class="text-yellow-700"><?php echo $servisDalamProses; ?></p>
                    </div>
                    <div class="card bg-purple-100 text-purple-800">
                        <h3>Servis Menunggu Sparepart</h3>
                        <p class="text-purple-700"><?php echo $servisMenungguSparepart; ?></p>
                    </div>
                    <div class="card bg-green-100 text-green-800">
                        <h3>Servis Selesai Hari Ini</h3>
                        <p class="text-green-700"><?php echo $servisSelesaiHariIni; ?></p>
                    </div>
                    <div class="card bg-indigo-100 text-indigo-800">
                        <h3>Estimasi Pendapatan Hari Ini</h3>
                        <p class="text-indigo-700">Rp <?php echo number_format($totalEstimasiPendapatanHariIni, 0, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md mt-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Servis Terbaru</h2>
                    <p class="text-gray-600 mb-4">Berikut adalah daftar servis yang baru saja masuk atau diperbarui.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Servis</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody id="servis-terbaru-tbody" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($latestServices)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data servis terbaru.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($latestServices as $service): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($service['id_service']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($service['nama_customer']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($service['device']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php
                                                $statusClass = '';
                                                switch ($service['status']) {
                                                    case 'Dalam Proses':
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'Menunggu Sparepart':
                                                        $statusClass = 'bg-purple-100 text-purple-800';
                                                        break;
                                                    case 'Selesai':
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'Dibatalkan':
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                        break;
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($service['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars(date('d-m-Y', strtotime($service['tanggal']))); ?>
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

                <div class="text-center mt-12">
                    <p class="text-lg text-gray-600">Gunakan menu di samping untuk mengelola data dan operasi secara detail.</p>
                </div>
            </div>

        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPage = 9; // Anda bisa mengubah angka ini jika ingin, misal 5 atau 10
            const tableBody = document.getElementById('servis-terbaru-tbody');
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