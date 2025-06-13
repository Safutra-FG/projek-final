<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();


?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Data Pelanggan - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Data Pelanggan</h2>
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

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Data Pelanggan</h2>

                    <!-- Form Pencarian -->
                    <div class="mb-4">
                        <form method="GET" class="flex gap-2">
                            <input type="text" name="search" placeholder="Cari nama atau nomor HP..."
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                                Cari
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor HP</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Service</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Pembelian</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-center">
                                <?php
                                // Konfigurasi Pagination
                                $items_per_page = 10;
                                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                $offset = ($page - 1) * $items_per_page;

                                // Query dasar
                                $base_query = "FROM customer c
                                    LEFT JOIN service s ON c.id_customer = s.id_customer
                                    LEFT JOIN transaksi t ON c.id_customer = t.id_customer
                                    LEFT JOIN detail_service ds ON s.id_service = ds.id_service
                                    LEFT JOIN stok sp ON ds.id_barang = sp.id_barang";

                                // Tambahkan kondisi pencarian jika ada
                                $where_clause = "";
                                if (isset($_GET['search']) && !empty($_GET['search'])) {
                                    $search = $koneksi->real_escape_string($_GET['search']);
                                    $where_clause = " WHERE c.nama_customer LIKE '%$search%' OR c.no_telepon LIKE '%$search%'";
                                }

                                // Query untuk total data
                                $count_query = "SELECT COUNT(DISTINCT c.id_customer) as total " . $base_query . $where_clause;
                                $total_result = $koneksi->query($count_query);
                                $total_rows = $total_result->fetch_assoc()['total'];
                                $total_pages = ceil($total_rows / $items_per_page);

                                // ... (kode di atasnya biarkan saja)
                                // Query utama dengan pagination (VERSI FINAL)
                                $sql = "SELECT 
                                c.id_customer,
                                c.nama_customer,
                                c.no_telepon,
                                c.email,
                                COUNT(DISTINCT s.id_service) AS total_service,
                                -- HANYA hitung transaksi penjualan produk murni (id_service di tabel transaksi adalah NULL)
                                COUNT(DISTINCT CASE WHEN t.id_service IS NULL THEN t.id_transaksi END) AS total_pembelian
                                " . $base_query . $where_clause . "
                                GROUP BY c.id_customer, c.nama_customer, c.no_telepon, c.email
                                ORDER BY c.id_customer ASC
                                LIMIT $offset, $items_per_page";
                                // ... (kode di bawahnya biarkan saja)

                                $result = $koneksi->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['id_customer']) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['nama_customer']) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['no_telepon']) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['email']) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['total_service']) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['total_pembelian']) . "</td>";
                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>";
                                        echo "<a href='detail_pelanggan.php?id=" . htmlspecialchars($row['id_customer']) . "' class='bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm transition duration-200'>Detail</a>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='px-6 py-4 text-center text-sm text-gray-500'>Belum ada data pelanggan.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-4 flex justify-center">
                                <div class="flex space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>"
                                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">
                                            &larr; Sebelumnya
                                        </a>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>"
                                            class="px-4 py-2 <?php echo $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-md hover:bg-gray-300 transition duration-200">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>"
                                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">
                                            Selanjutnya &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>

</html>