<?php
include '../koneksi.php';
include 'auth.php';

$namaAkun = getNamaUser();

// Pastikan koneksi database valid
if (!isset($koneksi) || !$koneksi instanceof mysqli) {
    die("Koneksi database belum dibuat atau salah.");
}

$nama_akun_admin = $namaAkun;

// Handle POST requests for validation/rejection
$success_message = null;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'tolak_bayar' && isset($_POST['id_bayar']) && isset($_POST['catatan'])) {
        $id_bayar = intval($_POST['id_bayar']);
        $catatan = trim($_POST['catatan']);
        if (empty($catatan)) {
            $error_message = "Catatan penolakan wajib diisi.";
        } else {
            $koneksi->begin_transaction();
            try {
                // Ambil id_transaksi dari tabel bayar
                $stmt_get_transaksi = $koneksi->prepare("SELECT id_transaksi FROM bayar WHERE id_bayar = ?");
                $stmt_get_transaksi->bind_param("i", $id_bayar);
                $stmt_get_transaksi->execute();
                $result_transaksi = $stmt_get_transaksi->get_result();
                $transaksi_data = $result_transaksi->fetch_assoc();
                $stmt_get_transaksi->close();
                
                if (!$transaksi_data) {
                    throw new Exception("Data pembayaran tidak ditemukan.");
                }
                
                $id_transaksi_tolak = $transaksi_data['id_transaksi'];
                
                // Ambil id_customer dari tabel transaksi
                $stmt_get_customer = $koneksi->prepare("SELECT id_customer FROM transaksi WHERE id_transaksi = ?");
                $stmt_get_customer->bind_param("i", $id_transaksi_tolak);
                $stmt_get_customer->execute();
                $result_customer = $stmt_get_customer->get_result();
                $customer_data = $result_customer->fetch_assoc();
                $stmt_get_customer->close();
                
                if (!$customer_data) {
                    throw new Exception("Data transaksi tidak ditemukan.");
                }
                
                $id_customer_tolak = $customer_data['id_customer'];
                
                // Kembalikan stok barang (ambil detail transaksi terlebih dahulu)
                $sql_detail = "SELECT id_barang, jumlah FROM detail_transaksi WHERE id_transaksi = ?";
                $stmt_detail = $koneksi->prepare($sql_detail);
                if (!$stmt_detail) throw new Exception("Gagal menyiapkan query detail stok: " . $koneksi->error);
                $stmt_detail->bind_param("i", $id_transaksi_tolak);
                $stmt_detail->execute();
                $result_detail = $stmt_detail->get_result();
                
                while ($row = $result_detail->fetch_assoc()) {
                    $sql_update_stok = "UPDATE stok SET stok = stok + ? WHERE id_barang = ?";
                    $stmt_stok = $koneksi->prepare($sql_update_stok);
                    if (!$stmt_stok) throw new Exception("Gagal menyiapkan query update stok: " . $koneksi->error);
                    $stmt_stok->bind_param("ii", $row['jumlah'], $row['id_barang']);
                    $stmt_stok->execute();
                    $stmt_stok->close();
                }
                $stmt_detail->close();
                
                // Hapus data pembayaran dari tabel bayar
                $stmt_delete_bayar = $koneksi->prepare("DELETE FROM bayar WHERE id_bayar = ?");
                $stmt_delete_bayar->bind_param("i", $id_bayar);
                $stmt_delete_bayar->execute();
                $stmt_delete_bayar->close();
                
                // Hapus detail transaksi
                $stmt_delete_detail = $koneksi->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = ?");
                $stmt_delete_detail->bind_param("i", $id_transaksi_tolak);
                $stmt_delete_detail->execute();
                $stmt_delete_detail->close();
                
                // Hapus transaksi
                $stmt_delete_transaksi = $koneksi->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
                $stmt_delete_transaksi->bind_param("i", $id_transaksi_tolak);
                $stmt_delete_transaksi->execute();
                $stmt_delete_transaksi->close();
                
                // Hapus customer
                $stmt_delete_customer = $koneksi->prepare("DELETE FROM customer WHERE id_customer = ?");
                $stmt_delete_customer->bind_param("i", $id_customer_tolak);
                $stmt_delete_customer->execute();
                $stmt_delete_customer->close();

                $koneksi->commit();
                $success_message = "Pembayaran berhasil ditolak. Stok telah dikembalikan dan data customer telah dihapus.";
                // Redirect untuk membersihkan POST data dan refresh halaman
                header("Location: " . $_SERVER['PHP_SELF'] . "?status_filter=" . urlencode($status_filter) . "&pesan=tolak_sukses");
                exit;
            } catch (Exception $e) {
                $koneksi->rollback();
                $error_message = "Gagal tolak pembayaran: " . $e->getMessage();
            }
        }
    }
}

