<?php
session_start();
include '../koneksi.php';

if (!isset($koneksi) || !$koneksi instanceof mysqli) {
    die("Koneksi database gagal.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Transaksi tidak valid atau tidak ditemukan.");
}
$id_transaksi = (int)$_GET['id'];

$namaAkun = "Admin"; // Sebaiknya diambil dari session jika sudah ada login

// Query utama, mengambil id_service juga
$sql_transaksi = "
    SELECT
        t.id_transaksi,
        t.id_service, -- Mengambil id_service dari tabel transaksi
        c.nama_customer,
        c.no_telepon,
        t.tanggal AS tanggal_transaksi,
        t.jenis AS jenis_transaksi,
        t.total AS total_harga,
        t.status AS status_transaksi
    FROM
        transaksi t
    JOIN
        customer c ON t.id_customer = c.id_customer
    WHERE
        t.id_transaksi = ?
    LIMIT 1;
";
$stmt_transaksi = $koneksi->prepare($sql_transaksi);
$stmt_transaksi->bind_param("i", $id_transaksi);
$stmt_transaksi->execute();
$result_transaksi = $stmt_transaksi->get_result();
$transaksi = $result_transaksi->fetch_assoc();
$stmt_transaksi->close();


// --- Blok kode debug yang ada di sini sebelumnya telah dihapus ---
// Menghapus blok debug memungkinkan skrip untuk melanjutkan ke logika di bawah ini.


// Variabel untuk menampung detail
$details = [];
$kerusakan = '';
$detail_title = "Rincian Barang";

if ($transaksi) {
    $jenis_transaksi = strtolower($transaksi['jenis_transaksi']); // Konversi ke lowercase

    // Logika untuk transaksi 'Penjualan'
    if ($jenis_transaksi == 'penjualan') {
        $detail_title = "Rincian Sparepart Dibeli";
        $sql_details = "
            SELECT 'Sparepart' as tipe, dt.jumlah, s.nama_barang as deskripsi, s.harga as harga
            FROM detail_transaksi dt
            JOIN stok s ON dt.id_barang = s.id_barang
            WHERE dt.id_transaksi = ?;
        ";
        $stmt_details = $koneksi->prepare($sql_details);
        $stmt_details->bind_param("i", $id_transaksi);

        // Logika untuk transaksi 'Service'
    } elseif ($jenis_transaksi == 'service' && !empty($transaksi['id_service'])) {
        $detail_title = "Rincian Biaya & Sparepart Servis";
        $id_service = $transaksi['id_service'];

        // Ambil data dari tabel detail service (ds)
        $sql_details = "
            SELECT
                ds.kerusakan,
                s.nama_barang,
                s.harga,
                j.jenis_jasa,
                j.harga AS harga_jasa
            FROM
                detail_service ds
            LEFT JOIN
                stok s ON ds.id_barang = s.id_barang
            LEFT JOIN
                jasa j ON ds.id_jasa = j.id_jasa
            WHERE
                ds.id_service = ?;
        ";
        $stmt_details = $koneksi->prepare($sql_details);
        $stmt_details->bind_param("i", $id_service);
    }

    // Eksekusi jika statement sudah disiapkan
    if (isset($stmt_details)) {
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();

        // Proses hasil query yang strukturnya berbeda
        if ($jenis_transaksi == 'penjualan') {
            while ($row = $result_details->fetch_assoc()) {
                $details[] = $row;
            }
        } elseif ($jenis_transaksi == 'service') {
            $is_first_row = true;
            while ($row = $result_details->fetch_assoc()) {
                if ($is_first_row) {
                    $kerusakan = $row['kerusakan']; // Ambil deskripsi kerusakan hanya sekali
                    $is_first_row = false;
                }
                // Tambahkan ke rincian jika ada jasa atau barang
                if (!empty($row['jenis_jasa'])) {
                    $details[] = ['tipe' => 'Biaya Jasa', 'jumlah' => 1, 'deskripsi' => $row['jenis_jasa'], 'harga' => $row['harga_jasa']];
                }
                if (!empty($row['nama_barang'])) {
                    $details[] = ['tipe' => 'Sparepart', 'jumlah' => 1, 'deskripsi' => $row['nama_barang'], 'harga' => $row['harga']];
                }
            }
        }
        $stmt_details->close();
    }
}

