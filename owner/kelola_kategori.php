<?php
session_start();
require '../koneksi.php'; // Menyertakan file koneksi database

// --- OTENTIKASI & OTORISASI ---
$namaAkun = $_SESSION['nama'] ?? 'Owner'; // Mengambil nama dari session jika ada

// --- INISIALISASI VARIABEL ---
$pesan = '';

// --- LOGIKA PROSES FORM (CRUD DENGAN MODAL) ---

// 1. PROSES TAMBAH KATEGORI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_kategori'])) {
    $jenis_kategori = trim($_POST['jenis_kategori']);
    $deskripsi = trim($_POST['deskripsi']);

    if (empty($jenis_kategori)) {
        $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Nama kategori tidak boleh kosong!</div>';
    } else {
        $stmt = $koneksi->prepare("INSERT INTO kategori (jenis_kategori, deskripsi) VALUES (?, ?)");
        $stmt->bind_param("ss", $jenis_kategori, $deskripsi);
        if ($stmt->execute()) {
            $pesan = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Kategori baru berhasil ditambahkan.</div>';
        } else {
            $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menambahkan kategori: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// 2. PROSES EDIT KATEGORI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_kategori'])) {
    $id_kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $jenis_kategori = trim($_POST['jenis_kategori']);
    $deskripsi = trim($_POST['deskripsi']);

    if (empty($jenis_kategori) || !$id_kategori) {
        $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Data tidak lengkap atau tidak valid.</div>';
    } else {
        $stmt = $koneksi->prepare("UPDATE kategori SET jenis_kategori = ?, deskripsi = ? WHERE id_kategori = ?");
        $stmt->bind_param("ssi", $jenis_kategori, $deskripsi, $id_kategori);
        if ($stmt->execute()) {
            $pesan = '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">Data kategori berhasil diperbarui.</div>';
        } else {
            $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal memperbarui kategori: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// 3. PROSES HAPUS KATEGORI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hapus_kategori'])) {
    $id_kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    if ($id_kategori) {
        // Cek dulu apakah kategori ini masih dipakai di tabel stok
        $stmt_check = $koneksi->prepare("SELECT COUNT(*) as total FROM stok WHERE id_kategori = ?");
        $stmt_check->bind_param("i", $id_kategori);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result_check['total'] > 0) {
            $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menghapus! Kategori masih digunakan oleh ' . $result_check['total'] . ' barang.</div>';
        } else {
            $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id_kategori = ?");
            $stmt->bind_param("i", $id_kategori);
            if ($stmt->execute()) {
                $pesan = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">Kategori berhasil dihapus.</div>';
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Gagal menghapus kategori: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

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

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kategori - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-home w-6 text-center"></i><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-users w-6 text-center"></i><span class="font-medium">Kelola Akun</span></a></li>
                    <li><a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition"><i class="fas fa-wrench w-6 text-center"></i><span class="font-medium">Kelola Sparepart</span></a></li>
                    <li><a href="kelola_kategori.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition"><i class="fas fa-tags w-6 text-center"></i><span class="font-medium">Kelola Kategori</span></a></li>
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
                <h2 class="text-2xl font-bold text-gray-800">Kelola Kategori</h2>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2"><i class="fas fa-user-circle text-2xl text-gray-600"></i><span class="text-lg font-semibold text-gray-700"><?= htmlspecialchars($namaAkun); ?></span></div>
                    <a href="../logout.php" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition text-sm font-medium"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                </div>
            </header>

            <div class="flex-1 p-8 overflow-y-auto">
                <button onclick="openModal('addModal')" class="mb-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                    <i class="fas fa-plus-circle mr-2"></i>Tambah Kategori Baru
                </button>
                
                <?php echo $pesan; // Menampilkan notifikasi ?>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Daftar Kategori</h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <form method="GET" action="kelola_kategori.php" class="flex items-end space-x-4">
                            <div class="flex-grow">
                                <label for="search_nama" class="block text-sm font-medium text-gray-700 mb-1">Cari Nama Kategori</label>
                                <input type="text" id="search_nama" name="search_nama" value="<?= htmlspecialchars($search_nama); ?>" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500" placeholder="Ketik nama kategori...">
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center py-2 px-4 border shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Cari</button>
                            <a href="kelola_kategori.php" class="inline-flex items-center justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Reset</a>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kategori</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="kategori-tbody" class="bg-white divide-y divide-gray-200">
                                <?php if ($resultDaftarKategori->num_rows > 0): while($row = $resultDaftarKategori->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 text-center text-sm text-gray-500"><?= $row['id_kategori']; ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['jenis_kategori']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($row['deskripsi']); ?></td>
                                    <td class="px-6 py-4 text-center text-sm font-medium">
                                        <button onclick="openModal('editModal', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button onclick="openModal('deleteModal', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="ml-4 text-red-600 hover:text-red-900" title="Hapus"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada data kategori.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="pagination-controls" class="mt-6 flex justify-center items-center space-x-4" style="display: none;">
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
                <form method="POST" action="kelola_kategori.php">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tambah Kategori Baru</h3>
                        <div class="space-y-4">
                            <div><label for="jenis_kategori_add" class="block text-sm font-medium">Nama Kategori</label><input type="text" name="jenis_kategori" id="jenis_kategori_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="deskripsi_add" class="block text-sm font-medium">Deskripsi (Opsional)</label><textarea name="deskripsi" id="deskripsi_add" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="tambah_kategori" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto">Simpan</button>
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
                <form method="POST" action="kelola_kategori.php">
                    <input type="hidden" name="id_kategori" id="id_kategori_edit">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Kategori</h3>
                        <div class="space-y-4">
                            <div><label for="jenis_kategori_edit" class="block text-sm font-medium">Nama Kategori</label><input type="text" name="jenis_kategori" id="jenis_kategori_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div><label for="deskripsi_edit" class="block text-sm font-medium">Deskripsi (Opsional)</label><textarea name="deskripsi" id="deskripsi_edit" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="edit_kategori" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto">Perbarui</button>
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
                <form method="POST" action="kelola_kategori.php">
                    <input type="hidden" name="id_kategori" id="id_kategori_delete">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i class="fas fa-exclamation-triangle text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Kategori</h3>
                                <p class="text-sm text-gray-500 mt-2">Anda yakin ingin menghapus kategori "<strong id="nama_kategori_delete"></strong>"? Tindakan ini tidak dapat dibatalkan.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="hapus_kategori" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto">Hapus</button>
                        <button type="button" onclick="closeModal('deleteModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Batal</button>
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
                    if (modalId === 'editModal') {
                        document.getElementById('id_kategori_edit').value = data.id_kategori;
                        document.getElementById('jenis_kategori_edit').value = data.jenis_kategori;
                        document.getElementById('deskripsi_edit').value = data.deskripsi;
                    } else if (modalId === 'deleteModal') {
                        document.getElementById('id_kategori_delete').value = data.id_kategori;
                        document.getElementById('nama_kategori_delete').textContent = data.jenis_kategori;
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

        // --- PERUBAHAN BARU: Logika Paginasi Tabel (Vanilla JS) ---
        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPage = 9;
            const tableBody = document.getElementById('kategori-tbody');
            // Pastikan tableBody ada sebelum melanjutkan
            if (!tableBody) return;

            const allRows = Array.from(tableBody.querySelectorAll('tr'));
            const totalRows = allRows.length;

            // Periksa apakah baris pertama adalah baris "data tidak ditemukan"
            if (totalRows === 1 && allRows[0].querySelectorAll('td').length === 1) {
                // Jangan lakukan paginasi jika hanya ada pesan "data tidak ditemukan"
                return;
            }

            const totalPages = Math.ceil(totalRows / rowsPerPage);
            let currentPage = 1;

            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            const pageCounter = document.getElementById('page-counter');
            const paginationControls = document.getElementById('pagination-controls');

            function displayPage(page) {
                // Sembunyikan semua baris terlebih dahulu
                allRows.forEach(row => row.style.display = 'none');

                // Hitung baris mana yang akan ditampilkan
                const startIndex = (page - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                
                // Tampilkan hanya baris untuk halaman saat ini
                const pageRows = allRows.slice(startIndex, endIndex);
                pageRows.forEach(row => row.style.display = ''); // '' akan mengembalikan ke display default (table-row)
            }

            function updatePaginationControls() {
                // Tampilkan/sembunyikan kontrol paginasi jika lebih dari 1 halaman
                if (totalPages <= 1) {
                    paginationControls.style.display = 'none';
                    return;
                } else {
                    paginationControls.style.display = 'flex';
                }

                // Atur status tombol
                prevBtn.disabled = (currentPage === 1);
                nextBtn.disabled = (currentPage === totalPages);

                // Perbarui teks counter halaman
                pageCounter.textContent = `Halaman ${currentPage} dari ${totalPages}`;
            }

            // Event listener untuk tombol "Berikutnya"
            nextBtn.addEventListener('click', function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(currentPage);
                    updatePaginationControls();
                }
            });

            // Event listener untuk tombol "Sebelumnya"
            prevBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(currentPage);
                    updatePaginationControls();
                }
            });
            
            // Inisialisasi tampilan saat halaman dimuat
            if (totalRows > 0) {
                displayPage(1);
                updatePaginationControls();
            }
        });
    </script>
</body>
</html>
<?php
$koneksi->close();
?>