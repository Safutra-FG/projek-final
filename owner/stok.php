<?php
session_start();
require '../koneksi.php'; // Menyertakan file koneksi database

// --- OTENTIKASI & OTORISASI ---
// Cek jika pengguna memiliki peran 'owner'. Aktifkan jika sistem login sudah ada.
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php"); // Redirect jika bukan owner
    exit();
}
*/
$namaAkun = "Owner"; // Nama akun statis untuk tampilan

// --- INISIALISASI VARIABEL ---
$pesan = '';
$pesan_tipe = ''; // 'success' atau 'error'

// --- PENGELOLAAN PESAN STATUS ---
// Menampilkan pesan setelah operasi (tambah/hapus) berhasil
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_add') {
        $pesan = "Barang baru berhasil ditambahkan!";
        $pesan_tipe = 'success';
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan = "Barang berhasil dihapus.";
        $pesan_tipe = 'success';
    } elseif ($_GET['status'] == 'success_update') { // <-- TAMBAHKAN BLOK INI
        $pesan = "Data barang berhasil diperbarui!";
        $pesan_tipe = 'success';
    } elseif ($_GET['status'] == 'error') {
        $pesan = "Terjadi kesalahan. Silakan coba lagi.";
        $pesan_tipe = 'error';
    }
}

// --- LOGIKA FORM: TAMBAH BARANG ---
if (isset($_POST['submit_add'])) {
    $nama = trim($_POST['nama_barang']);
    $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
    $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
    $nama_gambar = ''; // Variabel untuk menyimpan nama file gambar

    // --- LOGIKA UPLOAD GAMBAR ---
    // Cek apakah ada file yang diunggah dan tidak ada error
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $info_file = $_FILES['gambar'];
        $nama_asli = $info_file['name'];
        $lokasi_tmp = $info_file['tmp_name'];
        $ukuran_file = $info_file['size'];
        $tipe_file_diizinkan = ['jpg', 'jpeg', 'png', 'gif'];
        $ekstensi_file = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));

        // 1. Validasi Ekstensi File
        if (!in_array($ekstensi_file, $tipe_file_diizinkan)) {
            $pesan = "Format file tidak valid. Hanya JPG, JPEG, PNG, dan GIF yang diizinkan.";
            $pesan_tipe = 'error';
        } 
        // 2. Validasi Ukuran File (misal: maks 2MB)
        elseif ($ukuran_file > 2 * 1024 * 1024) { 
            $pesan = "Ukuran file terlalu besar. Maksimal 2MB.";
            $pesan_tipe = 'error';
        } else {
            // 3. Buat nama file yang unik untuk menghindari penimpaan file
            $nama_gambar = uniqid() . '-' . time() . '.' . $ekstensi_file;
            $folder_tujuan = '../uploads/'; // Sesuaikan dengan struktur folder Anda
            $path_tujuan = $folder_tujuan . $nama_gambar;

            // 4. Pindahkan file dari temporary ke folder tujuan
            if (move_uploaded_file($lokasi_tmp, $path_tujuan)) {
                // File berhasil diunggah, lanjutkan proses insert data
            } else {
                $pesan = "Gagal mengunggah file gambar.";
                $pesan_tipe = 'error';
                $nama_gambar = ''; // Kosongkan nama gambar jika gagal diunggah
            }
        }
    }

    // Lanjutkan hanya jika tidak ada pesan error dari validasi file
    if ($pesan_tipe !== 'error') {
        // Validasi input server-side
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
            // Menggunakan prepared statement untuk keamanan
            $stmt = $koneksi->prepare("INSERT INTO stok (nama_barang, stok, harga, gambar) VALUES (?, ?, ?, ?)");
            // Tipe data diupdate menjadi "sids" (string, integer, double, string)
            $stmt->bind_param("sids", $nama, $stok, $harga, $nama_gambar); 

            if ($stmt->execute()) {
                header("Location: stok.php?status=success_add");
                exit();
            } else {
                $pesan = "Gagal menambahkan barang: " . $stmt->error;
                $pesan_tipe = 'error';
            }
            $stmt->close();
        }
    }
}

// --- LOGIKA AKSI: HAPUS BARANG ---
if (isset($_GET['hapus'])) {
    $id_barang = filter_input(INPUT_GET, 'hapus', FILTER_VALIDATE_INT);

    if ($id_barang) {
        // 1. Ambil nama file gambar dari database sebelum dihapus
        $stmt_select = $koneksi->prepare("SELECT gambar FROM stok WHERE id_barang = ?");
        $stmt_select->bind_param("i", $id_barang);
        $stmt_select->execute();
        $result_gambar = $stmt_select->get_result()->fetch_assoc();
        $stmt_select->close();

        // 2. Hapus file gambar dari server jika ada
        if ($result_gambar && !empty($result_gambar['gambar'])) {
            $path_file_hapus = '../uploads/' . $result_gambar['gambar'];
            if (file_exists($path_file_hapus)) {
                unlink($path_file_hapus); // Fungsi untuk menghapus file
            }
        }
        
        // 3. Hapus data dari database
        $stmt_delete = $koneksi->prepare("DELETE FROM stok WHERE id_barang = ?");
        $stmt_delete->bind_param("i", $id_barang);

        if ($stmt_delete->execute()) {
            header("Location: stok.php?status=success_delete");
            exit();
        } else {
            header("Location: stok.php?status=error");
            exit();
        }
        
    }
    $stmt_delete->close();
}

// --- LOGIKA PENCARIAN & PENGAMBILAN DATA ---
$search_nama = isset($_GET['search_nama']) ? $koneksi->real_escape_string(trim($_GET['search_nama'])) : '';
// Query diupdate untuk mengambil kolom 'gambar'
$sql = "SELECT id_barang, nama_barang, stok, harga, gambar FROM stok";
$params = [];
$types = '';

