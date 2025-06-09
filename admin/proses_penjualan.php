<?php
session_start();
include '../koneksi.php'; // Sesuaikan path

// Cek login & koneksi (sama seperti halaman lain)
$nama_akun_admin = "Admin Contoh"; // Placeholder
// $id_user_admin = $_SESSION['admin_id'];

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
        $error_page = "Transaksi dengan ID #$id_transaksi tidak ditemukan.";
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
        $sql_bayar = "SELECT * FROM bayar WHERE id_transaksi = ? ORDER BY tanggal ASC";
        $stmt_bayar = $koneksi->prepare($sql_bayar);
        $stmt_bayar->bind_param("i", $id_transaksi);
        $stmt_bayar->execute();
        $result_bayar = $stmt_bayar->get_result();
        while ($row = $result_bayar->fetch_assoc()) {
            $pembayaran_history[] = $row;
            $total_terbayar += $row['jumlah'];
        }
        $stmt_bayar->close();
    }
}


// --- [ BAGIAN 2: PROSES FORM SUBMIT (POST REQUEST) ] ---
$success_message = null;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // -- Aksi: Simpan Pembayaran Baru --
    if ($action === 'simpan_pembayaran' && $transaksi_info) {
        $tanggal_pembayaran = $_POST['tanggal_pembayaran'];
        $jumlah_dibayar = floatval($_POST['jumlah_dibayar']);
        $metode_pembayaran = $_POST['metode_pembayaran'];
        $status_pembayaran = $_POST['status_pembayaran'];
        $catatan_pembayaran = trim($_POST['catatan_pembayaran']);

        // Validasi
        if (empty($tanggal_pembayaran) || $jumlah_dibayar <= 0 || empty($metode_pembayaran) || empty($status_pembayaran)) {
            $error_message = "Data pembayaran tidak lengkap.";
        } else {
            $koneksi->begin_transaction();
            try {
                // Insert ke tabel `bayar`
                $sql_insert_bayar = "INSERT INTO bayar (id_transaksi, tanggal, jumlah, metode, status, catatan) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $koneksi->prepare($sql_insert_bayar);
                $timestamp_pembayaran = date('Y-m-d H:i:s', strtotime($tanggal_pembayaran . " " . date("H:i:s")));
                $stmt_insert->bind_param("isdsss", $id_transaksi, $timestamp_pembayaran, $jumlah_dibayar, $metode_pembayaran, $status_pembayaran, $catatan_pembayaran);
                if (!$stmt_insert->execute()) throw new Exception("Gagal menyimpan pembayaran: " . $stmt_insert->error);
                $stmt_insert->close();

                // Update status transaksi, misalnya jika sudah lunas
                $sisa_tagihan = $transaksi_info['total'] - ($total_terbayar + $jumlah_dibayar);
                if ($sisa_tagihan <= 0) {
                    $status_baru = 'Pembayaran Diverifikasi'; // atau 'Lunas', 'Diproses', dll.
                    $sql_update_status = "UPDATE transaksi SET status = ? WHERE id_transaksi = ?";
                    $stmt_update = $koneksi->prepare($sql_update_status);
                    $stmt_update->bind_param("si", $status_baru, $id_transaksi);
                    if (!$stmt_update->execute()) throw new Exception("Gagal update status transaksi: " . $stmt_update->error);
                    $stmt_update->close();
                }

                $koneksi->commit();
                // Redirect untuk refresh halaman dan mencegah resubmit
                header("Location: " . $_SERVER['PHP_SELF'] . "?id_transaksi=" . $id_transaksi . "&pesan=bayar_sukses");
                exit;
            } catch (Exception $e) {
                $koneksi->rollback();
                $error_message = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }

    // -- Aksi: Update Status Pesanan --
    if ($action === 'update_status_pesanan' && $transaksi_info) {
        $status_baru = $_POST['status_transaksi_baru'];
        // validasi status baru jika perlu

        $sql_update_status = "UPDATE transaksi SET status = ? WHERE id_transaksi = ?";
        $stmt_update = $koneksi->prepare($sql_update_status);
        $stmt_update->bind_param("si", $status_baru, $id_transaksi);
        if ($stmt_update->execute()) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?id_transaksi=" . $id_transaksi . "&pesan=status_sukses");
            exit;
        } else {
            $error_message = "Gagal memperbarui status pesanan.";
        }
        $stmt_update->close();
    }

    // -- Aksi: Batalkan Pesanan --
    if ($action === 'batalkan_pesanan' && $transaksi_info) {
        $koneksi->begin_transaction();
        try {
            // Kembalikan stok
            foreach ($detail_items as $item) {
                $sql_kembalikan_stok = "UPDATE stok SET stok = stok + ? WHERE id_barang = ?";
                $stmt_stok = $koneksi->prepare($sql_kembalikan_stok);
                $stmt_stok->bind_param("ii", $item['jumlah'], $item['id_barang']);
                if (!$stmt_stok->execute()) throw new Exception("Gagal mengembalikan stok untuk barang ID " . $item['id_barang']);
                $stmt_stok->close();
            }

            // Update status transaksi menjadi 'Dibatalkan'
            $status_batal = 'Dibatalkan';
            $sql_update_batal = "UPDATE transaksi SET status= ? WHERE id_transaksi = ?";
            $stmt_batal = $koneksi->prepare($sql_update_batal);
            $stmt_batal->bind_param("si", $status_batal, $id_transaksi);
            if (!$stmt_batal->execute()) throw new Exception("Gagal membatalkan transaksi.");
            $stmt_batal->close();

            $koneksi->commit();
            header("Location: " . $_SERVER['PHP_SELF'] . "?id_transaksi=" . $id_transaksi . "&pesan=batal_sukses");
            exit;
        } catch (Exception $e) {
            $koneksi->rollback();
            $error_message = "Terjadi kesalahan saat membatalkan pesanan: " . $e->getMessage();
        }
    }
}