// Logika filter status pesanan
// Defaultnya, tampilkan pesanan yang paling butuh perhatian: 'menunggu konfirmasi' (dari tabel bayar)
$status_filter = $_GET['status_filter'] ?? 'menunggu konfirmasi';

$daftar_pesanan = [];
$error_db = null;

// Modified SQL query to get the latest payment details from `bayar` table
$sql_pesanan = "SELECT 
                    t.id_transaksi, 
                    c.nama_customer, 
                    t.tanggal AS tanggal_pesan, 
                    t.total AS total_tagihan,
                    t.status AS status_transaksi_utama,
                    b_latest.id_bayar, 
                    b_latest.metode, 
                    b_latest.bukti, 
                    b_latest.catatan AS catatan_bayar,
                    b_latest.status AS status_konfirmasi_bayar
                FROM 
                    transaksi t
                JOIN 
                    customer c ON t.id_customer = c.id_customer
                LEFT JOIN (
                    SELECT
                        id_transaksi,
                        id_bayar,
                        metode,
                        bukti,
                        catatan,
                        status,
                        ROW_NUMBER() OVER(PARTITION BY id_transaksi ORDER BY tanggal DESC, id_bayar DESC) as rn
                    FROM
                        bayar
                ) AS b_latest ON t.id_transaksi = b_latest.id_transaksi AND b_latest.rn = 1
                WHERE 
                    t.jenis = 'penjualan' 
                    AND b_latest.status = ?
                ORDER BY 
                    t.tanggal ASC";

$stmt_pesanan = $koneksi->prepare($sql_pesanan);
if ($stmt_pesanan) {
    $stmt_pesanan->bind_param("s", $status_filter);
    $stmt_pesanan->execute();
    $result_pesanan = $stmt_pesanan->get_result();
    while ($row = $result_pesanan->fetch_assoc()) {
        $daftar_pesanan[] = $row;
    }
    $stmt_pesanan->close();
} else {
    $error_db = "Gagal menyiapkan query daftar pesanan: " . $koneksi->error;
}

