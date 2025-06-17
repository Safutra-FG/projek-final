<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();

// Konfigurasi pagination
$items_per_page = 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Hitung total data
$total_query = "SELECT COUNT(*) as total FROM stok";
$total_result = $koneksi->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Cek kalau form dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_stok'])) {
    $id_barang = $_POST['id_barang'];
    $stok      = $_POST['stok'];

    // Validasi simple (pastikan stok adalah angka non-negatif)
    if (!is_numeric($stok) || $stok < 0) {
        // Anda bisa menambahkan pesan error yang lebih baik di sini
        echo "<script>alert('Stok harus berupa angka non-negatif.');</script>";
    } else {
        $stmt = $koneksi->prepare("UPDATE stok SET stok = ? WHERE id_barang = ?");
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($koneksi->error));
        }
        $stmt->bind_param("ii", $stok, $id_barang);
        if (!$stmt->execute()) {
            die('Execute failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        // Redirect untuk mencegah form resubmission saat refresh
        header("Location: stok_gudang.php?page=" . $page . "&status=success");
        exit();
    }
}

// Query untuk mengambil data dengan limit
$sql = "SELECT id_barang, nama_barang, stok, harga FROM stok ORDER BY id_barang DESC LIMIT ? OFFSET ? ";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Stok Gudang - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS tambahan untuk memastikan kolom "Stok" sejajar */
        /* Mengatur lebar relatif agar fleksibel namun tetap rapi */
        .table-auto-layout {
            table-layout: fixed;
            /* Penting untuk kontrol lebar kolom yang lebih baik */
        }

        .th-col {
            text-align: left;
            /* Header rata kiri */
        }

        .td-left {
            text-align: left;
            /* Sel data rata kiri */
        }

        .td-center {
            text-align: center;
            /* Sel data rata tengah */
            /* Menggunakan justify-center di form flex container sudah cukup untuk kontennya */
        }

        /* Memberi lebar eksplisit pada kolom tertentu */
        .w-id {
            width: 10%;
            min-width: 80px;
        }

        .w-nama {
            width: 30%;
            min-width: 150px;
        }

        .w-harga {
            width: 25%;
            min-width: 100px;
        }

        .w-stok {
            width: 35%;
            min-width: 180px;
        }

        /* Sesuaikan ini jika masih kurang lebar */

        /* Override global text-center dari tbody untuk sel tertentu */
        tbody .px-6 {
            /* agar padding default tetap ada */
            text-align: inherit;
            /* Inherit dari td-left/td-center */
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Stok Gudang</h2>
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
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Stok Barang</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 table-auto-layout">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider th-col w-id">ID Barang</th>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider th-col w-nama">Nama Barang</th>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider th-col w-harga">Harga</th>
                                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center w-stok">Stok</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result->num_rows > 0) : ?>
                                    <?php while ($row = $result->fetch_assoc()) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-left"><?php echo htmlspecialchars($row["id_barang"]); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-left"><?php echo htmlspecialchars($row["nama_barang"]); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-left">Rp <?php echo number_format(htmlspecialchars($row["harga"]), 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-center">
                                                <form method="POST" class="flex items-center justify-center space-x-2">
                                                    <input type="hidden" name="id_barang" value="<?php echo htmlspecialchars($row['id_barang']); ?>">
                                                    <input type="number" name="stok" value="<?php echo htmlspecialchars($row['stok']); ?>" min="0" class="w-20 px-2 py-1 border rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                    <button type="submit" name="update_stok" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition duration-200 shadow-sm">Update</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada data stok barang.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4 flex justify-center">
                        <div class="flex space-x-2">
                            <?php if ($page > 1) : ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">&laquo; Sebelumnya</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <a href="?page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-md transition duration-200">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages) : ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">Selanjutnya &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
<?php
// Tutup koneksi di akhir file, setelah semua operasi database selesai
if (isset($koneksi) && $koneksi instanceof mysqli && $koneksi->ping()) {
    $koneksi->close();
}
?>