// Ambil pesan sukses dari redirect
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'bayar_sukses') $success_message = "Pembayaran baru berhasil dicatat.";
    if ($_GET['pesan'] == 'status_sukses') $success_message = "Status pesanan berhasil diperbarui.";
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

<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen">
        <div class="w-64 bg-gray-800 shadow-lg">
            <div class="p-6">
                <a href="kelola_penjualan.php" class="text-white hover:text-blue-300">&larr; Kembali ke Daftar Pesanan</a>
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Proses Pesanan Penjualan #<?php echo htmlspecialchars($id_transaksi); ?></h2>
            </div>

            <div class="flex-1 p-8 overflow-auto">
                <?php if ($error_page): ?>
                    <div class="bg-red-100 border-red-500 text-red-700 p-4 rounded-lg shadow-md"><?php echo htmlspecialchars($error_page); ?></div>
                <?php elseif ($transaksi_info): ?>

                    <?php if ($success_message): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-8">
                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold mb-4 border-b pb-2">Detail Transaksi & Customer</h3>
                                <p><strong>Status Pesanan:</strong> <span class="font-bold text-blue-600"><?php echo htmlspecialchars($transaksi_info['status']); ?></span></p>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold mb-4 border-b pb-2">Rincian Barang Pesanan</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($detail_items)): ?>
                                                <tr>
                                                    <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">Tidak ada rincian barang untuk transaksi ini.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($detail_items as $item): ?>
                                                    <tr>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($item['nama_barang']); ?>
                                                        </td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                                            Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?>
                                                        </td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                            x <?php echo htmlspecialchars($item['jumlah']); ?>
                                                        </td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-medium">
                                                            Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
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
                                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($bayar['status'] == 'Lunas') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                                <?php echo htmlspecialchars($bayar['status']); ?>
                                                            </span>
                                                        </td>
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
                            // Kondisi untuk menampilkan form pembayaran: jika status BUKAN 'Selesai' atau 'Dibatalkan' DAN masih ada sisa tagihan.
                            $sisa_tagihan = $transaksi_info['total'] - $total_terbayar;
                            if ($transaksi_info['status'] != 'Selesai' && $transaksi_info['status'] != 'Dibatalkan' && $sisa_tagihan > 0):
                            ?>
                                <div class="bg-white p-6 rounded-lg shadow-md">
                                    <h3 class="text-xl font-semibold mb-4 border-b pb-2">Catat Pembayaran Baru</h3>
                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="simpan_pembayaran">

                                        <div class="space-y-4">
                                            <div>
                                                <label for="tanggal_pembayaran" class="block text-sm font-medium text-gray-700">Tanggal Pembayaran Diterima <span class="text-red-500">*</span></label>
                                                <input type="date" name="tanggal_pembayaran" id="tanggal_pembayaran" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            </div>
                                            <div>
                                                <label for="jumlah_dibayar" class="block text-sm font-medium text-gray-700">Jumlah Dibayar <span class="text-red-500">*</span></label>
                                                <div class="mt-1 relative rounded-md shadow-sm">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">Rp</span>
                                                    </div>
                                                    <input type="number" name="jumlah_dibayar" id="jumlah_dibayar" value="<?php echo $sisa_tagihan; ?>" required class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0">
                                                </div>
                                            </div>
                                            <div>
                                                <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700">Metode Pembayaran <span class="text-red-500">*</span></label>
                                                <select id="metode_pembayaran" name="metode_pembayaran" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                    <option value="Cash">Cash</option>
                                                    <option value="Transfer">Transfer</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="status_pembayaran" class="block text-sm font-medium text-gray-700">Status Pembayaran Ini <span class="text-red-500">*</span></label>
                                                <select id="status_pembayaran" name="status_pembayaran" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                    <option value="DP">DP (Down Payment)</option>
                                                    <option value="Lunas">Lunas</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="catatan_pembayaran" class="block text-sm font-medium text-gray-700">Catatan Pembayaran (Opsional)</label>
                                                <textarea id="catatan_pembayaran" name="catatan_pembayaran" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Contoh: Transfer dari rekening Bpk. Agus"></textarea>
                                            </div>
                                        </div>

                                        <button type="submit" class="w-full mt-6 px-4 py-2 bg-green-600 text-white font-semibold rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <i class="bi bi-save-fill"></i> Simpan Pembayaran
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Kondisi untuk menampilkan form update status: jika status BUKAN 'Selesai' atau 'Dibatalkan'
                            if ($transaksi_info['status'] != 'Selesai' && $transaksi_info['status'] != 'Dibatalkan'):
                            ?>
                                <div class="bg-white p-6 rounded-lg shadow-md">
                                    <h3 class="text-xl font-semibold mb-4 border-b pb-2">Update Status Pesanan</h3>
                                    <form action="" method="POST" class="space-y-4">
                                        <input type="hidden" name="action" value="update_status_pesanan">
                                        <div>
                                            <label for="status_transaksi_baru" class="block text-sm font-medium text-gray-700">Ubah Status Menjadi:</label>
                                            <select id="status_transaksi_baru" name="status_transaksi_baru" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                <?php
                                                // Daftar status yang bisa dipilih oleh admin untuk diupdate
                                                $opsi_status = ['Pembayaran Diverifikasi', 'Diproses', 'Siap Diambil', 'Dikirim', 'Selesai'];

                                                // Menampilkan status saat ini sebagai pilihan default
                                                echo '<option value="' . htmlspecialchars($transaksi_info['status_transaksi']) . '" selected>Saat ini: ' . htmlspecialchars($transaksi_info['status_transaksi']) . '</option>';

                                                foreach ($opsi_status as $opsi) {
                                                    // Jangan tampilkan opsi yang sama dengan status saat ini
                                                    if ($opsi !== $transaksi_info['status_transaksi']) {
                                                        echo '<option value="' . htmlspecialchars($opsi) . '">' . htmlspecialchars($opsi) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="bi bi-arrow-repeat"></i> Update Status
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>