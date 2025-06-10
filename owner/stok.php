<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Cek role harus owner (jika ada otentikasi)
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
//     header("Location: ../index.php"); // Pastikan path ke index.php benar
//     exit();
// }

$namaAkun = "Owner"; // Mengatur nama akun sebagai Owner

$pesan = ''; // Inisialisasi $pesan di awal agar tidak undefined

// Proses form tambah barang
if (isset($_POST['submit'])) {
    $nama = trim($_POST['nama_barang']);
    $stok = $_POST['stok'];
    $harga = $_POST['harga'];

    // Validasi input
    if (empty($nama)) {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Nama barang tidak boleh kosong!</div>";
    } elseif (!is_numeric($stok) || $stok < 0) {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Stok harus berupa angka positif!</div>";
    } elseif (!is_numeric($harga) || $harga < 0) {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Harga harus berupa angka positif!</div>";
    } else {
        $stmt = $koneksi->prepare("INSERT INTO stok (nama_barang, stok, harga) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama, $stok, $harga);

        if ($stmt->execute()) {
            // Redirect ke halaman yang sama untuk membersihkan parameter POST dari URL
            header("Location: stok.php?status=success_add"); // Tambahkan status untuk pesan
            exit();
        } else {
            $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Proses hapus barang
if (isset($_GET['hapus'])) {
    $id_barang = $_GET['hapus'];
    $stmt = $koneksi->prepare("DELETE FROM stok WHERE id_barang = ?");
    $stmt->bind_param("i", $id_barang);
    if ($stmt->execute()) {
        // Redirect ke halaman yang sama setelah hapus
        header("Location: stok.php?status=success_delete"); // Tambahkan status untuk pesan
        exit();
    } else {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Gagal menghapus barang: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Mengambil pesan dari URL setelah redirect (misal dari tambah/hapus)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_add') {
        $pesan = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Barang berhasil ditambahkan!</div>";
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Barang berhasil dihapus!</div>";
    }
}

// --- Logika untuk pencarian dan pengambilan data tabel ---
// Ambil nilai dari input pencarian
$search_nama = isset($_GET['search_nama']) ? $koneksi->real_escape_string($_GET['search_nama']) : '';

// Bangun klausa WHERE untuk query
$where_clause_stok = "WHERE 1=1"; // Kondisi awal yang selalu benar
if (!empty($search_nama)) { // Jika ada input pencarian, tambahkan kondisi LIKE
    $where_clause_stok .= " AND nama_barang LIKE '%" . $search_nama . "%'";
}

// Query untuk mengambil data barang (sudah difilter jika ada input pencarian)
$sqlStokBarang = "SELECT
                     id_barang,
                     nama_barang,
                     stok,
                     harga
                   FROM
                     stok
                   " . $where_clause_stok . "
                   ORDER BY
                     nama_barang ASC";

$resultStokBarang = $koneksi->query($sqlStokBarang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Sparepart - Thraz Computer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thraz Computer</h1>
                    <p class="text-sm text-gray-400">Owner Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
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
                        <a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-wrench w-6 text-center"></i>
                            <span class="font-medium">Kelola Sparepart</span>
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
                &copy; Thraz Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Sparepart</h2>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-circle text-xl text-gray-600"></i>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Tambah Barang Baru</h3>
                    <?php echo $pesan; // Menampilkan pesan dari PHP ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="nama_barang" class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                            <input type="text" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="nama_barang" name="nama_barang" placeholder="Masukkan Nama Barang" value="<?= htmlspecialchars($_POST['nama_barang'] ?? '') ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="stok" class="block text-sm font-medium text-gray-700 mb-1">Stok</label>
                            <input type="number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="stok" name="stok" placeholder="Masukkan Jumlah Stok" min="0" value="<?= htmlspecialchars($_POST['stok'] ?? '') ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="harga" class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                            <input type="number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="harga" name="harga" placeholder="Masukkan Harga Barang" step="0.01" min="0" value="<?= htmlspecialchars($_POST['harga'] ?? '') ?>" required>
                        </div>

                        <div class="flex space-x-3">
                            <button type="submit" name="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Tambah Barang</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Daftar Stok Barang</h3>
                    
                    <div class="bg-gray-50 p-4 rounded-lg shadow-sm mb-4">
                        <h5 class="text-lg font-semibold text-gray-800 mb-3">Cari Barang</h5>
                        <form method="GET" action="stok.php">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <label for="search_nama" class="block text-sm font-medium text-gray-700 mb-1">Cari Nama Barang:</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                        <input type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="search_nama" name="search_nama" value="<?php echo htmlspecialchars($search_nama); ?>" placeholder="Cari nama barang...">
                                    </div>
                                </div>
                                <div class="flex space-x-3">
                                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Cari</button>
                                    <a href="stok.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($resultStokBarang && $resultStokBarang->num_rows > 0): ?>
                                    <?php while($row = $resultStokBarang->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= htmlspecialchars($row['id_barang']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama_barang']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                <?php
                                                    $stokVal = $row['stok'];
                                                    $stokClass = '';
                                                    if ($stokVal <= 5) {
                                                        $stokClass = 'bg-red-100 text-red-800'; // Menipis
                                                    } elseif ($stokVal <= 10) {
                                                        $stokClass = 'bg-yellow-100 text-yellow-800'; // Hampir Habis
                                                    } else {
                                                        $stokClass = 'bg-green-100 text-green-800'; // Cukup
                                                    }
                                                ?>
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $stokClass; ?>">
                                                    <?= htmlspecialchars($stokVal); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-center">
                                                <a href="edit_stok.php?id=<?= $row['id_barang'] ?>" class="text-yellow-600 hover:text-yellow-900 mx-1 px-3 py-1 rounded-md bg-yellow-100 transition duration-200 inline-flex items-center text-xs">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <a href="?hapus=<?= $row['id_barang'] ?>" class="text-red-600 hover:text-red-900 mx-1 px-3 py-1 rounded-md bg-red-100 transition duration-200 inline-flex items-center text-xs" onclick="return confirm('Yakin ingin menghapus barang ini?')">
                                                    <i class="fas fa-trash-alt mr-1"></i>Hapus
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada data barang atau tidak ditemukan hasil pencarian.</td></tr>
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
<?php $koneksi->close(); ?>