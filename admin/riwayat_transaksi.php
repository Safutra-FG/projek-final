<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();

// Logika untuk filtering bulan (jika ada) akan tetap di sini
$bulan_filter = $_GET['bulan'] ?? date('Y-m'); // Default bulan saat ini (YYYY-MM)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Konfigurasi pagination
$items_per_page = 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Siapkan bagian WHERE untuk pencarian
$where_search = '';
$param_types = 's';
$params = [$bulan_filter];
if ($search !== '') {
    $where_search = " AND (c.nama_customer LIKE CONCAT('%', ?, '%') OR t.id_transaksi LIKE CONCAT('%', ?, '%'))";
    $param_types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}

$sql_transaksi = "
    WITH RankedTransaksi AS (
        SELECT
            t.id_transaksi,
            c.nama_customer,
            t.tanggal AS tanggal_transaksi,
            t.jenis AS jenis_transaksi,
            t.total AS total_harga,
            t.status AS status_transaksi,
            
            -- Menggunakan kolom 'id_service' dari tabel Anda
            t.id_service,

            ROW_NUMBER() OVER(
                PARTITION BY 
                    CASE 
                        -- Jika jenisnya 'service', kelompokkan berdasarkan id_service
                        WHEN t.jenis = 'service' THEN t.id_service 
                        -- Jika jenis lain, anggap setiap transaksi unik
                        ELSE t.id_transaksi 
                    END 
                -- Urutkan berdasarkan tanggal & ID terbaru untuk mendapatkan yg paling akhir
                ORDER BY t.tanggal DESC, t.id_transaksi DESC
            ) as rn
        FROM
            transaksi t
        JOIN
            customer c ON t.id_customer = c.id_customer
        WHERE
            DATE_FORMAT(t.tanggal, '%Y-%m') = ?
            $where_search
    ),
    FilteredTransaksi AS (
        SELECT
            id_transaksi,
            nama_customer,
            tanggal_transaksi,
            jenis_transaksi,
            total_harga,
            status_transaksi
        FROM
            RankedTransaksi
        WHERE
            rn = 1
    )
    SELECT 
        SQL_CALC_FOUND_ROWS
        *
    FROM 
        FilteredTransaksi
    ORDER BY
        tanggal_transaksi DESC
    LIMIT ? OFFSET ?;
";

// Siapkan parameter untuk bind_param
$param_types .= 'ii';
$params[] = $items_per_page;
$params[] = $offset;

$stmt_transaksi = $koneksi->prepare($sql_transaksi);

if ($stmt_transaksi) {
    $stmt_transaksi->bind_param($param_types, ...$params);
    $stmt_transaksi->execute();
    $result_transaksi = $stmt_transaksi->get_result();
    
    // Hitung total data
    $total_result = $koneksi->query("SELECT FOUND_ROWS() as total");
    $total_row = $total_result->fetch_assoc();
    $total_items = $total_row['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
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
                    <form action="" method="GET" class="flex flex-wrap items-center gap-4">
                        <label for="bulan" class="text-sm font-medium text-gray-700">Riwayat Transaksi Bulan:</label>
                        <input type="month" id="bulan" name="bulan" value="<?php echo htmlspecialchars($bulan_filter); ?>" class="px-3 py-2 border rounded-md text-sm bg-gray-100 focus:ring-blue-500 focus:border-blue-500 focus:outline-none">
                        <input type="text" name="search" placeholder="Cari nama pelanggan atau ID transaksi..." value="<?php echo htmlspecialchars($search); ?>" class="px-3 py-2 border rounded-md text-sm bg-gray-100 focus:ring-blue-500 focus:border-blue-500 focus:outline-none" style="min-width:220px;">
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
                                                switch ($row['status_transaksi']) {
                                                    case 'lunas':
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'menunggu pembayaran':
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
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
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada riwayat transaksi untuk bulan ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4 flex justify-center">
                        <div class="flex space-x-2">
                            <?php if ($page > 1) : ?>
                                <a href="?page=<?php echo $page - 1; ?>&bulan=<?php echo urlencode($bulan_filter); ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">&laquo; Sebelumnya</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <a href="?page=<?php echo $i; ?>&bulan=<?php echo urlencode($bulan_filter); ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 <?php echo $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-md transition duration-200">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages) : ?>
                                <a href="?page=<?php echo $page + 1; ?>&bulan=<?php echo urlencode($bulan_filter); ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">Selanjutnya &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>

</html>