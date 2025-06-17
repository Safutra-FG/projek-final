<?php
// register.php (MODIFIED WITH EDIT MODAL)
session_start();
include '../koneksi.php';

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Cek role harus owner (contoh session check)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    // Untuk pengembangan, kita asumsikan role owner ada
    // Jika tidak, uncomment baris di bawah untuk redirect
    // header("Location: ../login.php");
    // exit();
}

$pesan = ''; // Inisialisasi variabel pesan notifikasi
$namaAkun = "Owner"; // Mengatur nama akun sebagai Owner

// --- LOGIKA CRUD ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Proses Tambah Akun dari Modal
    if (isset($_POST['tambah_akun'])) {
        $username = trim($_POST['username']);
        $password_input = $_POST['password'];
        $role = $_POST['role'];

        if (empty($username) || empty($password_input)) {
            $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Username dan Password tidak boleh kosong!</div>';
        } else {
            // Cek apakah username sudah ada
            $cek = $koneksi->prepare("SELECT id_user FROM user WHERE username = ?");
            $cek->bind_param("s", $username);
            $cek->execute();
            $cek_result = $cek->get_result();

            if ($cek_result->num_rows > 0) {
                $pesan = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">Nama pengguna sudah digunakan!</div>';
            } else {
                $password_hashed = password_hash($password_input, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $password_hashed, $role);
                if ($stmt->execute()) {
                    $pesan = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Akun berhasil ditambahkan.</div>';
                } else {
                    $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal membuat akun: ' . $stmt->error . '</div>';
                }
                $stmt->close();
            }
            $cek->close();
        }
    }
    
    // BARU: Proses Edit Akun dari Modal
    if (isset($_POST['edit_akun'])) {
        $id_user = $_POST['id_user'];
        $username = trim($_POST['username']);
        $password_input = $_POST['password'];
        $role = $_POST['role'];

        if (empty($username) || empty($role)) {
             $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Username dan Role tidak boleh kosong!</div>';
        } else {
            // Hanya update password jika field diisi
            if (!empty($password_input)) {
                $hashed = password_hash($password_input, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("UPDATE user SET username=?, password=?, role=? WHERE id_user=?");
                $stmt->bind_param("sssi", $username, $hashed, $role, $id_user);
            } else {
                // Jangan update password jika kosong
                $stmt = $koneksi->prepare("UPDATE user SET username=?, role=? WHERE id_user=?");
                $stmt->bind_param("ssi", $username, $role, $id_user);
            }

            if ($stmt->execute()) {
                $pesan = '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">Akun berhasil diperbarui.</div>';
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal memperbarui akun: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }

    // Proses Hapus Akun (disarankan menggunakan POST untuk keamanan)
    if (isset($_POST['hapus_akun'])) {
        $id = $_POST['id_user'];
        // Pastikan owner tidak bisa dihapus
        $stmt = $koneksi->prepare("DELETE FROM user WHERE id_user = ? AND role != 'owner'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $pesan = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">Akun berhasil dihapus.</div>';
        } else {
            $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menghapus akun: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}


// Mengambil semua data user
$daftar_user = [];
$sqlUsers = "SELECT id_user, username, role FROM user ORDER BY role ASC, username ASC";
$resultUsers = $koneksi->query($sqlUsers);
if ($resultUsers->num_rows > 0) {
    while ($row = $resultUsers->fetch_assoc()) {
        $daftar_user[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun - Thraz Computer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
                    <p class="text-sm text-gray-400">Owner Panel</p>
                </div>
                <ul class="px-6 space-y-3">
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-home w-6 text-center"></i><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200"><i class="fas fa-users w-6 text-center"></i><span class="font-medium">Kelola Akun</span></a></li>
                    <li><a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-wrench w-6 text-center"></i><span class="font-medium">Kelola Sparepart</span></a></li>
                    <li><a href="kelola_kategori.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-tags w-6 text-center"></i><span class="font-medium">Kelola Kategori</span></a></li>
                    <li><a href="kelola_jasa.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-concierge-bell w-6 text-center"></i><span class="font-medium">Kelola Jasa</span></a></li>
                    <li><a href="laporan_keuangan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-chart-line w-6 text-center"></i><span class="font-medium">Laporan Keuangan</span></a></li>
                    <li><a href="laporan_sparepart.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-boxes w-6 text-center"></i><span class="font-medium">Laporan Stok Barang</span></a></li>
                    <li><a href="laporan_pesanan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-clipboard-list w-6 text-center"></i><span class="font-medium">Laporan Pesanan</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">&copy; Thar'z Computer 2025</div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Data Akun</h2>
                <div class="flex items-center space-x-5">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-circle text-xl text-gray-600"></i>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-y-auto">
                <button onclick="openModal('addModal')" class="mb-6 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                    <i class="fas fa-plus-circle mr-2"></i>Tambah Akun Baru
                </button>

                <?php echo $pesan; // Menampilkan pesan notifikasi ?>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Akun Pengguna</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($daftar_user)) : ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">Belum ada data akun.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php $no = 1;
                                    foreach ($daftar_user as $user) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $no++; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">

                                                <button onclick="openModal('editModal', <?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-indigo-600 hover:text-indigo-900 transition duration-150 ease-in-out" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <?php if ($user['role'] !== 'owner'): ?>
                                                    <button onclick="openModal('deleteModal', <?php echo htmlspecialchars(json_encode($user)); ?>)" class="ml-4 text-red-600 hover:text-red-900 transition duration-150 ease-in-out" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="ml-4 text-gray-400 cursor-not-allowed" title="Owner tidak dapat dihapus"><i class="fas fa-user-shield"></i></span>
                                                <?php endif; ?>
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

    <div id="addModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="register.php">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tambah Akun Baru</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="username_add" class="block text-sm font-medium text-gray-700">Username</label>
                                <input type="text" name="username" id="username_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Masukkan username">
                            </div>
                            <div>
                                <label for="password_add" class="block text-sm font-medium text-gray-700">Password</label>
                                <input type="password" name="password" id="password_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Masukkan password">
                            </div>
                            <div>
                                <label for="role_add" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="role" id="role_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="admin">Admin</option>
                                    <option value="teknisi">Teknisi</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="tambah_akun" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                        <button type="button" onclick="closeModal('addModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="editModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="register.php">
                    <input type="hidden" name="id_user" id="id_user_edit">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Data Akun</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="username_edit" class="block text-sm font-medium text-gray-700">Username</label>
                                <input type="text" name="username" id="username_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="password_edit" class="block text-sm font-medium text-gray-700">Password Baru</label>
                                <input type="password" name="password" id="password_edit" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Kosongkan jika tidak ingin diubah">
                            </div>
                            <div>
                                <label for="role_edit" class="block text-sm font-medium text-gray-700">Role</label>
                                <div id="role_edit_container">
                                    </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="edit_akun" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Perbarui</button>
                        <button type="button" onclick="closeModal('editModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="register.php">
                    <input type="hidden" name="id_user" id="id_user_delete">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Data Akun</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus akun "<strong id="username_delete"></strong>"? Tindakan ini tidak dapat dibatalkan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="hapus_akun" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Hapus</button>
                        <button type="button" onclick="closeModal('deleteModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (modal) {
                if (data) {
                    // DIUBAH: Tambah logika untuk 'editModal'
                    if (modalId === 'editModal') {
                        document.getElementById('id_user_edit').value = data.id_user;
                        document.getElementById('username_edit').value = data.username;
                        document.getElementById('password_edit').value = ''; // Kosongkan password

                        // Logika untuk role: jika owner, buat read-only. jika bukan, buat dropdown.
                        const roleContainer = document.getElementById('role_edit_container');
                        if (data.role === 'owner') {
                            roleContainer.innerHTML = `
                                <input type="text" value="Owner" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100" readonly>
                                <input type="hidden" name="role" value="owner">
                                <p class="mt-1 text-xs text-gray-500">Role Owner tidak dapat diubah.</p>
                            `;
                        } else {
                            roleContainer.innerHTML = `
                                <select name="role" id="role_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="admin">Admin</option>
                                    <option value="teknisi">Teknisi</option>
                                </select>
                            `;
                            // Set selected value untuk dropdown
                            document.getElementById('role_edit').value = data.role;
                        }

                    }
                    
                    // Logika untuk modal hapus yang sudah ada
                    else if (modalId === 'deleteModal') {
                        document.getElementById('id_user_delete').value = data.id_user;
                        document.getElementById('username_delete').textContent = data.username;
                    }
                }
                modal.classList.remove('hidden');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        }
    </script>
</body>

</html>
<?php
$koneksi->close();
?>