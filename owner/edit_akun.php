<?php
session_start();
// 1. Menggunakan file koneksi terpusat
require '../koneksi.php'; 

// Cek role harus owner (setelah koneksi berhasil)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    // Arahkan ke halaman login jika tidak ada akses
    header("Location: ../login.php"); 
    exit();
}

// Inisialisasi variabel untuk pesan notifikasi di halaman ini
$pesan = '';
$pesan_tipe = '';
$edit_data = null;

// Pastikan ada ID yang dikirim dan valid
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Validasi ID sebagai integer

    // Ambil data akun yang akan diedit dari tabel 'user'
    $stmt_get = $koneksi->prepare("SELECT id_user, username, role FROM user WHERE id_user = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $edit_data = $result->fetch_assoc();
    $stmt_get->close();

    // Jika akun dengan ID tersebut tidak ditemukan, redirect dengan pesan error
    if (!$edit_data) {
        $_SESSION['pesan'] = ['teks' => 'Akun dengan ID tersebut tidak ditemukan.', 'tipe' => 'error'];
        header("Location: register.php");
        exit();
    }
} else {
    // Jika tidak ada ID di URL, kembali ke halaman utama
    header("Location: register.php");
    exit();
}

// Proses form saat tombol "Update Akun" ditekan
if (isset($_POST['update'])) {
    // Ambil data dari form
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = $_POST['password']; // Tidak perlu di-trim
    $id_user = intval($_POST['id_user']);

    // Validasi input dasar
    if (empty($username) || empty($role)) {
        $pesan = "Username dan Role tidak boleh kosong!";
        $pesan_tipe = 'error';
    } else {
        // Logika update: Hanya update password jika field diisi
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = $koneksi->prepare("UPDATE user SET username=?, password=?, role=? WHERE id_user=?");
            $stmt_update->bind_param("sssi", $username, $hashed, $role, $id_user);
        } else {
            $stmt_update = $koneksi->prepare("UPDATE user SET username=?, role=? WHERE id_user=?");
            $stmt_update->bind_param("ssi", $username, $role, $id_user);
        }

        // Eksekusi query update
        if ($stmt_update->execute()) {
            // Set session untuk pesan sukses dan redirect ke halaman utama
            $_SESSION['pesan'] = ['teks' => 'Akun berhasil diupdate!', 'tipe' => 'success'];
            header("Location: register.php");
            exit();
        } else {
            // Jika gagal, tampilkan pesan error di halaman ini
            $pesan = "Gagal mengupdate akun: " . $stmt_update->error;
            $pesan_tipe = 'error';
        }
        $stmt_update->close();
    }
    
    // Jika ada error, perbarui variabel agar nilai yang salah tetap tampil di form
    $edit_data['username'] = $username;
    $edit_data['role'] = $role;
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Akun - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-lg bg-white p-8 rounded-xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Edit Akun</h2>
        
        <?php if ($pesan): ?>
        <div class="mb-5 px-4 py-3 rounded-lg text-sm <?php echo ($pesan_tipe === 'error') ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?>" role="alert">
            <span class="font-medium"><?php echo htmlspecialchars($pesan); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($edit_data): ?>
        <form method="POST" action="edit_akun.php?id=<?= $edit_data['id_user']; ?>">
            <input type="hidden" name="id_user" value="<?= $edit_data['id_user']; ?>">
            
            <div class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <div class="mt-1">
                        <input type="text" id="username" name="username" required value="<?= htmlspecialchars($edit_data['username']) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password Baru</label>
                    <div class="mt-1">
                        <input type="password" id="password" name="password" placeholder="Kosongkan jika tidak ingin diubah" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <div class="mt-1">
                        <?php if ($edit_data['role'] == 'owner'): // Jika yang diedit adalah owner ?>
                            <input type="text" value="Owner" class="block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm" readonly>
                            <input type="hidden" name="role" value="owner">
                            <p class="mt-2 text-xs text-gray-500">Role Owner tidak dapat diubah.</p>
                        <?php else: // Jika yang diedit adalah admin atau teknisi ?>
                            <select id="role" name="role" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="admin" <?= ($edit_data['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="teknisi" <?= ($edit_data['role'] == 'teknisi') ? 'selected' : '' ?>>Teknisi</option>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end space-x-4">
                <a href="register.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Kembali
                </a>
                <button type="submit" name="update" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Update Akun
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

</body>
</html>