<?php
session_start();
include '../koneksi.php'; // Sesuaikan path jika berbeda

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
        header("Location: stok_gudang.php?status=success");
        exit();
    }
}

$namaAkun = "Admin";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Stok Gudang - Thraz Computer</title>
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
                        <a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Pembayaran Service</span>
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
                        <a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                            <tbody class="bg-white divide-y divide-gray-200"> <?php
                                                                                $sql = "SELECT id_barang, nama_barang, stok, harga FROM stok";
                                                                                $result = $koneksi->query($sql);

                                                                                if ($result->num_rows > 0) {
                                                                                    while ($row = $result->fetch_assoc()) {
                                                                                        echo "<tr>";
                                                                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-left'>" . htmlspecialchars($row["id_barang"]) . "</td>";
                                                                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-left'>" . htmlspecialchars($row["nama_barang"]) . "</td>";
                                                                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-left'>Rp " . number_format(htmlspecialchars($row["harga"]), 0, ',', '.') . "</td>";
                                                                                        echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900 td-center'>"; // Menggunakan td-center
                                                                                ?>
                                        <form method="POST" class="flex items-center justify-center space-x-2">
                                            <input type="hidden" name="id_barang" value="<?php echo htmlspecialchars($row['id_barang']); ?>">
                                            <input type="number" name="stok" value="<?php echo htmlspecialchars($row['stok']); ?>" min="0" class="w-20 px-2 py-1 border rounded-md text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <button type="submit" name="update_stok" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition duration-200 shadow-sm">Update</button>
                                        </form>
                                <?php
                                                                                        echo "</td>";
                                                                                        echo "</tr>";
                                                                                    }
                                                                                } else {
                                                                                    echo "<tr><td colspan='4' class='px-6 py-4 text-center text-sm text-gray-500'>Belum ada data stok barang.</td></tr>";
                                                                                }
                                ?>
                            </tbody>
                        </table>
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