// Handle success messages from redirect
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'validasi_sukses') $success_message = "Pembayaran berhasil divalidasi!";
    if ($_GET['pesan'] == 'tolak_sukses') $success_message = "Pembayaran berhasil ditolak. Stok telah dikembalikan dan data customer telah dihapus.";
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Pesanan Penjualan - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Tambahkan CSS untuk badge status jika belum ada */
        .status-badge {
            padding: 0.25em 0.5em;
            border-radius: 0.25rem;
            font-size: 0.75em;
            font-weight: bold;
            display: inline-block;
        }
        .status-menunggu-pembayaran { background-color: #fbd38d; color: #9c4221; } /* yellow-200 / yellow-800 */
        .status-menunggu-konfirmasi { background-color: #fbd38d; color: #9c4221; } /* yellow-200 / yellow-800 */
        .status-lunas { background-color: #a7f3d0; color: #065f46; } /* green-200 / green-800 */
        .status-dibayar { background-color: #a7f3d0; color: #065f46; } /* green-200 / green-800 */
        .status-ditolak { background-color: #fecaca; color: #991b1b; } /* red-200 / red-800 */
        .status-selesai { background-color: #bfdbfe; color: #1e40af; } /* blue-200 / blue-800 */
        .status-default { background-color: #e2e8f0; color: #4a5568; } /* gray-200 / gray-800 */
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">
    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Kelola Pesanan Penjualan</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <span class="text-2xl">🔔</span>
                    </button>
                    <div class="flex items-center space-x-3">
                        <span class="text-xl text-gray-600">👤</span>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($nama_akun_admin); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-auto">
                <?php if ($error_db): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Error Database!</p>
                        <p><?php echo htmlspecialchars($error_db); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Berhasil!</p>
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Gagal!</p>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Cari Transaksi Penjualan</h3>
                    <form action="pembayaran_penjualan.php" method="GET" class="flex items-end space-x-4">
                        <div>
                            <label for="id_transaksi_cari" class="block text-sm font-medium text-gray-700 mb-1">Masukkan ID Transaksi:</label>
                            <input type="number" name="id_transaksi" id="id_transaksi_cari" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Contoh: 101" required>
                        </div>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">
                            <i class="bi bi-search"></i> Cari & Proses
                        </button>
                    </form>
                </div>

                <hr class="my-8 border-gray-300">
                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Lihat Daftar Pesanan Berdasarkan Status</h3>
                    <form action="" method="GET" class="flex items-center space-x-4">
                        <label for="status_filter_select" class="text-sm font-medium text-gray-700">Tampilkan:</label>
                        <select id="status_filter_select" name="status_filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-50 focus:ring-blue-500 focus:border-blue-500 focus:outline-none" onchange="this.form.submit()">
                            <option value="menunggu konfirmasi" <?php if ($status_filter == 'menunggu konfirmasi') echo 'selected'; ?>>Menunggu Konfirmasi</option>
                            <option value="lunas" <?php if ($status_filter == 'lunas') echo 'selected'; ?>>Lunas</option>
                            <option value="ditolak" <?php if ($status_filter == 'ditolak') echo 'selected'; ?>>Ditolak</option>
                        </select>
                        <noscript><button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md">Filter</button></noscript>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Daftar Pesanan (Status: <?php echo htmlspecialchars($status_filter); ?>)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaksi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Pesan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode Bayar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Konfirmasi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan Admin</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($daftar_pesanan)): ?>
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada pesanan dengan status yang dipilih.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daftar_pesanan as $pesanan): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">#<?php echo htmlspecialchars($pesanan['id_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($pesanan['nama_customer']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesan'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">Rp <?php echo number_format($pesanan['total_tagihan'], 0, ',', '.'); ?></td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?= htmlspecialchars(ucwords($pesanan['metode'] ?? '-')) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                <?php if ($pesanan['bukti']): ?>
                                                    <a href="../<?= htmlspecialchars($pesanan['bukti']) ?>" target="_blank" class="text-blue-600 hover:underline">Lihat</a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php 
                                                    $display_status = $pesanan['status_transaksi_utama']; // Default to transaction status
                                                    $status_class = 'status-default';

                                                    if ($pesanan['id_bayar']) {
                                                        // If there's a payment record, prioritize its status
                                                        $display_status = $pesanan['status_konfirmasi_bayar'];
                                                    }

                                                    switch ($display_status) {
                                                        case 'menunggu pembayaran':
                                                        case 'menunggu konfirmasi':
                                                            $status_class = 'status-menunggu-konfirmasi';
                                                            break;
                                                        case 'lunas':
                                                        case 'dibayar':
                                                            $status_class = 'status-lunas';
                                                            break;
                                                        case 'ditolak':
                                                            $status_class = 'status-ditolak';
                                                            break;
                                                        case 'selesai': // Jika ada status selesai di `bayar` atau jika `transaksi` bisa `selesai`
                                                            $status_class = 'status-selesai';
                                                            break;
                                                        case 'dp':
                                                            $status_class = 'bg-blue-200 text-blue-800'; // Tambahkan class baru jika perlu
                                                            break;
                                                        default:
                                                            $status_class = 'status-default';
                                                            break;
                                                    }
                                                ?>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $display_status))) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-700">
                                                <?= nl2br(htmlspecialchars($pesanan['catatan_bayar'] ?? '')) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium">
                                                <?php 
                                                // Tampilkan tombol proses jika transaksi menunggu pembayaran DAN ada konfirmasi menunggu
                                                if ($pesanan['status_transaksi_utama'] == 'menunggu pembayaran' && $pesanan['status_konfirmasi_bayar'] == 'menunggu konfirmasi' && $pesanan['id_bayar']): 
                                                ?>
                                                    <a href="pembayaran_penjualan.php?id_transaksi=<?= $pesanan['id_transaksi'] ?>&id_bayar=<?= $pesanan['id_bayar'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded text-xs inline-block">
                                                        <i class="bi bi-gear"></i> Proses
                                                    </a>
                                                <?php elseif ($pesanan['status_transaksi_utama'] == 'lunas'): ?>
                                                     <a href="pembayaran_penjualan.php?id_transaksi=<?php echo htmlspecialchars($pesanan['id_transaksi']); ?>" class="text-indigo-600 hover:text-indigo-900">
                                                        Lihat Detail &rarr;
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">N/A</span>
                                                <?php endif; ?>
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
</body>

</html>