// Grouping data $details untuk menjumlahkan item yang sama
if (!empty($details)) {
    $grouped = [];
    foreach ($details as $item) {
        $key = $item['deskripsi'] . '|' . $item['tipe'] . '|' . $item['harga'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = $item;
        } else {
            $grouped[$key]['jumlah'] += $item['jumlah'];
        }
    }
    $details = array_values($grouped);
}

// Ambil data pembayaran
$pembayaran = [];
if (strtolower($transaksi['jenis_transaksi']) == 'service' && !empty($transaksi['id_service'])) {
    // JIKA SERVICE: Cari semua pembayaran yang terkait dengan id_service yang sama.
    $sql_bayar = "
        SELECT
            b.*,
            t.status as status_transaksi
        FROM
            bayar b
        JOIN
            transaksi t ON b.id_transaksi = t.id_transaksi
        WHERE
            t.id_service = ?  -- Filter berdasarkan id_service
        ORDER BY
            b.tanggal desc";

    $stmt_bayar = $koneksi->prepare($sql_bayar);
    // Binding parameter dengan id_service, bukan id_transaksi dari URL
    $stmt_bayar->bind_param("s", $transaksi['id_service']);
} else {
    // JIKA BUKAN SERVICE (misal: penjualan): Gunakan logika lama, filter berdasarkan id_transaksi
    $sql_bayar = "
        SELECT b.*, t.status as status_transaksi
        FROM bayar b
        JOIN transaksi t ON b.id_transaksi = t.id_transaksi
        WHERE b.id_transaksi = ?
        ORDER BY b.tanggal ASC";

    $stmt_bayar = $koneksi->prepare($sql_bayar);
    $stmt_bayar->bind_param("i", $id_transaksi);
}

// Eksekusi statement dan ambil hasilnya
if (isset($stmt_bayar)) {
    $stmt_bayar->execute();
    $result_bayar = $stmt_bayar->get_result();
    while ($row = $result_bayar->fetch_assoc()) {
        $pembayaran[] = $row;
    }
    $stmt_bayar->close();
}

