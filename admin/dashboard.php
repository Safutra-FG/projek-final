<?php
// dashboard.php (untuk peran Admin)
include '../koneksi.php'; // Pastikan file koneksi.php ada dan benar
include 'auth.php'; // Menambahkan otentikasi


$namaAkun = getNamaUser(); // Menggunakan fungsi dari auth.php

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
// FIX 1: Memperbaiki nama variabel dari $resultDalamProproses menjadi $resultDalamProses
$resultDalamProses = $koneksi->query($sqlDalamProses);
if ($resultDalamProses && $resultDalamProses->num_rows > 0) { // Baris 35: Sekarang merujuk dengan benar ke $resultDalamProses
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
// PASTIKAN NAMA KOLOM 'estimasi_harga' BENAR DI DATABASE ANDA.
$sqlEstimasiPendapatanHariIni = "SELECT SUM(estimasi_harga) AS total_estimasi_pendapatan FROM service WHERE status = 'Selesai' AND DATE(tanggal_selesai) = '$today'";
$resultEstimasiPendapatanHariIni = $koneksi->query($sqlEstimasiPendapatanHariIni);
if ($resultEstimasiPendapatanHariIni && $resultEstimasiPendapatanHariIni->num_rows > 0) {
    $row = $resultEstimasiPendapatanHariIni->fetch_assoc();
    $totalEstimasiPendapatanHariIni = $row['total_estimasi_pendapatan'];
}

// --- Ambil data servis terbaru dari database ---
$latestServices = [];
// Query menggunakan JOIN untuk mengambil nama_customer dari tabel 'customer'
// Asumsi: Ada kolom 'id_customer' di tabel 'service' yang merupakan foreign key ke 'id_customer' di tabel 'customer'
$sqlLatestServices = "SELECT
                            s.id_service,
                            c.nama_customer,
                            s.device, -- <--- PERBAIKAN PENTING DI SINI! (Baris 74)
                            s.status,
                            s.tanggal
                          FROM
                            service s
                          JOIN
                            customer c ON s.id_customer = c.id_customer
                          ORDER BY
                            s.tanggal DESC, s.id_service DESC
                          LIMIT 5";
$resultLatestServices = $koneksi->query($sqlLatestServices); // Baris ini adalah baris 87 yang bermasalah sebelumnya

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
    <title>Dashboard Admin - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
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
                    <p class="text-sm text-gray-400">Admin Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                            <span class="text-xl">🏠</span>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Pembayaran Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300  hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Kelola Penjualan</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📝</span>
                            <span class="font-medium">Data Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">👥</span>
                            <span class="font-medium">Data Pelanggan</span>
                        </a>
                    </li>
                    <li>
                        <a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💳</span>
                            <span class="font-medium">Riwayat Transaksi</span>
                        </a>
                    </li>
                    <li>
                        <a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📦</span>
                            <span class="font-medium">Stok Gudang</span>
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
                <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <span class="text-2xl">🔔</span>
                    </button>
                    <div class="flex items-center space-x-3">
                        <span class="text-xl text-gray-600">👤</span>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">
                <h1 class="text-3xl font-extrabold mb-8 text-center text-gray-800">Selamat Datang Admin, Pantau Seluruh Operasi!</h1>

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
                    <p class="text-gray-600">Berikut adalah daftar servis yang baru saja masuk atau diperbarui.</p>
                    <table class="min-w-full divide-y divide-gray-200 mt-4">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Servis</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
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
                                            <?php
                                            echo htmlspecialchars($service['device']); // <--- PERBAIKAN PENTING DI SINI! (Baris 199)
                                            ?>
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
                                            <?php echo htmlspecialchars($service['tanggal']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-center mt-12">
                    <p class="text-lg text-gray-600">Gunakan menu di samping untuk mengelola data dan operasi secara detail.</p>
                </div>
            </div>

        </div>
    </div>

</body>

</html>