<?php
session_start();
require '../koneksi.php'; // Menyertakan file koneksi database

// --- OTENTIKASI & OTORISASI ---
// (Asumsi role 'owner' untuk pengembangan)
$namaAkun = "Owner";

// --- INISIALISASI VARIABEL ---
$pesan = '';

// --- LOGIKA PROSES FORM (CRUD DENGAN MODAL) ---

// 1. PROSES TAMBAH BARANG (Tidak ada perubahan di sini)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_barang'])) {
    $nama = trim($_POST['nama_barang']);
    $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
    $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
    $id_kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $nama_gambar = '';

    if (empty($nama) || $stok === false || $stok < 0 || $harga === false || $harga < 0 || $id_kategori === false || $id_kategori <= 0) {
        $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Semua field wajib diisi dengan benar.</div>';
    } else {
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $info_file = $_FILES['gambar'];
            $nama_asli = $info_file['name'];
            $lokasi_tmp = $info_file['tmp_name'];
            $ekstensi_file = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));
            $tipe_diizinkan = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($ekstensi_file, $tipe_diizinkan) && $info_file['size'] <= 2 * 1024 * 1024) {
                $nama_gambar = uniqid() . '-' . time() . '.' . $ekstensi_file;
                $path_tujuan = '../uploads/' . $nama_gambar;
                if (!move_uploaded_file($lokasi_tmp, $path_tujuan)) {
                    $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal mengunggah file gambar.</div>';
                    $nama_gambar = '';
                }
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">File tidak valid (hanya JPG, PNG, GIF, maks 2MB).</div>';
            }
        }
        
        if (empty($pesan)) {
            $stmt = $koneksi->prepare("INSERT INTO stok (nama_barang, stok, harga, gambar, id_kategori) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sidsi", $nama, $stok, $harga, $nama_gambar, $id_kategori);
            if ($stmt->execute()) {
                $pesan = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Barang baru berhasil ditambahkan.</div>';
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menambahkan barang: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// 2. PROSES EDIT BARANG (Tidak ada perubahan di sini)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_barang'])) {
    $id_barang = filter_input(INPUT_POST, 'id_barang', FILTER_VALIDATE_INT);
    $nama = trim($_POST['nama_barang']);
    $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
    $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
    $id_kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $gambar_lama = $_POST['gambar_lama'];
    $nama_gambar = $gambar_lama;

    if (empty($nama) || $stok === false || $harga === false || $id_kategori === false || !$id_barang) {
         $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Data yang dikirim tidak lengkap atau tidak valid.</div>';
    } else {
        if (isset($_FILES['gambar_edit']) && $_FILES['gambar_edit']['error'] === UPLOAD_ERR_OK) {
             $info_file = $_FILES['gambar_edit'];
             $ekstensi_file = strtolower(pathinfo($info_file['name'], PATHINFO_EXTENSION));
             $tipe_diizinkan = ['jpg', 'jpeg', 'png', 'gif'];
             if (in_array($ekstensi_file, $tipe_diizinkan) && $info_file['size'] <= 2 * 1024 * 1024) {
                 $nama_gambar = uniqid() . '-' . time() . '.' . $ekstensi_file;
                 $path_tujuan = '../uploads/' . $nama_gambar;
                 if (move_uploaded_file($info_file['tmp_name'], $path_tujuan)) {
                     if (!empty($gambar_lama) && file_exists('../uploads/' . $gambar_lama)) {
                         unlink('../uploads/' . $gambar_lama);
                     }
                 } else {
                     $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal mengunggah gambar baru. Perubahan dibatalkan.</div>';
                     $nama_gambar = $gambar_lama;
                 }
             } else {
                 $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">File baru tidak valid. Perubahan gambar dibatalkan.</div>';
             }
        }

        if (empty($pesan)) {
             $stmt = $koneksi->prepare("UPDATE stok SET nama_barang = ?, stok = ?, harga = ?, gambar = ?, id_kategori = ? WHERE id_barang = ?");
             $stmt->bind_param("sidsii", $nama, $stok, $harga, $nama_gambar, $id_kategori, $id_barang);
             if ($stmt->execute()) {
                 $pesan = '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">Data barang berhasil diperbarui.</div>';
             } else {
                 $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal memperbarui data: ' . $stmt->error . '</div>';
             }
             $stmt->close();
        }
    }
}

// 3. PROSES HAPUS BARANG (DIUBAH)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hapus_barang'])) {
    $id_barang = filter_input(INPUT_POST, 'id_barang', FILTER_VALIDATE_INT);
    $gambar_hapus = $_POST['gambar'];

    if ($id_barang) {
        // DIUBAH: Tambahkan pengecekan ke tabel anak (`detail_service`)
        $stmt_check = $koneksi->prepare("SELECT COUNT(*) as total FROM detail_service WHERE id_barang = ?");
        $stmt_check->bind_param("i", $id_barang);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        // Jika total > 0, artinya barang sedang digunakan. Jangan hapus.
        if ($result_check['total'] > 0) {
            $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menghapus! Barang ini masih tercatat dalam ' . $result_check['total'] . ' riwayat servis.</div>';
        } else {
            // Jika tidak digunakan, baru lanjutkan proses hapus
            $stmt = $koneksi->prepare("DELETE FROM stok WHERE id_barang = ?");
            $stmt->bind_param("i", $id_barang);
            // Ini adalah baris 118 yang menyebabkan error sebelumnya
            if ($stmt->execute()) {
                // Jika berhasil, hapus file gambar dari server
                if (!empty($gambar_hapus) && file_exists('../uploads/' . $gambar_hapus)) {
                    unlink('../uploads/' . $gambar_hapus);
                }
                $pesan = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">Data barang berhasil dihapus.</div>';
            } else {
                // Pesan error umum jika terjadi masalah lain
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menghapus data: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// --- PENGAMBILAN DATA KATEGORI (Tidak ada perubahan) ---
$queryKategori = "SELECT id_kategori, jenis_kategori FROM kategori ORDER BY jenis_kategori ASC";
$resultKategori = $koneksi->query($queryKategori);
$listKategori = [];
while ($rowKategori = $resultKategori->fetch_assoc()) {
    $listKategori[] = $rowKategori;
}

// --- LOGIKA PENCARIAN & PENGAMBILAN DATA BARANG (Tidak ada perubahan) ---
$search_nama = isset($_GET['search_nama']) ? $koneksi->real_escape_string(trim($_GET['search_nama'])) : '';
$filter_kategori = isset($_GET['filter_kategori']) ? filter_input(INPUT_GET, 'filter_kategori', FILTER_VALIDATE_INT) : '';

$sql = "SELECT s.id_barang, s.nama_barang, s.stok, s.harga, s.gambar, s.id_kategori, k.jenis_kategori 
        FROM stok s
        LEFT JOIN kategori k ON s.id_kategori = k.id_kategori";
$conditions = [];
$params = [];
$types = '';

if (!empty($search_nama)) {
    $conditions[] = "s.nama_barang LIKE ?";
    $params[] = "%" . $search_nama . "%";
    $types .= 's';
}
if ($filter_kategori) {
    $conditions[] = "s.id_kategori = ?";
    $params[] = $filter_kategori;
    $types .= 'i';
}
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY s.nama_barang ASC";
$stmt_select = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt_select->bind_param($types, ...$params);
}
$stmt_select->execute();
$resultStokBarang = $stmt_select->get_result();

function get_stok_badge_class($jumlah_stok) {
    if ($jumlah_stok <= 5) return 'bg-red-100 text-red-800';
    if ($jumlah_stok <= 10) return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Sparepart - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #D1D5DB !important; border-radius: 0.375rem !important; display: flex; align-items: center; }
        .select2-container .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding-left: 12px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; right: 8px !important; }
        .select2-results__option--highlighted { background-color: #2563EB !important; color: white !important; }
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
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-home w-6 text-center"></i><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-users w-6 text-center"></i><span class="font-medium">Kelola Akun</span></a></li>
                    <li><a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition"><i class="fas fa-wrench w-6 text-center"></i><span class="font-medium">Kelola Sparepart</span></a></li>
                    <li><a href="kelola_kategori.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-tags w-6 text-center"></i><span class="font-medium">Kelola Kategori</span></a></li>
                    <li><a href="kelola_jasa.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-concierge-bell w-6 text-center"></i><span class="font-medium">Kelola Jasa</span></a></li>
                    <li><a href="laporan_keuangan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-chart-line w-6 text-center"></i><span class="font-medium">Laporan Keuangan</span></a></li>
                    <li><a href="laporan_sparepart.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-boxes w-6 text-center"></i><span class="font-medium">Laporan Stok Barang</span></a></li>
                    <li><a href="laporan_pesanan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-clipboard-list w-6 text-center"></i><span class="font-medium">Laporan Pesanan</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">&copy; Thar'z Computer 2025</div>
        </div>

        <main class="flex-1 flex flex-col">
            <header class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Sparepart</h2>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2"><i class="fas fa-user-circle text-2xl text-gray-600"></i><span class="text-lg font-semibold text-gray-700"><?= htmlspecialchars($namaAkun); ?></span></div>
                    <a href="../logout.php" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition text-sm font-medium"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                </div>
            </header>

            <div class="flex-1 p-8 overflow-y-auto">
                <button onclick="openModal('addModal')" class="mb-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                    <i class="fas fa-plus-circle mr-2"></i>Tambah Barang Baru
                </button>

                <?php echo $pesan; ?>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Daftar Stok Barang</h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <form method="GET" action="stok.php" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="md:col-span-1">
                                <label for="search_nama" class="block text-sm font-medium text-gray-700 mb-1">Cari Nama Barang</label>
                                <input type="text" id="search_nama" name="search_nama" value="<?= htmlspecialchars($search_nama); ?>" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500" placeholder="Ketik nama barang...">
                            </div>
                            <div>
                                <label for="filter_kategori" class="block text-sm font-medium text-gray-700 mb-1">Filter Kategori</label>
                                <select id="filter_kategori" name="filter_kategori" class="block w-full">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($listKategori as $kategori): ?>
                                        <option value="<?= htmlspecialchars($kategori['id_kategori']); ?>" <?= (string)$filter_kategori === (string)$kategori['id_kategori'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($kategori['jenis_kategori']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex space-x-2">
                                <button type="submit" class="w-full inline-flex items-center justify-center py-2 px-4 border shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Cari</button>
                                <a href="stok.php" class="w-full inline-flex items-center justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Gambar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="stok-tbody" class="bg-white divide-y divide-gray-200">
                                <?php if ($resultStokBarang->num_rows > 0): while($row = $resultStokBarang->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-center">
                                            <?php if (!empty($row['gambar'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['gambar']); ?>" alt="<?= htmlspecialchars($row['nama_barang']); ?>" class="h-12 w-12 object-cover rounded-md mx-auto">
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama_barang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['jenis_kategori'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= get_stok_badge_class($row['stok']); ?>"><?= $row['stok']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <button onclick="openModal('editModal', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button onclick="openModal('deleteModal', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="ml-4 text-red-600 hover:text-red-900" title="Hapus"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Data tidak ditemukan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="pagination-controls" class="mt-6 flex justify-center items-center space-x-4">
                        <button id="prev-page-btn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Sebelumnya
                        </button>
                        <span id="page-counter" class="text-sm text-gray-700"></span>
                        <button id="next-page-btn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Berikutnya
                        </button>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <div id="addModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="stok.php" enctype="multipart/form-data">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tambah Barang Baru</h3>
                        <div class="space-y-4">
                            <div><label for="nama_barang_add" class="block text-sm font-medium">Nama Barang</label><input type="text" name="nama_barang" id="nama_barang_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="id_kategori_add" class="block text-sm font-medium">Kategori</label><select name="id_kategori" id="id_kategori_add" required class="mt-1 block w-full select2-add"><?php foreach($listKategori as $k){echo "<option value='{$k['id_kategori']}'>".htmlspecialchars($k['jenis_kategori'])."</option>";}?></select></div>
                            <div><label for="stok_add" class="block text-sm font-medium">Stok</label><input type="number" name="stok" id="stok_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="harga_add" class="block text-sm font-medium">Harga (Rp)</label><input type="number" name="harga" id="harga_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="gambar_add" class="block text-sm font-medium">Gambar</label><input type="file" name="gambar" id="gambar_add" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="tambah_barang" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto">Simpan</button>
                        <button type="button" onclick="closeModal('addModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="stok.php" enctype="multipart/form-data">
                    <input type="hidden" name="id_barang" id="id_barang_edit">
                    <input type="hidden" name="gambar_lama" id="gambar_lama_edit">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Barang</h3>
                        <div class="space-y-4">
                            <div><label for="nama_barang_edit" class="block text-sm font-medium">Nama Barang</label><input type="text" name="nama_barang" id="nama_barang_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="id_kategori_edit" class="block text-sm font-medium">Kategori</label><select name="id_kategori" id="id_kategori_edit" required class="mt-1 block w-full select2-edit"><?php foreach($listKategori as $k){echo "<option value='{$k['id_kategori']}'>".htmlspecialchars($k['jenis_kategori'])."</option>";}?></select></div>
                            <div><label for="stok_edit" class="block text-sm font-medium">Stok</label><input type="number" name="stok" id="stok_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="harga_edit" class="block text-sm font-medium">Harga (Rp)</label><input type="number" name="harga" id="harga_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div>
                                <label class="block text-sm font-medium">Gambar Saat Ini</label>
                                <img id="preview_gambar_edit" src="" alt="Preview" class="mt-2 h-20 w-20 object-cover rounded-md">
                            </div>
                            <div><label for="gambar_edit" class="block text-sm font-medium">Ganti Gambar (Opsional)</label><input type="file" name="gambar_edit" id="gambar_edit" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="edit_barang" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto">Perbarui</button>
                        <button type="button" onclick="closeModal('editModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
             <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="stok.php">
                    <input type="hidden" name="id_barang" id="id_barang_delete">
                    <input type="hidden" name="gambar" id="gambar_delete">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i class="fas fa-exclamation-triangle text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Barang</h3>
                                <p class="text-sm text-gray-500 mt-2">Anda yakin ingin menghapus barang "<strong id="nama_barang_delete"></strong>"? Tindakan ini tidak dapat dibatalkan.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="hapus_barang" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto">Hapus</button>
                        <button type="button" onclick="closeModal('deleteModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#filter_kategori').select2({ placeholder: "Semua Kategori", allowClear: true });
            $('.select2-add').select2({ dropdownParent: $('#addModal'), placeholder: "-- Pilih Kategori --" });
            $('.select2-edit').select2({ dropdownParent: $('#editModal'), placeholder: "-- Pilih Kategori --" });

            // --- PERUBAHAN BARU: Logika Paginasi Tabel ---
            const rowsPerPage = 9;
            const tableBody = $('#stok-tbody');
            const allRows = tableBody.find('tr');
            const totalRows = allRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            let currentPage = 1;

            const prevBtn = $('#prev-page-btn');
            const nextBtn = $('#next-page-btn');
            const pageCounter = $('#page-counter');
            const paginationControls = $('#pagination-controls');

            function displayPage(page) {
                // Sembunyikan semua baris terlebih dahulu
                allRows.hide();

                // Hitung baris mana yang akan ditampilkan
                const startIndex = (page - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                
                // Tampilkan hanya baris untuk halaman saat ini
                allRows.slice(startIndex, endIndex).show();
            }

            function updatePaginationControls() {
                // Tampilkan/sembunyikan kontrol paginasi jika lebih dari 1 halaman
                if (totalPages <= 1) {
                    paginationControls.hide();
                    return;
                } else {
                    paginationControls.show();
                }

                // Atur status tombol
                prevBtn.prop('disabled', currentPage === 1);
                nextBtn.prop('disabled', currentPage === totalPages);

                // Perbarui teks counter halaman
                pageCounter.text(`Halaman ${currentPage} dari ${totalPages}`);
            }

            // Event listener untuk tombol "Berikutnya"
            nextBtn.on('click', function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(currentPage);
                    updatePaginationControls();
                }
            });

            // Event listener untuk tombol "Sebelumnya"
            prevBtn.on('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(currentPage);
                    updatePaginationControls();
                }
            });
            
            // Inisialisasi tampilan saat halaman dimuat
            if (totalRows > 0) {
                // Periksa apakah ada baris data nyata, bukan hanya pesan "Data tidak ditemukan"
                if(allRows.find('td').length > 1) {
                    displayPage(1);
                    updatePaginationControls();
                } else {
                    paginationControls.hide();
                }
            } else {
                // Jika tidak ada baris sama sekali, sembunyikan kontrol
                paginationControls.hide();
            }
        });
        
        function openModal(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (modal) {
                if (data) {
                    if (modalId === 'editModal') {
                        document.getElementById('id_barang_edit').value = data.id_barang;
                        document.getElementById('nama_barang_edit').value = data.nama_barang;
                        document.getElementById('stok_edit').value = data.stok;
                        document.getElementById('harga_edit').value = data.harga;
                        document.getElementById('gambar_lama_edit').value = data.gambar;
                        $('#id_kategori_edit').val(data.id_kategori).trigger('change');
                        const imgPreview = document.getElementById('preview_gambar_edit');
                        imgPreview.src = data.gambar ? `../uploads/${data.gambar}` : 'https://via.placeholder.com/80';
                        imgPreview.alt = data.nama_barang;

                    } else if (modalId === 'deleteModal') {
                        document.getElementById('id_barang_delete').value = data.id_barang;
                        document.getElementById('gambar_delete').value = data.gambar;
                        document.getElementById('nama_barang_delete').textContent = data.nama_barang;
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