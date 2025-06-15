<?php
session_start();
require '../koneksi.php'; // Menyertakan file koneksi database

// --- OTENTIKASI & OTORISASI ---
$namaAkun = $_SESSION['nama'] ?? 'Owner'; // Mengambil nama dari session jika ada

// --- INISIALISASI VARIABEL ---
$pesan = '';
$pesan_tipe = '';

// --- PENGELOLAAN PESAN STATUS (SESSION-BASED) ---
if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan']['teks'];
    $pesan_tipe = $_SESSION['pesan']['tipe'];
    unset($_SESSION['pesan']); // Hapus pesan setelah ditampilkan
}

// --- LOGIKA FORM: HANYA TAMBAH KATEGORI ---
if (isset($_POST['submit_kategori'])) {
    $jenis_kategori = trim($_POST['jenis_kategori']);
    $deskripsi = trim($_POST['deskripsi']);

    if (empty($jenis_kategori)) {
        $_SESSION['pesan'] = ['teks' => 'Nama kategori tidak boleh kosong!', 'tipe' => 'error'];
    } else {
        // HANYA PROSES INSERT DATA BARU
        $stmt = $koneksi->prepare("INSERT INTO kategori (jenis_kategori, deskripsi) VALUES (?, ?)");
        $stmt->bind_param("ss", $jenis_kategori, $deskripsi);
        
        if ($stmt->execute()) {
            $_SESSION['pesan'] = ['teks' => 'Kategori baru berhasil ditambahkan!', 'tipe' => 'success'];
        } else {
            $_SESSION['pesan'] = ['teks' => 'Operasi gagal: ' . $stmt->error, 'tipe' => 'error'];
        }
        $stmt->close();
    }
    header("Location: kelola_kategori.php");
    exit();
}

// --- LOGIKA AKSI: HAPUS KATEGORI ---
if (isset($_GET['hapus'])) {
    $id_kategori = filter_input(INPUT_GET, 'hapus', FILTER_VALIDATE_INT);
    if ($id_kategori) {
        // Cek dulu apakah kategori ini masih dipakai di tabel stok
        $stmt_check = $koneksi->prepare("SELECT COUNT(*) as total FROM stok WHERE id_kategori = ?");
        $stmt_check->bind_param("i", $id_kategori);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result_check['total'] > 0) {
            $_SESSION['pesan'] = ['teks' => 'Gagal menghapus! Kategori masih digunakan oleh ' . $result_check['total'] . ' barang.', 'tipe' => 'error'];
        } else {
            $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id_kategori = ?");
            $stmt->bind_param("i", $id_kategori);
            if ($stmt->execute()) {
                $_SESSION['pesan'] = ['teks' => 'Kategori berhasil dihapus.', 'tipe' => 'success'];
            } else {
                $_SESSION['pesan'] = ['teks' => 'Gagal menghapus kategori.', 'tipe' => 'error'];
            }
            $stmt->close();
        }
    }
    header("Location: kelola_kategori.php");
    exit();
}

// --- FUNGSI EDIT DIHAPUS DARI SINI ---


// --- LOGIKA PENCARIAN & PENGAMBILAN DATA KATEGORI ---
$search_nama = isset($_GET['search_nama']) ? $koneksi->real_escape_string(trim($_GET['search_nama'])) : '';

$sql = "SELECT id_kategori, jenis_kategori, deskripsi FROM kategori";
if (!empty($search_nama)) {
    $sql .= " WHERE jenis_kategori LIKE ?";
}
$sql .= " ORDER BY jenis_kategori ASC";

$stmt_select = $koneksi->prepare($sql);
if (!empty($search_nama)) {
    $search_param = "%" . $search_nama . "%";
    $stmt_select->bind_param('s', $search_param);
}

$stmt_select->execute();
$resultDaftarKategori = $stmt_select->get_result();

$koneksi->close();

