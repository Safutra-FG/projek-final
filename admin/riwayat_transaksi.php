<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();

// Pastikan koneksi database sudah dibuat dan valid
if (!isset($koneksi) || !$koneksi instanceof mysqli) {
    die("Koneksi database belum dibuat atau salah.");
}

// Logika untuk filtering bulan (jika ada) akan tetap di sini
$bulan_filter = $_GET['bulan'] ?? date('Y-m'); // Default bulan saat ini (YYYY-MM)

// Query untuk mengambil data transaksi (kolom 'total' di-alias menjadi 'total_harga')
// Query untuk mengambil data transaksi (kolom 'total' di-alias menjadi 'total_harga')
$sql_transaksi = "
    SELECT
        t.id_transaksi,
        c.nama_customer,
        t.tanggal AS tanggal_transaksi,
        t.jenis AS jenis_transaksi,
        t.total AS total_harga,
        t.status AS status_transaksi
    FROM
        transaksi t
    JOIN
        customer c ON t.id_customer = c.id_customer
    WHERE
        DATE_FORMAT(t.tanggal, '%Y-%m') = ?
    ORDER BY
        t.tanggal DESC;
";

$stmt_transaksi = $koneksi->prepare($sql_transaksi);

if ($stmt_transaksi) {
    $stmt_transaksi->bind_param("s", $bulan_filter);
    $stmt_transaksi->execute();
    $result_transaksi = $stmt_transaksi->get_result();
    $stmt_transaksi->close();
} else {
    die("Prepare statement gagal untuk transaksi: " . $koneksi->error);
}

$koneksi->close(); // Tutup koneksi setelah semua data diambil

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Riwayat Transaksi</h2>
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
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali ke Dashboard
                    </a>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Filter Riwayat</h2>
                    <form action="" method="GET" class="flex items-center space-x-4">
                        <label for="bulan" class="text-sm font-medium text-gray-700">Riwayat Transaksi Bulan:</label>
                        <input type="month" id="bulan" name="bulan" value="<?php echo htmlspecialchars($bulan_filter); ?>" class="px-3 py-2 border rounded-md text-sm bg-gray-100 focus:ring-blue-500 focus:border-blue-500 focus:outline-none">
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">Filter</button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Riwayat Transaksi</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaksi</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Pelanggan</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Transaksi</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Harga</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-center">
                                <?php if ($result_transaksi->num_rows > 0) : ?>
                                    <?php while ($row = $result_transaksi->fetch_assoc()) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['id_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['nama_customer']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['tanggal_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['jenis_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php 
                                                $statusClass = '';
                                                switch($row['status_transaksi']) {
                                                    case 'selesai':
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'proses':
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'batal':
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                }
                                                ?>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($row['status_transaksi'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <a href="detail_transaksi.php?id=<?php echo htmlspecialchars($row['id_transaksi']); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-1 px-3 rounded-md shadow-sm transition duration-200 text-xs">Detail</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada riwayat transaksi untuk bulan ini.</td>
                                    </tr>
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