<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();

// Pastikan koneksi database valid
if (!isset($koneksi) || !$koneksi instanceof mysqli) {
    die("Koneksi database belum dibuat atau salah.");
}

// Cek jika admin sudah login (contoh sederhana)
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit();
// }
$nama_akun_admin = "Admin Contoh"; // Placeholder, ganti dengan dari session

// Logika filter status pesanan
// Defaultnya, tampilkan pesanan yang paling butuh perhatian: 'Menunggu Pembayaran'
$status_filter = $_GET['status_filter'] ?? 'Menunggu Pembayaran';

$daftar_pesanan = [];
$error_db = null;

// Ganti `t.status` dengan `t.status` jika kamu mengikuti saran penamaan kolom
$sql_pesanan = "SELECT 
                    t.id_transaksi, 
                    c.nama_customer, 
                    t.tanggal AS tanggal_pesan, 
                    t.total AS total_tagihan,
                    t.status -- Pastikan nama kolom ini benar
                FROM 
                    transaksi t
                JOIN 
                    customer c ON t.id_customer = c.id_customer
                WHERE 
                    t.jenis = 'penjualan' 
                    AND t.status = ? -- Ganti 't.status' menjadi 't.status' jika perlu
                ORDER BY 
                    t.tanggal ASC"; // Tampilkan yang paling lama menunggu di atas

$stmt_pesanan = $koneksi->prepare($sql_pesanan);
if ($stmt_pesanan) {
    $stmt_pesanan->bind_param("s", $status_filter);
    $stmt_pesanan->execute();
    $result_pesanan = $stmt_pesanan->get_result();
    while ($row = $result_pesanan->fetch_assoc()) {
        $daftar_pesanan[] = $row;
    }
    $stmt_pesanan->close();
} else {
    $error_db = "Gagal menyiapkan query daftar pesanan: " . $koneksi->error;
}
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Pesanan Penjualan - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-gray-100 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <div class="w-64 bg-gray-800 shadow-lg flex flex-col">
            <div>
                <div class="flex flex-col items-center my-6">
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
                        <a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Pembayaran Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                &copy; Thar'z Computer <?php echo date("Y"); ?>
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Pesanan Penjualan</h2>
                <div class="flex items-center space-x-3">
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($nama_akun_admin); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-auto">
                <?php if ($error_db): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Error Database!</p>
                        <p><?php echo htmlspecialchars($error_db); ?></p>
                    </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Cari Transaksi Penjualan</h3>
                    <form action="pembayaran_penjualan.php" method="GET" class="flex items-end space-x-4">
                        <div>
                            <label for="id_transaksi_cari" class="block text-sm font-medium text-gray-700 mb-1">Masukkan ID Transaksi:</label>
                            <input type="number" name="id_transaksi" id="id_transaksi_cari" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Contoh: 101" required>
                        </div>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">
                            <i class="bi bi-search"></i> Cari & Proses
                        </button>
                    </form>
                </div>

                <hr class="my-8 border-gray-300">
                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Lihat Daftar Pesanan Berdasarkan Status</h3>
                    <form action="" method="GET" class="flex items-center space-x-4">
                        <label for="status_filter_select" class="text-sm font-medium text-gray-700">Tampilkan:</label>
                        <select id="status_filter_select" name="status_filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-50 focus:ring-blue-500 focus:border-blue-500 focus:outline-none" onchange="this.form.submit()">
                            <option value="Menunggu Pembayaran" <?php if ($status_filter == 'Menunggu Pembayaran') echo 'selected'; ?>>Menunggu Pembayaran</option>
                            <option value="lunas-siap diambil" <?php if ($status_filter == 'lunas-siap diambil') echo 'selected'; ?>>Lunas</option>
                            <option value="Dibatalkan" <?php if ($status_filter == 'Dibatalkan') echo 'selected'; ?>>Dibatalkan</option>
                        </select>
                        <noscript><button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md">Filter</button></noscript>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Daftar Pesanan (Status: <?php echo htmlspecialchars($status_filter); ?>)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaksi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Pesan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($daftar_pesanan)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada pesanan dengan status yang dipilih.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daftar_pesanan as $pesanan): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">#<?php echo htmlspecialchars($pesanan['id_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($pesanan['nama_customer']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesan'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">Rp <?php echo number_format($pesanan['total_tagihan'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium">
                                                <a href="pembayaran_penjualan.php?id_transaksi=<?php echo htmlspecialchars($pesanan['id_transaksi']); ?>" class="text-indigo-600 hover:text-indigo-900">
                                                    Proses / Lihat Detail &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>