function tampilkan_pesan($pesan, $tipe) {
    if (empty($pesan)) return '';
    $bg_color = ($tipe === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
    return "<div class='{$bg_color} border px-4 py-3 rounded relative mb-4' role='alert'>{$pesan}</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kategori - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* Style tidak diubah */
        .card { background-color: #fff; padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); transition: transform 0.2s ease-in-out; }
        .card:hover { transform: translateY(-5px); }
        .card h3 { margin-top: 0; color: #4A5568; font-size: 1.125rem; margin-bottom: 12px; font-weight: 600; }
        .card p { font-size: 2.25em; font-weight: bold; color: #2D3748; }
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
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-home w-6 text-center"></i><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-users w-6 text-center"></i><span class="font-medium">Kelola Akun</span></a></li>
                    <li><a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-wrench w-6 text-center"></i><span class="font-medium">Kelola Sparepart</span></a></li>
                    <li><a href="kelola_kategori.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200"><i class="fas fa-tags w-6 text-center"></i><span class="font-medium">Kelola Kategori</span></a></li>
                    <li><a href="laporan_keuangan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-chart-line w-6 text-center"></i><span class="font-medium">Laporan Keuangan</span></a></li>
                    <li><a href="laporan_sparepart.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-boxes w-6 text-center"></i><span class="font-medium">Laporan Stok Barang</span></a></li>
                    <li><a href="laporan_pesanan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-clipboard-list w-6 text-center"></i><span class="font-medium">Laporan Pesanan</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">&copy; Thar'z Computer 2025</div>
        </div>

        <main class="flex-1 flex flex-col">
            <header class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Kategori</h2>
                <div class="flex items-center space-x-4">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan"><i class="fas fa-bell text-xl"></i></button>
                    <div class="flex items-center space-x-2"><i class="fas fa-user-circle text-2xl text-gray-600"></i><span class="text-lg font-semibold text-gray-700"><?= htmlspecialchars($namaAkun); ?></span></div>
                    <a href="../logout.php" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors duration-200 text-sm font-medium"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                </div>
            </header>

            <div class="flex-1 p-8 overflow-y-auto">
                <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">
                        Tambah Kategori Baru
                    </h3>
                    <?= tampilkan_pesan($pesan, $pesan_tipe); ?>
                    <form method="POST" action="kelola_kategori.php">
                        <div class="space-y-4">
                            <div>
                                <label for="jenis_kategori" class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori</label>
                                <input type="text" id="jenis_kategori" name="jenis_kategori" value="" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <div>
                                <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-1">Deskripsi (Opsional)</label>
                                <textarea id="deskripsi" name="deskripsi" rows="3" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="submit_kategori" class="inline-flex items-center justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i>Tambah Kategori
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Daftar Kategori</h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <form method="GET" action="kelola_kategori.php" class="flex items-end space-x-4">
                            <div class="flex-grow">
                                <label for="search_nama" class="block text-sm font-medium text-gray-700 mb-1">Cari Nama Kategori</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                                    <input type="text" id="search_nama" name="search_nama" value="<?= htmlspecialchars($search_nama); ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Ketik nama kategori untuk mencari...">
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Cari</button>
                            <a href="kelola_kategori.php" class="inline-flex items-center justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Reset</a>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kategori</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($resultDaftarKategori->num_rows > 0): ?>
                                    <?php while($row = $resultDaftarKategori->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $row['id_kategori']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['jenis_kategori']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['deskripsi']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="edit_kategori.php?id=<?= $row['id_kategori']; ?>" class="text-yellow-600 hover:text-yellow-900 px-3 py-1 rounded-md bg-yellow-100 hover:bg-yellow-200 transition-colors duration-200 inline-flex items-center text-xs"><i class="fas fa-edit mr-1"></i>Edit</a>
                                                <a href="kelola_kategori.php?hapus=<?= $row['id_kategori']; ?>" class="text-red-600 hover:text-red-900 px-3 py-1 rounded-md bg-red-100 hover:bg-red-200 transition-colors duration-200 inline-flex items-center text-xs" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')"><i class="fas fa-trash-alt mr-1"></i>Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                            Belum ada data kategori.
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