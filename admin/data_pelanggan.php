<?php
session_start();
include '../koneksi.php'; // Sesuaikan path jika berbeda

$namaAkun = "Admin"; // Kamu bisa mengambil nama akun dari session jika sudah ada
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

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
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
                &copy; Thar'z Computer 2025
            </div>
        </div>

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
                                $sql = "SELECT 
                                    c.id_customer,
                                    c.nama_customer,
                                    c.no_telepon,
                                    c.email,
                                    COUNT(DISTINCT s.id_service) AS total_service,
                                    COUNT(DISTINCT t.id_transaksi) AS total_pembelian
                                FROM customer c
                                LEFT JOIN service s ON c.id_customer = s.id_customer
                                LEFT JOIN transaksi t ON c.id_customer = t.id_customer
                                GROUP BY c.id_customer, c.nama_customer, c.no_telepon, email
                                ORDER BY c.id_customer ASC";

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

                                $koneksi->close(); // Pastikan koneksi ditutup di akhir skrip
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