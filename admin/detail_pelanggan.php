<?php
session_start();
include '../koneksi.php'; // Path ini sudah benar jika koneksi.php di folder yang sama

// Pastikan koneksi database sudah dibuat dan valid
if (!isset($koneksi) || !$koneksi instanceof mysqli) {
    die("Koneksi database belum dibuat atau salah.");
}

$namaAkun = "Admin"; // Anda bisa mengambil nama akun dari session jika sudah ada

$id_customer = $_GET['id'] ?? null;

if (!$id_customer) {
    // Redirect ke halaman data_pelanggan jika ID tidak ditemukan
    header("Location: data_pelanggan.php");
    exit;
}

// Ambil data pelanggan
$sql_pelanggan = "SELECT * FROM customer WHERE id_customer = ?";
$stmt = $koneksi->prepare($sql_pelanggan);
if (!$stmt) {
    die("Prepare statement gagal untuk pelanggan: " . $koneksi->error);
}
$stmt->bind_param("s", $id_customer);
$stmt->execute();
$data_pelanggan = $stmt->get_result()->fetch_assoc();
$stmt->close(); // Tutup statement setelah digunakan

if (!$data_pelanggan) {
    // Redirect jika data pelanggan tidak ditemukan
    header("Location: data_pelanggan.php?status=notfound");
    exit;
}

// Ambil riwayat service
// Menggunakan nama kolom yang ada di database Anda: 'tanggal' dan 'kerusakan'
$sql_service = "SELECT service.*, detail_service.kerusakan 
                FROM service 
                LEFT JOIN detail_service ON service.id_service = detail_service.id_service 
                WHERE service.id_customer = ? 
                ORDER BY service.tanggal DESC";

$stmt_service = $koneksi->prepare($sql_service);

if ($stmt_service) {
    $stmt_service->bind_param("s", $id_customer);
    $stmt_service->execute();
    $riwayat_service = $stmt_service->get_result();
    $stmt_service->close(); // Tutup statement setelah digunakan
} else {
    // Handle error jika prepare statement gagal
    die("Prepare statement gagal untuk service: " . $koneksi->error);
}

// Ambil riwayat pembelian
// Menggunakan nama kolom yang ada di database Anda: 'tanggal' untuk transaksi, dan meng-aliasnya
$sql_beli = "SELECT td.id_transaksi, td.jumlah, td.subtotal, t.tanggal AS tanggal_transaksi, sb.nama_barang
             FROM detail_transaksi td
             JOIN transaksi t ON td.id_transaksi = t.id_transaksi
             JOIN stok sb ON td.id_barang = sb.id_barang
             WHERE t.id_customer = ? ORDER BY t.tanggal DESC";
$stmt_beli = $koneksi->prepare($sql_beli);

if (!$stmt_beli) {
    die("Prepare statement gagal untuk pembelian: " . $koneksi->error);
}
$stmt_beli->bind_param("s", $id_customer);
$stmt_beli->execute();
$riwayat_beli = $stmt_beli->get_result();
$stmt_beli->close(); // Tutup statement setelah digunakan

// Tutup koneksi setelah semua data diambil
$koneksi->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Pelanggan: <?php echo htmlspecialchars($data_pelanggan['nama_customer']); ?> - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Gaya tambahan untuk flex-wrap pada info detail agar rapi */
        .detail-item {
            flex: 1 1 45%;
            /* Memungkinkan dua kolom per baris pada layar lebar */
            min-width: 280px;
            /* Lebar minimum sebelum wrap */
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thraz Computer</h1>
                    <p class="text-sm text-gray-400">Admin Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">🏠</span>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📝</span>
                            <span class="font-medium">Data Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                &copy; Thraz Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Detail Pelanggan</h2>
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

            <div class="flex-1 p-8 overflow-auto">

                <div class="mb-6">
                    <a href="data_pelanggan.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali ke Data Pelanggan
                    </a>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <div class="card-header border-b border-gray-200 pb-3 mb-4">
                        <h5 class="mb-0 text-lg font-semibold text-gray-700">👤 Data Pelanggan</h5>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-2">
                            <div class="col-span-1"><strong>ID Customer:</strong> <?php echo htmlspecialchars($data_pelanggan['id_customer']); ?></div>
                            <div class="col-span-1"><strong>Nama:</strong> <?php echo htmlspecialchars($data_pelanggan['nama_customer']); ?></div>
                            <div class="col-span-1"><strong>Nomor Telepon:</strong> <?php echo htmlspecialchars($data_pelanggan['no_telepon']); ?></div>
                            <div class="col-span-1"><strong>Email:</strong> <?php echo htmlspecialchars($data_pelanggan['email']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">🛠️ Riwayat Service</h2>
                    <?php if ($riwayat_service && $riwayat_service->num_rows > 0) : ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID Service</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Kerusakan</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 text-center">
                                    <?php while ($row = $riwayat_service->fetch_assoc()) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['id_service']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['device']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['kerusakan'] ?: "-"); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php
                                                $statusClass = '';
                                                switch ($row['status']) {
                                                    case 'diajukan':
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                        break;
                                                    case 'dikonfirmasi':
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'menunggu sparepart':
                                                        $statusClass = 'bg-purple-100 text-purple-800';
                                                        break;
                                                    case 'diperbaiki':
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'selesai':
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'dibatalkan':
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                        break;
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['tanggal']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <a href="edit_service.php?id=<?php echo htmlspecialchars($row['id_service']); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-1 px-3 rounded-md shadow-sm transition duration-200 text-xs">Detail</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-gray-500">Belum ada data service untuk pelanggan ini.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">🧩 Riwayat Pembelian Sparepart</h2>
                    <?php if ($riwayat_beli && $riwayat_beli->num_rows > 0) : ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Transaksi</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 text-center">
                                    <?php while ($row = $riwayat_beli->fetch_assoc()) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['tanggal_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['jumlah']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp<?php echo number_format($row['subtotal'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-gray-500">Belum ada pembelian sparepart untuk pelanggan ini.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

</body>

</html>