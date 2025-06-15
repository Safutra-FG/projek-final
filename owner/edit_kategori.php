<?php
session_start();
require '../koneksi.php';

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Inisialisasi variabel
$pesan = '';
$pesan_tipe = '';
$data_kategori = null;

// Ambil data kategori berdasarkan ID dari URL
if (isset($_GET['id'])) {
    $id_kategori = intval($_GET['id']);

    // Ambil data kategori yang akan diedit dari tabel 'kategori'
    $stmt_get = $koneksi->prepare("SELECT id_kategori, jenis_kategori, deskripsi FROM kategori WHERE id_kategori = ?");
    $stmt_get->bind_param("i", $id_kategori);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $data_kategori = $result->fetch_assoc();
    $stmt_get->close();

    if (!$data_kategori) {
        // Jika data tidak ada, kembali ke halaman utama dengan pesan error
        $_SESSION['pesan'] = ['teks' => 'Data kategori dengan ID tersebut tidak ditemukan.', 'tipe' => 'error'];
        header("Location: kelola_kategori.php");
        exit();
    }
} else {
    // Jika tidak ada ID di URL, kembali ke halaman kelola_kategori
    header("Location: kelola_kategori.php");
    exit();
}

// Proses form saat tombol "Update Kategori" ditekan
if (isset($_POST['update_kategori']) && $data_kategori) {
    // 1. Ambil data dari form
    $jenis_kategori = trim($_POST['jenis_kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    $id_kategori = intval($_POST['id_kategori']); 

    // 2. Validasi input
    if (empty($jenis_kategori)) {
        $pesan = "Nama kategori tidak boleh kosong!";
        $pesan_tipe = 'error';
    } else {
        // 3. Lakukan update ke database
        $stmt_update = $koneksi->prepare("UPDATE kategori SET jenis_kategori = ?, deskripsi = ? WHERE id_kategori = ?");
        $stmt_update->bind_param("ssi", $jenis_kategori, $deskripsi, $id_kategori);

        if ($stmt_update->execute()) {
            // Set session untuk pesan sukses dan redirect
            $_SESSION['pesan'] = ['teks' => 'Data kategori berhasil diperbarui!', 'tipe' => 'success'];
            header("Location: kelola_kategori.php");
            exit();
        } else {
            $pesan = "Gagal memperbarui data kategori: " . $stmt_update->error;
            $pesan_tipe = 'error';
        }
        $stmt_update->close();
    }
    
    // Jika ada error, perbarui variabel agar nilai yang salah tetap tampil di form
    $data_kategori['jenis_kategori'] = $jenis_kategori;
    $data_kategori['deskripsi'] = $deskripsi;
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kategori - Thar'z Computer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-lg bg-white p-8 rounded-xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Edit Kategori</h2>

        <?php if ($pesan): ?>
        <div class="mb-5 px-4 py-3 rounded-lg text-sm <?php echo ($pesan_tipe === 'error') ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?>" role="alert">
            <span class="font-medium"><?php echo htmlspecialchars($pesan); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($data_kategori): ?>
        <form method="POST" action="edit_kategori.php?id=<?= $data_kategori['id_kategori']; ?>">
            <input type="hidden" name="id_kategori" value="<?= $data_kategori['id_kategori']; ?>">
            
            <div class="space-y-6">
                <div>
                    <label for="jenis_kategori" class="block text-sm font-medium text-gray-700">Nama Kategori</label>
                    <div class="mt-1">
                        <input type="text" id="jenis_kategori" name="jenis_kategori" value="<?= htmlspecialchars($data_kategori['jenis_kategori']) ?>" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-gray-700">Deskripsi</label>
                    <div class="mt-1">
                        <textarea id="deskripsi" name="deskripsi" rows="4" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?= htmlspecialchars($data_kategori['deskripsi']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end space-x-4">
                <a href="kelola_kategori.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" name="update_kategori" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Update Kategori
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

</body>
</html>