if (!empty($search_nama)) {
    $sql .= " WHERE nama_barang LIKE ?";
    $params[] = "%" . $search_nama . "%";
    $types .= 's';
}

$sql .= " ORDER BY nama_barang ASC";
$stmt_select = $koneksi->prepare($sql);

if (!empty($params)) {
    $stmt_select->bind_param($types, ...$params);
}

$stmt_select->execute();
$resultStokBarang = $stmt_select->get_result();

// Menutup koneksi di akhir script
$koneksi->close();

/**
 * Fungsi untuk menampilkan pesan notifikasi.
 * @param string $pesan - Isi pesan.
 * @param string $tipe - Tipe pesan ('success' atau 'error').
 * @return string - Elemen HTML notifikasi.
 */
function tampilkan_pesan($pesan, $tipe) {
    if (empty($pesan)) {
        return '';
    }
    $bg_color = ($tipe === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
    return "<div class='{$bg_color} border px-4 py-3 rounded relative mb-4' role='alert'>{$pesan}</div>";
}

/**
 * Fungsi untuk menentukan kelas CSS badge stok.
 * @param int $jumlah_stok - Jumlah stok saat ini.
 * @return string - Nama kelas CSS Tailwind.
 */
function get_stok_badge_class($jumlah_stok) {
    if ($jumlah_stok <= 5) {
        return 'bg-red-100 text-red-800'; // Menipis
    } elseif ($jumlah_stok <= 10) {
        return 'bg-yellow-100 text-yellow-800'; // Hampir Habis
    }
    return 'bg-green-100 text-green-800'; // Cukup
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Owner - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya dasar untuk card, agar lebih menarik dan konsisten dengan Tailwind */
        .card {
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            margin-top: 0;
            color: #4A5568;
            /* Warna teks yang lebih gelap */
            font-size: 1.125rem;
            /* Ukuran font lebih proporsional */
            margin-bottom: 12px;
            font-weight: 600;
            /* Sedikit lebih tebal */
        }

        .card p {
            font-size: 2.25em;
            /* Ukuran angka lebih besar */
            font-weight: bold;
            color: #2D3748;
            /* Warna angka lebih gelap */
        }
    </style>
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
                &copy; Thar'z Computer 2025
            </div>
        </div>

        <main class="flex-1 flex flex-col">
            <header class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Sparepart</h2>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                        <span class="text-lg font-semibold text-gray-700"><?= htmlspecialchars($namaAkun); ?></span>
                    </div>
                    <a href="../logout.php" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors duration-200 text-sm font-medium">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </header>

            <div class="flex-1 p-8 overflow-y-auto">
                <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Tambah Barang Baru</h3>
                    <?= tampilkan_pesan($pesan, $pesan_tipe); ?>
                    <form method="POST" action="stok.php" enctype="multipart/form-data">
                        <div class="space-y-4">
                            <div>
                                <label for="nama_barang" class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                                <input type="text" id="nama_barang" name="nama_barang" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <div>
                                <label for="stok" class="block text-sm font-medium text-gray-700 mb-1">Stok</label>
                                <input type="number" id="stok" name="stok" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <div>
                                <label for="harga" class="block text-sm font-medium text-gray-700 mb-1">Harga (Rp)</label>
                                <input type="number" id="harga" name="harga" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <div>
                                <label for="gambar" class="block text-sm font-medium text-gray-700 mb-1">Gambar Barang</label>
                                <input type="file" id="gambar" name="gambar" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" accept="image/png, image/jpeg, image/jpg, image/gif">
                                <p class="mt-1 text-xs text-gray-500">Tipe file: PNG, JPG, JPEG, GIF. Ukuran maks: 2MB.</p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="submit_add" class="inline-flex items-center justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i>Tambah Barang
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Daftar Stok Barang</h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <form method="GET" action="stok.php" class="flex items-center space-x-4">
                            <div class="relative flex-grow">
                                <label for="search_nama" class="sr-only">Cari Nama Barang</label>
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                                <input type="text" id="search_nama" name="search_nama" value="<?= htmlspecialchars($search_nama); ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Ketik nama barang untuk mencari...">
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Cari</button>
                            <a href="stok.php" class="inline-flex items-center justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Reset</a>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Gambar</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($resultStokBarang->num_rows > 0): ?>
                                    <?php while($row = $resultStokBarang->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            <?php if (!empty($row['gambar'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['gambar']); ?>" alt="<?= htmlspecialchars($row['nama_barang']); ?>" class="h-12 w-12 object-cover rounded-md mx-auto">
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $row['id_barang']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= get_stok_badge_class($row['stok']); ?>"><?= $row['stok']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="edit_stok.php?id=<?= $row['id_barang']; ?>" class="text-yellow-600 hover:text-yellow-900 px-3 py-1 rounded-md bg-yellow-100 hover:bg-yellow-200 transition-colors duration-200 inline-flex items-center text-xs"><i class="fas fa-edit mr-1"></i>Edit</a>
                                                <a href="?hapus=<?= $row['id_barang']; ?>" class="text-red-600 hover:text-red-900 px-3 py-1 rounded-md bg-red-100 hover:bg-red-200 transition-colors duration-200 inline-flex items-center text-xs" onclick="return confirm('Apakah Anda yakin ingin menghapus barang ini secara permanen?')"><i class="fas fa-trash-alt mr-1"></i>Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                            <?php if (!empty($search_nama)): ?>
                                                Barang dengan nama "<?= htmlspecialchars($search_nama); ?>" tidak ditemukan.
                                            <?php else: ?>
                                                Belum ada data barang di dalam stok.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>