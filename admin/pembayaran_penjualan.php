<?php
session_start();
include '../koneksi.php'; // Sesuaikan path jika perlu

// Cek jika admin sudah login (contoh sederhana)
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit();
// }
// $id_user_admin = $_SESSION['admin_id'];
$nama_akun_admin = "Admin Contoh"; // Placeholder, ganti dengan dari session

// --- [ BAGIAN 1: PENGAMBILAN DATA AWAL ] ---
$id_transaksi = $_GET['id_transaksi'] ?? 0;
$transaksi_info = null;
$detail_items = [];
$pembayaran_history = [];
$error_page = null;
$total_terbayar = 0;

if (empty($id_transaksi) || !filter_var($id_transaksi, FILTER_VALIDATE_INT)) {
    $error_page = "ID Transaksi tidak valid atau tidak ditemukan.";
} else {
    // Ambil data utama transaksi & customer
    $sql_transaksi = "SELECT t.*, c.nama_customer, c.no_telepon, c.email 
                      FROM transaksi t 
                      JOIN customer c ON t.id_customer = c.id_customer 
                      WHERE t.id_transaksi = ?";
    $stmt = $koneksi->prepare($sql_transaksi);
    $stmt->bind_param("i", $id_transaksi);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $transaksi_info = $result->fetch_assoc();
    } else {
        $error_page = "Transaksi dengan ID #" . htmlspecialchars($id_transaksi) . " tidak ditemukan.";
    }
    $stmt->close();

    if ($transaksi_info) {
        // Ambil rincian barang pesanan
        $sql_detail = "SELECT dt.*, s.nama_barang, s.harga AS harga_satuan 
                       FROM detail_transaksi dt 
                       JOIN stok s ON dt.id_barang = s.id_barang 
                       WHERE dt.id_transaksi = ?";
        $stmt_detail = $koneksi->prepare($sql_detail);
        $stmt_detail->bind_param("i", $id_transaksi);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        while ($row = $result_detail->fetch_assoc()) {
            $detail_items[] = $row;
        }
        $stmt_detail->close();

        // Ambil riwayat pembayaran
        // Ambil total yang sudah terbayar
        $sql_total_bayar = "SELECT COALESCE(SUM(jumlah), 0) AS total FROM bayar WHERE id_transaksi = ?";
        $stmt_total = $koneksi->prepare($sql_total_bayar);
        $stmt_total->bind_param("i", $id_transaksi);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        if ($row_total = $result_total->fetch_assoc()) {
            $total_terbayar = $row_total['total'];
        }
        $stmt_total->close();

        // Ambil riwayat detail pembayaran untuk ditampilkan di tabel
        $sql_history = "SELECT * FROM bayar WHERE id_transaksi = ? ORDER BY tanggal ASC";
        $stmt_history = $koneksi->prepare($sql_history);
        $stmt_history->bind_param("i", $id_transaksi);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        while ($row = $result_history->fetch_assoc()) {
            $pembayaran_history[] = $row;
        }
        $stmt_history->close();
    }
}

