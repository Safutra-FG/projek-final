<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Variabel untuk menyimpan pesan notifikasi
$pesan = ''; 
$pesan_sukses = false;
$data_barang = null; // Variabel untuk menampung data barang

// Ambil data barang berdasarkan ID
if (isset($_GET['id'])) {
    $id_barang = intval($_GET['id']); // Amankan ID
    
    // Gunakan prepared statement untuk mengambil data
    $stmt_get = $koneksi->prepare("SELECT * FROM stok WHERE id_barang = ?");
    $stmt_get->bind_param("i", $id_barang);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $data_barang = $result->fetch_assoc();
    $stmt_get->close();

    // Jika data tidak ditemukan
    if (!$data_barang) {
        $pesan = "Data barang tidak ditemukan.";
        $pesan_sukses = false;
    }
} else {
    $pesan = "ID barang tidak valid.";
    $pesan_sukses = false;
    // Arahkan kembali jika tidak ada ID
    // header("Location: stok.php");
    // exit();
}

// Proses update data
if (isset($_POST['update']) && $data_barang) {
    $nama = trim($_POST['nama_barang']);
    $stok = $_POST['stok'];
    $harga = $_POST['harga'];

    // Validasi input
    if (empty($nama)) {
        $pesan = "Nama barang tidak boleh kosong!";
        $pesan_sukses = false;
    } elseif (!is_numeric($stok) || $stok < 0) {
        $pesan = "Stok harus berupa angka positif!";
        $pesan_sukses = false;
    } elseif (!is_numeric($harga) || $harga < 0) {
        $pesan = "Harga harus berupa angka positif!";
        $pesan_sukses = false;
    } else {
        $stmt_update = $koneksi->prepare("UPDATE stok SET nama_barang = ?, stok = ?, harga = ? WHERE id_barang = ?");
        // 'sidi' : string, integer, double, integer
        $stmt_update->bind_param("sidi", $nama, $stok, $harga, $id_barang);

        if ($stmt_update->execute()) {
            $pesan = "Barang berhasil diupdate!";
            $pesan_sukses = true;
            
            // Ambil lagi data terbaru untuk ditampilkan di form
            $stmt_get_new = $koneksi->prepare("SELECT * FROM stok WHERE id_barang = ?");
            $stmt_get_new->bind_param("i", $id_barang);
            $stmt_get_new->execute();
            $data_barang = $stmt_get_new->get_result()->fetch_assoc();
            $stmt_get_new->close();

        } else {
            $pesan = "Gagal mengupdate barang: " . $stmt_update->error;
            $pesan_sukses = false;
        }
        $stmt_update->close();
    }
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
        <div class="mb-5 px-4 py-3 rounded-lg text-sm <?php echo $pesan_sukses ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?>" role="alert">
            <span class="font-medium"><?php echo htmlspecialchars($pesan); ?></span>
            <?php if ($pesan_sukses): ?>
                <a href="stok.php" class="underline ml-2">Kembali ke Daftar Stok</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($data_barang): ?>
        <form method="POST" action="">
            <div class="space-y-6">
                <div>
                    <label for="nama_barang" class="block text-sm font-medium text-gray-700">Nama Barang</label>
                    <div class="mt-1">
                        <input type="text" id="nama_barang" name="nama_barang" value="<?= htmlspecialchars($data_barang['nama_barang']) ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="stok" class="block text-sm font-medium text-gray-700">Stok</label>
                    <div class="mt-1">
                        <input type="number" id="stok" name="stok" value="<?= htmlspecialchars($data_barang['stok']) ?>" required min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="harga" class="block text-sm font-medium text-gray-700">Harga</label>
                    <div class="mt-1">
                        <input type="number" id="harga" name="harga" value="<?= htmlspecialchars($data_barang['harga']) ?>" required min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
        <?php endif; ?>
    </div>
    <div>
        
    </div>

</body>
</html>
<?php
$koneksi->close();
?>