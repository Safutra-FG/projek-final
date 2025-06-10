<?php
session_start();
// Gunakan koneksi terpusat, sama seperti stok.php
require '../koneksi.php';

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Inisialisasi variabel
$pesan = '';
$pesan_tipe = '';
$data_barang = null;

// Tentukan folder upload yang KONSISTEN dengan stok.php
$upload_dir = '../uploads/';

// Ambil data barang berdasarkan ID dari URL
if (isset($_GET['id'])) {
    $id_barang = intval($_GET['id']);

    // Ambil data barang yang akan diedit
    $stmt_get = $koneksi->prepare("SELECT id_barang, nama_barang, stok, harga, gambar FROM stok WHERE id_barang = ?");
    $stmt_get->bind_param("i", $id_barang);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $data_barang = $result->fetch_assoc();
    $stmt_get->close();

    if (!$data_barang) {
        // Jika data tidak ada, set pesan error dan hentikan
        $pesan = "Data barang dengan ID tersebut tidak ditemukan.";
        $pesan_tipe = 'error';
    }
} else {
    // Jika tidak ada ID di URL, kembali ke halaman stok
    header("Location: stok.php");
    exit();
}

// Proses form saat tombol "Update Barang" ditekan
if (isset($_POST['update']) && $data_barang) {
    // 1. Ambil data dari form
    $nama = trim($_POST['nama_barang']);
    $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
    $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
    $id_barang = intval($_POST['id_barang']); // Ambil ID dari hidden input

    // 2. Simpan nama gambar lama sebagai default
    $nama_gambar_untuk_db = $data_barang['gambar'];

    // 3. Validasi input
    if (empty($nama)) {
        $pesan = "Nama barang tidak boleh kosong!";
        $pesan_tipe = 'error';
    } elseif ($stok === false || $stok < 0) {
        $pesan = "Stok harus berupa angka non-negatif.";
        $pesan_tipe = 'error';
    } elseif ($harga === false || $harga < 0) {
        $pesan = "Harga harus berupa angka non-negatif.";
        $pesan_tipe = 'error';
    } else {
        // 4. Proses jika ada gambar baru yang diunggah
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['gambar'];
            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            // Validasi tipe dan ukuran file
            if (!in_array($file_ext, $allowed_ext)) {
                $pesan = "Format file tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.";
                $pesan_tipe = 'error';
            } elseif ($file_info['size'] > 2 * 1024 * 1024) { // Maks 2MB
                $pesan = "Ukuran file terlalu besar. Maksimal 2MB.";
                $pesan_tipe = 'error';
            } else {
                // Buat nama file unik
                $nama_gambar_baru = uniqid() . '-' . time() . '.' . $file_ext;
                $tujuan_path = $upload_dir . $nama_gambar_baru;

                // Pindahkan file baru
                if (move_uploaded_file($file_info['tmp_name'], $tujuan_path)) {
                    // Jika berhasil, hapus gambar lama dari server (jika ada)
                    if (!empty($data_barang['gambar'])) {
                        $path_gambar_lama = $upload_dir . $data_barang['gambar'];
                        if (file_exists($path_gambar_lama)) {
                            unlink($path_gambar_lama);
                        }
                    }
                    // Update nama gambar yang akan disimpan ke DB
                    $nama_gambar_untuk_db = $nama_gambar_baru;
                } else {
                    $pesan = "Gagal memindahkan file gambar yang diunggah.";
                    $pesan_tipe = 'error';
                }
            }
        }

        // 5. Lakukan update ke database HANYA jika tidak ada error sebelumnya
        if (empty($pesan)) {
            $stmt_update = $koneksi->prepare("UPDATE stok SET nama_barang = ?, stok = ?, harga = ?, gambar = ? WHERE id_barang = ?");
            $stmt_update->bind_param("sidsi", $nama, $stok, $harga, $nama_gambar_untuk_db, $id_barang);

            if ($stmt_update->execute()) {
                // Redirect ke halaman stok dengan status sukses
                header("Location: stok.php?status=success_update");
                exit();
            } else {
                $pesan = "Gagal memperbarui data barang: " . $stmt_update->error;
                $pesan_tipe = 'error';
            }
            $stmt_update->close();
        }
    }
     // Jika ada error, ambil lagi data terbaru untuk ditampilkan (jika perlu)
    $data_barang['nama_barang'] = $nama;
    $data_barang['stok'] = $stok;
    $data_barang['harga'] = $harga;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stok Barang - Thraz Computer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-lg bg-white p-8 rounded-xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Edit Sparepart</h2>

        <?php if ($pesan): ?>
        <div class="mb-5 px-4 py-3 rounded-lg text-sm <?php echo ($pesan_tipe === 'error') ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?>" role="alert">
            <span class="font-medium"><?php echo htmlspecialchars($pesan); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($data_barang): ?>
        <form method="POST" action="edit_stok.php?id=<?= $data_barang['id_barang']; ?>" enctype="multipart/form-data">
            <input type="hidden" name="id_barang" value="<?= $data_barang['id_barang']; ?>">
            
            <div class="space-y-6">
                <div>
                    <label for="nama_barang" class="block text-sm font-medium text-gray-700 font-bold">Nama Barang</label>
                    <div class="mt-1">
                        <input type="text" id="nama_barang" name="nama_barang" value="<?= htmlspecialchars($data_barang['nama_barang']) ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="stok" class="block text-sm font-medium text-gray-700 font-bold">Stok</label>
                    <div class="mt-1">
                        <input type="number" id="stok" name="stok" value="<?= htmlspecialchars($data_barang['stok']) ?>" required min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="harga" class="block text-sm font-medium text-gray-700 font-bold">Harga</label>
                    <div class="mt-1">
                        <input type="number" id="harga" name="harga" value="<?= htmlspecialchars($data_barang['harga']) ?>" required min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="gambar" class="block text-sm font-medium text-gray-700 font-bold">Gambar Barang</label>
                    <div class="mt-2">
                        <?php
                        // Cek jika ada nama gambar di database
                        if (!empty($data_barang['gambar'])):
                            // Bangun path lengkap ke gambar
                            $path_gambar_sekarang = $upload_dir . $data_barang['gambar'];
                            // Cek jika file benar-benar ada di server
                            if(file_exists($path_gambar_sekarang)):
                        ?>
                                <img src="<?= htmlspecialchars($path_gambar_sekarang) ?>" alt="Gambar Barang Saat Ini" class="mb-2 max-h-40 w-auto rounded-md border">
                        <?php 
                            endif;
                        endif; 
                        ?>
                        
                        <p class="text-xs text-gray-500 mb-2">Unggah gambar baru untuk mengganti gambar saat ini. Biarkan kosong jika tidak ingin mengubah.</p>
                        <input type="file" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" id="gambar" name="gambar" accept="image/png, image/jpeg, image/jpg, image/gif">
                        <p class="mt-1 text-xs text-gray-500">Tipe: PNG, JPG, GIF (MAX. 2MB).</p>
                    </div>
                </div>

            </div>

            <div class="mt-8 flex items-center justify-end space-x-4">
                <a href="stok.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Batal
                </a>
                <button type="submit" name="update" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-save mr-2"></i>Update Barang
                </button>
            </div>
        </form>
        <?php elseif(empty($pesan)): ?>
            <p class="text-center text-red-500">ID barang tidak valid atau tidak diberikan.</p>
            <div class="text-center mt-4">
                <a href="stok.php" class="text-blue-600 hover:underline">Kembali ke Daftar Stok</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
<?php
$koneksi->close();
?>