$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Transaksi - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">
    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thar'z Computer</h1>
                    <p class="text-sm text-gray-400">Admin Panel</p>
                </div>
                <ul class="px-6 space-y-3">
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">🏠</span><span class="font-medium">Dashboard</span></a></li>
                    <li><a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">💰</span><span class="font-medium">Pembayaran Service</span></a></li>
                    <li><a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">💰</span><span class="font-medium">Kelola Penjualan</span></a></li>
                    <li><a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">📝</span><span class="font-medium">Data Service</span></a></li>
                    <li><a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">👥</span><span class="font-medium">Data Pelanggan</span></a></li>
                    <li><a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200"><span class="text-xl">💳</span><span class="font-medium">Riwayat Transaksi</span></a></li>
                    <li><a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">📦</span><span class="font-medium">Stok Gudang</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">&copy; Thar'z Computer 2025</div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Detail Transaksi</h2>
                <div class="flex items-center space-x-3">
                    <span class="text-xl text-gray-600">👤</span>
                    <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                    <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-auto">
                <div class="mb-6"><a href="riwayat_transaksi.php" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition duration-200 text-sm font-medium">&larr; Kembali ke Riwayat</a></div>

                <?php if ($transaksi) : ?>
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                        <h3 class="text-xl font-semibold mb-4 border-b pb-2 text-gray-700">Informasi Transaksi</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div><strong class="text-gray-600">ID Pesanan:</strong> <?php echo htmlspecialchars($transaksi['id_transaksi']); ?></div>
                            <div><strong class="text-gray-600">Jenis Transaksi:</strong> <span class="font-bold <?php echo strtolower($transaksi['jenis_transaksi']) == 'service' ? 'text-blue-600' : 'text-green-600'; ?>"><?php echo htmlspecialchars($transaksi['jenis_transaksi']); ?></span></div>
                            <div><strong class="text-gray-600">Status:</strong>
                                <span class="font-bold <?php
                                                        $status_lower = strtolower($transaksi['status_transaksi']);
                                                        if ($status_lower == 'lunas' || $status_lower == 'selesai') echo 'text-green-600';
                                                        elseif ($status_lower == 'proses' || $status_lower == 'menunggu pembayaran') echo 'text-yellow-600';
                                                        else echo 'text-red-600';
                                                        ?>">
                                    <?php echo htmlspecialchars($transaksi['status_transaksi']); ?>
                                </span>
                            </div>
                            <div><strong class="text-gray-600">Nama Pelanggan:</strong> <?php echo htmlspecialchars($transaksi['nama_customer']); ?></div>
                            <div><strong class="text-gray-600">No. HP:</strong> <?php echo htmlspecialchars($transaksi['no_telepon']); ?></div>
                            <div><strong class="text-gray-600">Tanggal:</strong> <?php echo date('d F Y', strtotime($transaksi['tanggal_transaksi'])); ?></div>
                        </div>
                        <?php if (strtolower($transaksi['jenis_transaksi']) == 'service' && !empty($kerusakan)): ?>
                            <div class="mt-4 pt-4 border-t">
                                <strong class="text-gray-600">Deskripsi Kerusakan:</strong>
                                <p class="text-gray-800 mt-1"><?php echo nl2br(htmlspecialchars($kerusakan)); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-xl font-semibold mb-4 text-gray-700"><?php echo htmlspecialchars($detail_title); ?></h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($details)) : ?>
                                        <?php foreach ($details as $item) : ?>
                                            <tr>
                                                <td class="px-6 py-4 text-left text-sm text-gray-900"><?php echo htmlspecialchars($item['deskripsi']); ?></td>
                                                <td class="px-6 py-4 text-left text-sm text-gray-900"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $item['tipe'] == 'Sparepart' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>"><?php echo htmlspecialchars($item['tipe']); ?></span></td>
                                                <td class="px-6 py-4 text-center text-sm text-gray-900"><?php echo htmlspecialchars($item['jumlah']); ?></td>
                                                <td class="px-6 py-4 text-right text-sm text-gray-900">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                                <td class="px-6 py-4 text-right text-sm text-gray-900">Rp <?php echo number_format($item['harga'] * $item['jumlah'], 0, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada rincian biaya atau sparepart untuk transaksi ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="bg-gray-100">
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-right text-sm font-bold text-gray-800 uppercase">Total Keseluruhan</td>
                                        <td class="px-6 py-4 text-right text-sm font-bold text-gray-800">Rp <?php echo number_format($transaksi['total_harga'], 0, ',', '.'); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md mt-8">
                        <h3 class="text-xl font-semibold mb-4 text-gray-700">Riwayat Pembayaran</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Metode</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status Pembayaran</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status Transaksi</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($pembayaran)) : ?>
                                        <?php foreach ($pembayaran as $bayar) : ?>
                                            <tr>
                                                <td class="px-6 py-4 text-left text-sm text-gray-900"><?php echo date('d-m-Y H:i', strtotime($bayar['tanggal'])); ?></td>
                                                <td class="px-6 py-4 text-right text-sm text-gray-900">Rp <?php echo number_format($bayar['jumlah'], 0, ',', '.'); ?></td>
                                                <td class="px-6 py-4 text-center text-sm text-gray-900"><?php echo htmlspecialchars($bayar['metode']); ?></td>
                                                <td class="px-6 py-4 text-center text-sm text-gray-900">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php echo strtolower($bayar['status']) == 'lunas' ? 'bg-green-100 text-green-800' : (strtolower($bayar['status']) == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                        <?php echo htmlspecialchars($bayar['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center text-sm text-gray-900">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php echo strtolower($bayar['status_transaksi']) == 'selesai' || strtolower($bayar['status_transaksi']) == 'lunas' ? 'bg-green-100 text-green-800' : (strtolower($bayar['status_transaksi']) == 'proses' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                        <?php echo htmlspecialchars($bayar['status_transaksi']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center text-sm text-gray-900"><?php if ($bayar['bukti']) {
                                                                                                            echo '<a href="../' . htmlspecialchars($bayar['bukti']) . '" target="_blank" class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition duration-200"><svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>Lihat Bukti</a>';
                                                                                                        } else {
                                                                                                            echo '-';
                                                                                                        } ?></td>
                                                <td class="px-6 py-4 text-left text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($bayar['catatan'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada pembayaran untuk transaksi ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="bg-white p-8 rounded-lg shadow-md text-center">
                        <h2 class="text-2xl font-bold text-red-500 mb-4">Transaksi Tidak Ditemukan</h2>
                        <p class="text-gray-600">ID transaksi yang Anda minta tidak ada dalam database.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>