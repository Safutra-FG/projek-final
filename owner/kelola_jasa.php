<?php
// kelola_jasa.php (Disesuaikan dengan struktur database Anda)
include '../koneksi.php'; // Sesuaikan path jika perlu

session_start();
// Harusnya ada pengecekan role di sini
$namaAkun = "Owner";

$pesan = ''; // Variabel untuk menyimpan pesan notifikasi

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Aksi Tambah Jasa
    if (isset($_POST['tambah_jasa'])) {
        // DIUBAH: Menggunakan 'jenis_jasa' sesuai database
        $jenis_jasa = $_POST['jenis_jasa'];
        $harga = $_POST['harga'];

        if (!empty($jenis_jasa) && !empty($harga)) {
            // DIUBAH: Query INSERT disesuaikan (tanpa deskripsi)
            $stmt = $koneksi->prepare("INSERT INTO jasa (jenis_jasa, harga) VALUES (?, ?)");
            // DIUBAH: bind_param disesuaikan
            $stmt->bind_param("si", $jenis_jasa, $harga);
            if ($stmt->execute()) {
                $pesan = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Data jasa berhasil ditambahkan.</div>';
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }

    // Aksi Edit Jasa
    if (isset($_POST['edit_jasa'])) {
        $id_jasa = $_POST['id_jasa'];
        // DIUBAH: Menggunakan 'jenis_jasa' sesuai database
        $jenis_jasa = $_POST['jenis_jasa'];
        $harga = $_POST['harga'];

        if (!empty($id_jasa) && !empty($jenis_jasa) && !empty($harga)) {
            // DIUBAH: Query UPDATE disesuaikan (tanpa deskripsi)
            $stmt = $koneksi->prepare("UPDATE jasa SET jenis_jasa = ?, harga = ? WHERE id_jasa = ?");
            // DIUBAH: bind_param disesuaikan
            $stmt->bind_param("sii", $jenis_jasa, $harga, $id_jasa);
            if ($stmt->execute()) {
                $pesan = '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">Data jasa berhasil diperbarui.</div>';
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }

    // Aksi Hapus Jasa (Tidak ada perubahan di bagian ini)
    if (isset($_POST['hapus_jasa'])) {
        $id_jasa = $_POST['id_jasa'];
        if (!empty($id_jasa)) {
            $stmt = $koneksi->prepare("DELETE FROM jasa WHERE id_jasa = ?");
            $stmt->bind_param("i", $id_jasa);
            if ($stmt->execute()) {
                $pesan = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">Data jasa berhasil dihapus.</div>';
            } else {
                $pesan = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// --- Ambil semua data jasa dari database ---
$daftar_jasa = [];
// DIUBAH: Query SELECT disesuaikan (tanpa deskripsi, menggunakan jenis_jasa)
$sql = "SELECT id_jasa, jenis_jasa, harga FROM jasa ORDER BY jenis_jasa ASC";
$result = $koneksi->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $daftar_jasa[] = $row;
    }
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Jasa - Thraz Computer</title>
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
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-home w-6 text-center"></i><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="register.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-users w-6 text-center"></i><span class="font-medium">Kelola Akun</span></a></li>
                    <li><a href="stok.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-wrench w-6 text-center"></i><span class="font-medium">Kelola Sparepart</span></a></li>
                    <li><a href="kelola_kategori.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-tags w-6 text-center"></i><span class="font-medium">Kelola Kategori</span></a></li>
                    <li><a href="kelola_jasa.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200"><i class="fas fa-concierge-bell w-6 text-center"></i><span class="font-medium">Kelola Jasa</span></a></li>
                    <li><a href="laporan_keuangan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-chart-line w-6 text-center"></i><span class="font-medium">Laporan Keuangan</span></a></li>
                    <li><a href="laporan_sparepart.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-boxes w-6 text-center"></i><span class="font-medium">Laporan Stok Barang</span></a></li>
                    <li><a href="laporan_pesanan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="fas fa-clipboard-list w-6 text-center"></i><span class="font-medium">Laporan Pesanan</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">&copy; Thar'z Computer 2025</div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Data Jasa</h2>
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
                    <i class="fas fa-plus-circle mr-2"></i>Tambah Jasa Baru
                </button>

                <?php echo $pesan; ?>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Jasa Tersedia</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Jasa</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($daftar_jasa)) : ?>
                                    <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">Belum ada data jasa.</td></tr>
                                <?php else : ?>
                                    <?php $no = 1; foreach ($daftar_jasa as $jasa) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $no++; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($jasa['jenis_jasa']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($jasa['harga'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                <button onclick="openModal('editModal', <?php echo htmlspecialchars(json_encode($jasa)); ?>)" class="text-indigo-600 hover:text-indigo-900 transition duration-150 ease-in-out" title="Edit"><i class="fas fa-edit"></i></button>
                                                <button onclick="openModal('deleteModal', <?php echo htmlspecialchars(json_encode($jasa)); ?>)" class="ml-4 text-red-600 hover:text-red-900 transition duration-150 ease-in-out" title="Hapus"><i class="fas fa-trash"></i></button>
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
            <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="kelola_jasa.php">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tambah Jasa Baru</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="jenis_jasa_add" class="block text-sm font-medium text-gray-700">Jenis Jasa</label>
                                <input type="text" name="jenis_jasa" id="jenis_jasa_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="harga_add" class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                                <input type="number" name="harga" id="harga_add" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="tambah_jasa" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                        <button type="button" onclick="closeModal('addModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
             <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="kelola_jasa.php">
                    <input type="hidden" name="id_jasa" id="id_jasa_edit">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Data Jasa</h3>
                        <div class="space-y-4">
                             <div>
                                <label for="jenis_jasa_edit" class="block text-sm font-medium text-gray-700">Jenis Jasa</label>
                                <input type="text" name="jenis_jasa" id="jenis_jasa_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="harga_edit" class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                                <input type="number" name="harga" id="harga_edit" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="edit_jasa" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Perbarui</button>
                        <button type="button" onclick="closeModal('editModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
             <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form method="POST" action="kelola_jasa.php">
                    <input type="hidden" name="id_jasa" id="id_jasa_delete">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Data Jasa</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus jasa "<strong id="jenis_jasa_delete"></strong>"? Tindakan ini tidak dapat dibatalkan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="hapus_jasa" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Hapus</button>
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
                    if (modalId === 'editModal') {
                        document.getElementById('id_jasa_edit').value = data.id_jasa;
                        // DIUBAH: Menggunakan 'jenis_jasa' dan id form yang sesuai
                        document.getElementById('jenis_jasa_edit').value = data.jenis_jasa;
                        document.getElementById('harga_edit').value = data.harga;
                    }
                    if (modalId === 'deleteModal') {
                        document.getElementById('id_jasa_delete').value = data.id_jasa;
                        // DIUBAH: Menggunakan 'jenis_jasa' dan id strong element yang sesuai
                        document.getElementById('jenis_jasa_delete').textContent = data.jenis_jasa;
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