// --- [ BAGIAN 2: PROSES FORM SUBMIT (POST REQUEST) ] ---
$success_message = null;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // -- Aksi: Simpan Pembayaran Baru (Sekarang Sekaligus Update Status) --
    if ($action === 'simpan_pembayaran_penjualan' && $transaksi_info) {
        $tanggal_pembayaran = $_POST['tanggal_pembayaran'];
        $jumlah_dibayar = floatval($_POST['jumlah_dibayar']);
        $metode_pembayaran = $_POST['metode_pembayaran'];
        $status_pembayaran = $_POST['status_pembayaran']; // Pilihan admin: 'DP' atau 'Lunas'
        $catatan_pembayaran = trim($_POST['catatan_pembayaran']);

        // Validasi
        if (empty($tanggal_pembayaran) || $jumlah_dibayar <= 0 || empty($metode_pembayaran) || empty($status_pembayaran)) {
            $error_message = "Data pembayaran tidak lengkap. Semua field wajib diisi.";
        } else {
            $koneksi->begin_transaction();
            try {
                // 1. Insert ke tabel `bayar`
                $sql_insert_bayar = "INSERT INTO bayar (id_transaksi, tanggal, jumlah, metode, status, catatan) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_bayar = $koneksi->prepare($sql_insert_bayar);
                $timestamp_pembayaran = date('Y-m-d H:i:s', strtotime($tanggal_pembayaran . " " . date("H:i:s")));
                $stmt_bayar->bind_param("isdsss", $id_transaksi, $timestamp_pembayaran, $jumlah_dibayar, $metode_pembayaran, $status_pembayaran, $catatan_pembayaran);
                if (!$stmt_bayar->execute()) throw new Exception("Gagal menyimpan pembayaran.");
                $stmt_bayar->close();

                // 2. Tentukan status transaksi baru secara otomatis
                $status_transaksi_baru = '';
                if ($status_pembayaran == 'Lunas') {
                    // Jika lunas, statusnya bisa 'Pembayaran Diverifikasi' atau langsung 'Diproses'
                    $status_transaksi_baru = 'lunas-siap diambil'; // SESUAIKAN JIKA PERLU
                } elseif ($status_pembayaran == 'DP') {
                    // Jika DP, statusnya bisa 'DP Diterima'
                    $status_transaksi_baru = 'menunggu pembayaran'; // CONTOH, SESUAIKAN
                }

                // 3. Update status di tabel `transaksi`
                if (!empty($status_transaksi_baru)) {
                    $sql_update_status = "UPDATE transaksi SET status = ? WHERE id_transaksi = ?";
                    $stmt_update = $koneksi->prepare($sql_update_status);
                    $stmt_update->bind_param("si", $status_transaksi_baru, $id_transaksi);
                    if (!$stmt_update->execute()) throw new Exception("Gagal update status transaksi.");
                    $stmt_update->close();
                }

                $koneksi->commit();
                header("Location: " . $_SERVER['PHP_SELF'] . "?id_transaksi=" . $id_transaksi . "&pesan=bayar_sukses");
                exit;
            } catch (Exception $e) {
                $koneksi->rollback();
                $error_message = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }

    // -- Aksi: Batalkan Pesanan --
    // -- Aksi: Hapus Pesanan (sebelumnya Batalkan) --
    if ($action === 'batalkan_pesanan' && $transaksi_info) {
        $koneksi->begin_transaction();
        try {
            // 1. Kembalikan stok (logika ini tetap dipertahankan)
            foreach ($detail_items as $item) {
                $sql_kembalikan_stok = "UPDATE stok SET stok = stok + ? WHERE id_barang = ?";
                $stmt_stok = $koneksi->prepare($sql_kembalikan_stok);
                $stmt_stok->bind_param("ii", $item['jumlah'], $item['id_barang']);
                if (!$stmt_stok->execute()) throw new Exception("Gagal mengembalikan stok.");
                $stmt_stok->close();
            }

            // 2. Hapus semua riwayat pembayaran terkait
            $stmt_del_bayar = $koneksi->prepare("DELETE FROM bayar WHERE id_transaksi = ?");
            $stmt_del_bayar->bind_param("i", $id_transaksi);
            if (!$stmt_del_bayar->execute()) throw new Exception("Gagal menghapus riwayat pembayaran.");
            $stmt_del_bayar->close();

            // 3. Hapus semua rincian barang pesanan
            $stmt_del_detail = $koneksi->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = ?");
            $stmt_del_detail->bind_param("i", $id_transaksi);
            if (!$stmt_del_detail->execute()) throw new Exception("Gagal menghapus rincian pesanan.");
            $stmt_del_detail->close();

            // 4. Hapus data transaksi utama
            $stmt_del_transaksi = $koneksi->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
            $stmt_del_transaksi->bind_param("i", $id_transaksi);
            if (!$stmt_del_transaksi->execute()) throw new Exception("Gagal menghapus transaksi utama.");
            $stmt_del_transaksi->close();

            $koneksi->commit();
            // Redirect ke halaman daftar penjualan, karena halaman ini sudah tidak ada lagi
            header("Location: kelola_penjualan.php?pesan=hapus_sukses");
            exit;
        } catch (Exception $e) {
            $koneksi->rollback();
            $error_message = "Terjadi kesalahan saat menghapus pesanan: " . $e->getMessage();
        }
    }
}

// Ambil pesan sukses dari redirect
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'bayar_sukses') $success_message = "Pembayaran baru berhasil dicatat.";
    if ($_GET['pesan'] == 'batal_sukses') $success_message = "Pesanan berhasil dibatalkan dan stok telah dikembalikan.";
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Proses Pesanan #<?php echo htmlspecialchars($id_transaksi); ?> - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-gray-100 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <div class="w-64 bg-gray-800 shadow-lg flex flex-col">
            <div class="flex-grow">
                <div class="flex flex-col items-center my-6">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
                    <p class="text-sm text-gray-400">Admin Panel</p>
                </div>
                <ul class="px-6 space-y-3">
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="bi bi-house-door-fill"></i><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200"><i class="bi bi-cart-check-fill"></i><span class="font-medium">Kelola Penjualan</span></a></li>
                    <li><a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="bi bi-journal-text"></i><span class="font-medium">Data Service</span></a></li>
                    <li><a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="bi bi-clock-history"></i><span class="font-medium">Riwayat Transaksi</span></a></li>
                    <li><a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="bi bi-box-seam-fill"></i><span class="font-medium">Stok Gudang</span></a></li>
                    <li><a href="laporan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="bi bi-file-earmark-text-fill"></i><span class="font-medium">Laporan</span></a></li>
                    <li><a href="pengaturan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><i class="bi bi-gear-fill"></i><span class="font-medium">Pengaturan</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Thar'z Computer <?php echo date("Y"); ?>
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Proses Pesanan Penjualan #<?php echo htmlspecialchars($id_transaksi); ?></h2>
                <div class="flex items-center space-x-3">
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($nama_akun_admin); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-auto">
                <?php if ($error_page): ?>
                    <div class="bg-red-100 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                        <p class="font-bold">Terjadi Kesalahan!</p>
                        <p><?php echo htmlspecialchars($error_page); ?></p>
                        <a href="kelola_penjualan.php" class="mt-4 inline-block text-blue-600 hover:underline">&larr; Kembali ke Daftar Pesanan</a>
                    </div>

                <?php elseif ($transaksi_info): ?>

                    <?php if ($success_message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
                    <?php if ($error_message): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-8">
                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold mb-4 border-b pb-2 flex justify-between items-center">
                                    <span>Detail Transaksi & Customer</span>
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                        <?php
                                        if ($transaksi_info['status'] == 'Menunggu Pembayaran') echo 'bg-yellow-100 text-yellow-800';
                                        elseif (in_array($transaksi_info['status'], ['Diproses', 'Pembayaran Diverifikasi', 'DP Diterima'])) echo 'bg-blue-100 text-blue-800';
                                        elseif ($transaksi_info['status'] == 'Selesai' || $transaksi_info['status'] == 'Siap Diambil') echo 'bg-green-100 text-green-800';
                                        elseif ($transaksi_info['status'] == 'Dibatalkan') echo 'bg-red-100 text-red-800';
                                        else echo 'bg-gray-100 text-gray-800';
                                        ?>">
                                        <?php echo htmlspecialchars($transaksi_info['status']); ?>
                                    </span>
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <p><strong>ID Transaksi:</strong> #<?php echo htmlspecialchars($transaksi_info['id_transaksi']); ?></p>
                                    <p><strong>Tanggal Pesan:</strong> <?php echo date('d M Y, H:i', strtotime($transaksi_info['tanggal'])); ?></p>
                                    <p class="sm:col-span-2 pt-2 border-t mt-2"><strong>Nama Customer:</strong> <?php echo htmlspecialchars($transaksi_info['nama_customer']); ?></p>
                                    <p><strong>No. Telepon:</strong> <?php echo htmlspecialchars($transaksi_info['no_telepon']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($transaksi_info['email']); ?></p>
                                </div>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold mb-4 border-b pb-2">Rincian Barang Pesanan</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($detail_items as $item): ?>
                                                <tr>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 text-right">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 text-center">x <?php echo htmlspecialchars($item['jumlah']); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-medium">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold mb-4 border-b pb-2">Riwayat & Status Pembayaran</h3>
                                <div class="overflow-x-auto mb-4">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left font-medium text-gray-500">Tgl. Bayar</th>
                                                <th class="px-4 py-2 text-right font-medium text-gray-500">Jumlah</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-500">Metode</th>
                                                <th class="px-4 py-2 text-center font-medium text-gray-500">Status</th>
                                                <th class="px-4 py-2 text-left font-medium text-gray-500">Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php if (empty($pembayaran_history)): ?>
                                                <tr>
                                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">Belum ada pembayaran yang dicatat.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($pembayaran_history as $bayar): ?>
                                                    <tr>
                                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700"><?php echo date('d M Y, H:i', strtotime($bayar['tanggal'])); ?></td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-gray-800 text-right font-medium">Rp <?php echo number_format($bayar['jumlah'], 0, ',', '.'); ?></td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500 text-center"><?php echo htmlspecialchars($bayar['metode']); ?></td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-center"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($bayar['status'] == 'Lunas') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>"><?php echo htmlspecialchars($bayar['status']); ?></span></td>
                                                        <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars($bayar['catatan'] ?: '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4 pt-4 border-t text-right font-semibold text-lg space-y-1">
                                    <p>Total Tagihan: <span class="text-gray-800">Rp <?php echo number_format($transaksi_info['total'], 0, ',', '.'); ?></span></p>
                                    <p>Total Terbayar: <span class="text-green-600">Rp <?php echo number_format($total_terbayar, 0, ',', '.'); ?></span></p>
                                    <p class="text-xl">Sisa Tagihan: <span class="text-red-600">Rp <?php echo number_format($transaksi_info['total'] - $total_terbayar, 0, ',', '.'); ?></span></p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8">
                            <?php
                            $sisa_tagihan = $transaksi_info['total'] - $total_terbayar;
                            if ($transaksi_info['status'] != 'Selesai' && $transaksi_info['status'] != 'Dibatalkan' && $sisa_tagihan > 0):
                            ?>
                                <div class="bg-white p-6 rounded-lg shadow-md">
                                    <h3 class="text-xl font-semibold mb-4 border-b pb-2">Form Pembayaran</h3>
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="simpan_pembayaran_penjualan">
                                        <div class="space-y-4">
                                            <div>
                                                <label for="tanggal_pembayaran" class="block text-sm font-medium text-gray-700">Tanggal Bayar*</label>
                                                <input type="date" name="tanggal_pembayaran" id="tanggal_pembayaran" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            </div>
                                            <div>
                                                <label for="jumlah_dibayar" class="block text-sm font-medium text-gray-700">Jumlah Dibayar*</label>
                                                <input type="number" name="jumlah_dibayar" id="jumlah_dibayar" value="<?php echo $sisa_tagihan; ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            </div>
                                            <div>
                                                <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700">Metode*</label>
                                                <select id="metode_pembayaran" name="metode_pembayaran" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                    <option value="Cash">Cash</option>
                                                    <option value="Transfer">Transfer</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="status_pembayaran" class="block text-sm font-medium text-gray-700">Status Pembayaran Ini*</label>
                                                <select id="status_pembayaran" name="status_pembayaran" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                    <option value="Lunas">Lunas</option>
                                                    <option value="DP">DP (Down Payment)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="catatan_pembayaran" class="block text-sm font-medium text-gray-700">Catatan</label>
                                                <textarea id="catatan_pembayaran" name="catatan_pembayaran" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                            </div>
                                        </div>
                                        <button type="submit" class="w-full mt-6 px-4 py-2 bg-green-600 text-white font-semibold rounded-md shadow-sm hover:bg-green-700">
                                            <i class="bi bi-save-fill"></i> Simpan Pembayaran
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                                    <p class="text-green-600 font-semibold"><i class="bi bi-check-circle-fill"></i> Tagihan untuk pesanan ini sudah lunas atau dalam status final.</p>
                                </div>
                            <?php endif; ?>

                            <?php if ($transaksi_info['status'] != 'Selesai' && $transaksi_info['status'] != 'Dibatalkan'): ?>
                                <div class="bg-white p-6 rounded-lg shadow-md border border-red-200 mt-8">
                                    <h3 class="text-xl font-semibold mb-4 border-b pb-2 text-red-700">Aksi Berbahaya</h3>
                                    <form action="" method="POST" onsubmit="return confirm('PERINGATAN! Anda akan MENGHAPUS PERMANEN pesanan ini beserta riwayatnya dan mengembalikan stok. Aksi ini tidak dapat diurungkan. Lanjutkan?');">
                                        <input type="hidden" name="action" value="batalkan_pesanan">
                                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-800">Batalkan Pesanan Ini</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="bg-white p-8 rounded-lg shadow-md text-center">
                        <p class="text-gray-600">Silakan pilih sebuah transaksi dari halaman "Kelola Pesanan Penjualan" untuk melihat detailnya.</p>
                        <a href="kelola_penjualan.php" class="mt-4 inline-block px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Kembali ke Daftar Pesanan</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php $koneksi->close(); ?>
</body>

</html>