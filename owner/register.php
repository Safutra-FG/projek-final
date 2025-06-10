<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Cek role harus owner
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php"); // Pastikan path ke index.php benar
    exit();
}

$pesan = ''; // Inisialisasi $pesan di awal
$namaAkun = "Owner"; // Mengatur nama akun sebagai Owner

// Proses Tambah Akun
if (isset($_POST['tambah'])) {
    $username = trim($_POST['username']);
    $password_input = $_POST['password']; // Ambil password mentah
    $role = $_POST['role'];

    // Validasi input tambah akun
    if (empty($username)) {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Nama pengguna tidak boleh kosong!</div>";
    } elseif (empty($password_input)) {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Password tidak boleh kosong!</div>";
    } else {
        $password_hashed = password_hash($password_input, PASSWORD_DEFAULT); // Hash password

        $cek = $koneksi->prepare("SELECT * FROM user WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        $cek_result = $cek->get_result();

        if ($cek_result->num_rows > 0) {
            $pesan = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4' role='alert'>Nama pengguna sudah digunakan!</div>";
        } else {
            $stmt = $koneksi->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password_hashed, $role);
            if ($stmt->execute()) {
                // Gunakan parameter URL untuk pesan sukses, bukan langsung echo
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=success_add");
                exit();
            } else {
                $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Gagal membuat akun: " . $stmt->error . "</div>";
            }
        }
        $cek->close();
    }
}

// Proses Hapus Akun
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $koneksi->prepare("DELETE FROM user WHERE id_user = ? AND role IN ('admin', 'teknisi')");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success_delete");
        exit();
    } else {
        $pesan = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Gagal menghapus akun: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Mengambil semua data user
$sqlUsers = "SELECT
                id_user,
                username,
                role
              FROM
                user
              ORDER BY
                username ASC";

$resultUsers = $koneksi->query($sqlUsers);

// Mengambil pesan dari URL setelah redirect (misal dari tambah/hapus)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_add') {
        $pesan = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Akun berhasil dibuat!</div>";
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Akun berhasil dihapus!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Akun - Thraz Computer</title>
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
                        <a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                           <i class="fas fa-users w-6 text-center"></i>
                            <span class="font-medium">Kelola Akun</span>
                        </a>
                    </li>
                    <li>
                        <a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
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
                <h2 class="text-2xl font-bold text-gray-800">Kelola Akun</h2>
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-circle text-xl text-gray-600"></i>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Tambah Akun Baru</h3>
                    <?php if ($pesan) echo $pesan; // Menampilkan pesan dari PHP ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="username_tambah" class="block text-sm font-medium text-gray-700 mb-1">Username:</label>
                            <input type="text" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Masukan Username!" id="username_tambah" name="username" required>
                        </div>
                        <div class="mb-4">
                            <label for="password_tambah" class="block text-sm font-medium text-gray-700 mb-1">Password:</label>
                            <input type="password" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Masukan Password!" id="password_tambah" name="password" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="role_tambah">Role:</label>
                            <select class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="role_tambah" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="teknisi">Teknisi</option>
                            </select>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" name="tambah">Buat Akun</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Daftar Akun</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php $no = 1; ?>
                                <?php if ($resultUsers && $resultUsers->num_rows > 0): ?>
                                    <?php while ($row = $resultUsers->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $no++ ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['username']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $row['role'] ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-center">
                                                <a href="edit_akun.php?id=<?= $row['id_user'] ?>" class="text-yellow-600 hover:text-yellow-900 mx-1 px-3 py-1 rounded-md bg-yellow-100 transition duration-200 inline-flex items-center text-xs">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <?php if ($row['role'] !== 'owner'): ?>
                                                    <a href="?delete=<?= $row['id_user'] ?>" class="text-red-600 hover:text-red-900 mx-1 px-3 py-1 rounded-md bg-red-100 transition duration-200 inline-flex items-center text-xs" onclick="return confirm('Yakin hapus akun ini?')">
                                                        <i class="fas fa-trash-alt mr-1"></i>Hapus
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 bg-gray-200 cursor-not-allowed mx-1 px-3 py-1 rounded-md inline-flex items-center text-xs">
                                                        <i class="fas fa-user-shield mr-1"></i>Owner
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data akun.</td>
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
<?php $koneksi->close(); ?>