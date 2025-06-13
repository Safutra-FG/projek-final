<?php
session_start();
include '../koneksi.php';

$namaAkun = 'Admin'; // Anda bisa sesuaikan dengan fungsi auth Anda

// Fungsi untuk menghasilkan CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$id_service = null;
if (isset($_GET['id'])) {
    $id_service = intval($_GET['id']);
} else {
    die("ID Service tidak ditemukan.");
}

$error_messages = [];
$success_message = "";

// ========================================================
// == PROSES FORM POST (Update Status, Tambah Detail) =====
// == LOGIKA INI DIAMBIL DARI KODE ASLI ANDA ==============
// ========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_messages[] = "Kesalahan validasi CSRF token.";
    } else {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $id_service_post = intval($_POST['id_service']);
        mysqli_begin_transaction($koneksi);
        try {
            // Update data service utama
            $id_customer = mysqli_real_escape_string($koneksi, $_POST['id_customer']);
            $status_baru = mysqli_real_escape_string($koneksi, $_POST['status']);
            $estimasi_waktu = mysqli_real_escape_string($koneksi, $_POST['estimasi_waktu']);
            $estimasi_harga_input = trim($_POST['estimasi_harga']);
            $estimasi_harga = !empty($estimasi_harga_input) ? (float)$estimasi_harga_input : null;

            $query_cek_status_lama = "SELECT status, tanggal_selesai FROM service WHERE id_service = ?";
            $stmt_cek_status = $koneksi->prepare($query_cek_status_lama);
            $stmt_cek_status->bind_param("i", $id_service_post);
            $stmt_cek_status->execute();
            $data_lama = $stmt_cek_status->get_result()->fetch_assoc();
            $stmt_cek_status->close();
            
            $status_lama = $data_lama['status'] ?? null;
            
            $set_parts = [
                "id_customer = ?", 
                "status = ?", 
                "estimasi_waktu = ?", 
                "estimasi_harga = ?"
            ];
            $bind_types_update = "sssd";
            $bind_params_update = [$id_customer, $status_baru, $estimasi_waktu, $estimasi_harga];

            $status_selesai_arr = ['selesai', 'siap diambil'];
            if (!in_array($status_lama, $status_selesai_arr) && in_array($status_baru, $status_selesai_arr)) {
                $set_parts[] = "tanggal_selesai = CURDATE()";
            } elseif (in_array($status_lama, $status_selesai_arr) && !in_array($status_baru, $status_selesai_arr)) {
                $set_parts[] = "tanggal_selesai = NULL";
            }

            $query_update_service = "UPDATE service SET " . implode(", ", $set_parts) . " WHERE id_service = ?";
            $stmt_update_service = $koneksi->prepare($query_update_service);
            $bind_types_update .= "i";
            $bind_params_update[] = $id_service_post;
            $stmt_update_service->bind_param($bind_types_update, ...$bind_params_update);
            if (!$stmt_update_service->execute()) {
                throw new Exception("Error updating data service utama: " . $stmt_update_service->error);
            }
            $stmt_update_service->close();

            // Proses penambahan detail service baru
            if (!empty(trim($_POST['kerusakan_detail'])) || !empty($_POST['id_barang']) || !empty($_POST['id_jasa'])) {
                $id_barang_val = !empty($_POST['id_barang']) ? intval($_POST['id_barang']) : null;
                $id_jasa_val = !empty($_POST['id_jasa']) ? intval($_POST['id_jasa']) : null;
                $kerusakan_detail_val = mysqli_real_escape_string($koneksi, $_POST['kerusakan_detail']);
                $server_calculated_total_detail = 0;

                if ($id_barang_val) {
                    $stmt_harga_brg = $koneksi->prepare("SELECT harga FROM stok WHERE id_barang = ?");
                    $stmt_harga_brg->bind_param("i", $id_barang_val);
                    $stmt_harga_brg->execute();
                    if ($row_brg = $stmt_harga_brg->get_result()->fetch_assoc()) {
                        $server_calculated_total_detail += (float)$row_brg['harga'];
                    }
                    $stmt_harga_brg->close();
                }
                if ($id_jasa_val) {
                    $stmt_harga_jsa = $koneksi->prepare("SELECT harga FROM jasa WHERE id_jasa = ?");
                    $stmt_harga_jsa->bind_param("i", $id_jasa_val);
                    $stmt_harga_jsa->execute();
                    if ($row_jsa = $stmt_harga_jsa->get_result()->fetch_assoc()) {
                        $server_calculated_total_detail += (float)$row_jsa['harga'];
                    }
                    $stmt_harga_jsa->close();
                }

                $stmt_add_detail = $koneksi->prepare("INSERT INTO detail_service (id_service, id_barang, id_jasa, kerusakan, total) VALUES (?, ?, ?, ?, ?)");
                $stmt_add_detail->bind_param("iiisd", $id_service_post, $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $server_calculated_total_detail);
                if (!$stmt_add_detail->execute()) {
                    throw new Exception("Error menambahkan detail service: " . $stmt_add_detail->error);
                }
                $stmt_add_detail->close();
                $success_message = "Data berhasil diupdate dan detail baru ditambahkan!";
            } else {
                 $success_message = "Data service berhasil diupdate";
            }
            
            mysqli_commit($koneksi);
            echo "<script>alert('" . addslashes($success_message) . "'); window.location.href='edit_service.php?id=$id_service_post';</script>";
            exit;

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error_messages[] = "Terjadi kegagalan transaksi: " . $e->getMessage();
        }
    }
}

// ========================================================
// == PENGAMBILAN DATA DARI DATABASE (DARI KODE ASLI ANDA) =
// ========================================================

// Ambil data service utama
$query_service = "SELECT s.*, c.nama_customer, c.no_telepon, c.email FROM service s LEFT JOIN customer c ON s.id_customer = c.id_customer WHERE id_service = ?";
$stmt_service = $koneksi->prepare($query_service);
$stmt_service->bind_param("i", $id_service);
$stmt_service->execute();
$result_service = $stmt_service->get_result();
if ($result_service->num_rows == 0) { die("Data service tidak ditemukan."); }
$service = $result_service->fetch_assoc();
$stmt_service->close();

// Ambil data detail service dan hitung total biaya
$total_biaya_service = 0;
$query_detail = "SELECT ds.*, b.nama_barang, j.jenis_jasa FROM detail_service ds LEFT JOIN stok b ON ds.id_barang = b.id_barang LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa WHERE ds.id_service = ? ORDER BY ds.id_ds ASC";
$stmt_detail = $koneksi->prepare($query_detail);
$stmt_detail->bind_param("i", $id_service);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
if ($result_detail) {
    while ($detail_row = $result_detail->fetch_assoc()) {
        $total_biaya_service += $detail_row['total'];
    }
}

// =================================================================
// == AMBIL DATA DARI TABEL 'pembayaran' (DARI KODE ASLI ANDA)  =====
// =================================================================
$pembayaran_menunggu_verifikasi = [];
$pembayaran_diverifikasi = [];
$total_pembayaran_diverifikasi = 0;

$query_pembayaran = "SELECT * FROM pembayaran WHERE id_service = ? ORDER BY tanggal_konfirmasi ASC";
$stmt_pembayaran = $koneksi->prepare($query_pembayaran);
$stmt_pembayaran->bind_param("i", $id_service);
$stmt_pembayaran->execute();
$result_pembayaran = $stmt_pembayaran->get_result();

while ($row_bayar = $result_pembayaran->fetch_assoc()) {
    if ($row_bayar['status_verifikasi'] == 'Menunggu Verifikasi') {
        $pembayaran_menunggu_verifikasi[] = $row_bayar;
    } elseif ($row_bayar['status_verifikasi'] == 'Diverifikasi') {
        $pembayaran_diverifikasi[] = $row_bayar;
        $total_pembayaran_diverifikasi += $row_bayar['jumlah_transfer'];
    }
}
$stmt_pembayaran->close();

// Hitung sisa pembayaran berdasarkan data yang sudah diverifikasi
$sisa_pembayaran = $total_biaya_service - $total_pembayaran_diverifikasi;
$status_pembayaran = 'Belum Lunas';
$status_badge_class = 'bg-red-100 text-red-800';

if ($total_biaya_service > 0 && $sisa_pembayaran <= 0) {
    $status_pembayaran = 'Lunas';
    $status_badge_class = 'bg-green-100 text-green-800';
} elseif ($total_pembayaran_diverifikasi > 0) {
    $status_pembayaran = 'DP / Sebagian';
    $status_badge_class = 'bg-yellow-100 text-yellow-800';
}

// Ambil data untuk dropdown
$barangs_result = mysqli_query($koneksi, "SELECT id_barang, nama_barang, harga FROM stok ORDER BY nama_barang");
$jasas_result = mysqli_query($koneksi, "SELECT id_jasa, jenis_jasa, harga FROM jasa ORDER BY jenis_jasa");

function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'selesai': case 'siap diambil': return 'bg-green-100 text-green-800';
        case 'dibatalkan': return 'bg-red-100 text-red-800';
        case 'diperbaiki': case 'dikonfirmasi': return 'bg-blue-100 text-blue-800';
        default: return 'bg-yellow-100 text-yellow-800';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Service - Thar'z Computer</title>
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
                    <li><a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">🏠</span> <span class="font-medium">Dashboard</span></a></li>
                    <li><a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">💰</span> <span class="font-medium">Pembayaran Service</span></a></li>
                    <li><a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">🛒</span> <span class="font-medium">Kelola Penjualan</span></a></li>
                    <li><a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200"><span class="text-xl">📝</span> <span class="font-medium">Data Service</span></a></li>
                    <li><a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">👥</span> <span class="font-medium">Data Pelanggan</span></a></li>
                    <li><a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">💳</span> <span class="font-medium">Riwayat Transaksi</span></a></li>
                    <li><a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200"><span class="text-xl">📦</span> <span class="font-medium">Stok Gudang</span></a></li>
                </ul>
            </div>
            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Thar'z Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Detail Service #<?php echo htmlspecialchars($service['id_service']); ?></h2>
                <div class="flex items-center space-x-5">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl text-gray-600">👤</span>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="container mx-auto p-4 sm:p-6 lg:p-8">
                <div class="mb-6">
                    <a href="data_service.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali ke Data Service
                    </a>
                </div>

                <form method="POST" action="edit_service.php?id=<?php echo $id_service; ?>">
                    <input type="hidden" name="id_service" value="<?php echo htmlspecialchars($service['id_service']); ?>">
                    <input type="hidden" name="id_customer" value="<?php echo htmlspecialchars($service['id_customer']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="flex flex-wrap -mx-4">

                        <div class="w-full lg:w-2/3 px-4 mb-6 lg:mb-0">
                            
                            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                                 <div class="flex justify-between items-center border-b pb-4 mb-4">
                                     <h3 class="text-xl font-semibold text-gray-800">Detail Transaksi & Customer</h3>
                                     <span class="text-xs font-medium px-3 py-1 rounded-full <?php echo getStatusBadge($service['status']); ?>"><?php echo htmlspecialchars(ucwords($service['status'])); ?></span>
                                 </div>
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                                     <div><span class="text-gray-500">ID Transaksi:</span><p class="font-semibold text-gray-900">#<?php echo htmlspecialchars($service['id_service']); ?></p></div>
                                     <div><span class="text-gray-500">Tanggal Pesan:</span><p class="font-semibold text-gray-900"><?php echo date('d M Y', strtotime($service['tanggal'])); ?></p></div>
                                     <div><span class="text-gray-500">Nama Customer:</span><p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['nama_customer']); ?></p></div>
                                     <div><span class="text-gray-500">Email:</span><p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['email']); ?></p></div>
                                     <div><span class="text-gray-500">No. Telepon:</span><p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['no_telepon']); ?></p></div>
                                     <div><span class="text-gray-500">Device / Keluhan:</span><p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['device']); ?> - <?php echo htmlspecialchars($service['keluhan']); ?></p></div>
                                 </div>
                             </div>

                            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                                <h3 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-4">Rincian Service</h3>
                                <div class="space-y-2">
                                    <div class="hidden md:flex text-sm font-semibold text-gray-500 px-4">
                                        <div class="w-1/5">ID Detail</div>
                                        <div class="w-1/5">Barang</div>
                                        <div class="w-1/5">Jasa</div>
                                        <div class="w-1/5 text-center">Deskripsi</div>
                                        <div class="w-1/5 text-right">SUBTOTAL</div>
                                    </div>
                                    <?php
                                    if ($result_detail) { mysqli_data_seek($result_detail, 0); } // Reset pointer
                                    if ($result_detail && $result_detail->num_rows > 0):
                                        while ($detail = $result_detail->fetch_assoc()):
                                    ?>
                                    <div class="flex flex-wrap md:flex-nowrap items-center border-b py-3 text-sm">
                                        <div class="w-full md:w-1/5 px-4 mb-2 md:mb-0"><span class="md:hidden font-bold">ID: </span><?php echo number_format($detail['id_ds']); ?></div>
                                        <div class="w-full md:w-1/5 px-4 mb-2 md:mb-0"><span class="md:hidden font-bold">Barang: </span><?php echo htmlspecialchars($detail['nama_barang'] ?: 'N/A'); ?></div>
                                        <div class="w-full md:w-1/5 px-4 mb-2 md:mb-0"><span class="md:hidden font-bold">Jasa: </span><?php echo htmlspecialchars($detail['jenis_jasa'] ?: 'N/A'); ?></div>
                                        <div class="w-full md:w-1/5 text-left md:text-center px-4 mb-2 md:mb-0"><span class="md:hidden font-bold">Deskripsi: </span><?php echo htmlspecialchars($detail['kerusakan']); ?></div>
                                        <div class="w-full md:w-1/5 text-left md:text-right font-semibold px-4"><span class="md:hidden font-bold">Subtotal: </span>Rp <?php echo number_format($detail['total'], 0, ',', '.'); ?></div>
                                    </div>
                                    <?php
                                        endwhile;
                                    else:
                                        echo '<p class="text-center text-gray-500 py-4">Belum ada detail service yang ditambahkan.</p>';
                                    endif;
                                    ?>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-4">Ringkasan & Verifikasi Pembayaran</h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-6 pb-4 border-b">
                                    <div class="bg-gray-50 p-3 rounded-lg"><span class="text-gray-500">Total Tagihan Service:</span><p class="font-bold text-gray-900 text-lg">Rp <?php echo number_format($total_biaya_service, 0, ',', '.'); ?></p></div>
                                    <div class="bg-green-50 p-3 rounded-lg"><span class="text-gray-500">Total Pembayaran Diverifikasi:</span><p class="font-bold text-green-700 text-lg">Rp <?php echo number_format($total_pembayaran_diverifikasi, 0, ',', '.'); ?></p></div>
                                    <div class="bg-red-50 p-3 rounded-lg"><span class="text-gray-500">Sisa Tagihan:</span><p class="font-bold text-red-700 text-lg">Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></p></div>
                                </div>
                                
                                <div class="mb-6">
                                    <h4 class="text-md font-semibold text-blue-800 mb-3">Pembayaran Menunggu Verifikasi</h4>
                                    <?php if (empty($pembayaran_menunggu_verifikasi)): ?>
                                        <p class="text-gray-500 text-sm text-center py-4 bg-gray-50 rounded-lg">Tidak ada pembayaran yang menunggu verifikasi saat ini.</p>
                                    <?php else: ?>
                                        <div class="space-y-3">
                                            <?php foreach ($pembayaran_menunggu_verifikasi as $bayar): ?>
                                                <div class="flex flex-wrap md:flex-nowrap justify-between items-center border rounded-lg p-3">
                                                    <div class="text-sm">
                                                        <p><span class="font-semibold">Tgl Konfirmasi:</span> <?php echo date('d M Y, H:i', strtotime($bayar['tanggal_konfirmasi'])); ?></p>
                                                        <p><span class="font-semibold">Pengirim:</span> <?php echo htmlspecialchars($bayar['nama_pengirim']); ?></p>
                                                        <p><span class="font-semibold">Jumlah:</span> <span class="text-red-600 font-bold">Rp <?php echo number_format($bayar['jumlah_transfer'], 0, ',', '.'); ?></span></p>
                                                    </div>
                                                    <div class="flex items-center space-x-2 mt-2 md:mt-0">
                                                        <a href="../uploads/bukti_pembayaran/<?php echo htmlspecialchars($bayar['file_bukti']); ?>" target="_blank" class="bg-gray-600 hover:bg-gray-700 text-white text-xs font-bold py-1 px-2 rounded">Lihat Bukti</a>
                                                        <a href="verifikasi_pembayaran.php?id_bayar=<?php echo $bayar['id_pembayaran']; ?>&id_service=<?php echo $id_service; ?>&aksi=verifikasi" onclick="return confirm('Anda yakin ingin memverifikasi pembayaran ini?');" class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-2 rounded">Verifikasi</a>
                                                        <a href="verifikasi_pembayaran.php?id_bayar=<?php echo $bayar['id_pembayaran']; ?>&id_service=<?php echo $id_service; ?>&aksi=tolak" onclick="return confirm('Anda yakin ingin menolak pembayaran ini?');" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-1 px-2 rounded">Tolak</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <h4 class="text-md font-semibold text-gray-700 mb-3 border-t pt-4">Riwayat Pembayaran (Telah Diverifikasi)</h4>
                                    <?php if (empty($pembayaran_diverifikasi)): ?>
                                        <p class="text-gray-500 text-sm text-center py-4">Belum ada riwayat pembayaran yang diverifikasi.</p>
                                    <?php else: ?>
                                        <div class="space-y-2 text-sm">
                                            <?php foreach ($pembayaran_diverifikasi as $bayar): ?>
                                                <div class="flex justify-between items-center border-b p-2 hover:bg-gray-50">
                                                    <div>
                                                        <p class="font-semibold">ID Bayar: <?php echo htmlspecialchars($bayar['id_pembayaran']); ?></p>
                                                        <p class="text-gray-500"><?php echo date('d M Y, H:i', strtotime($bayar['tanggal_konfirmasi'])); ?></p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="font-bold text-green-700">Rp <?php echo number_format($bayar['jumlah_transfer'], 0, ',', '.'); ?></p>
                                                        <p class="text-gray-500 text-xs">Metode: <?php echo htmlspecialchars($bayar['metode_transfer']); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Script untuk kalkulasi total biaya detail (dari Kode Asli Anda)
    document.addEventListener('DOMContentLoaded', function() {
        const barangSelect = document.getElementById('id_barang');
        const jasaSelect = document.getElementById('id_jasa');
        const totalDetailDisplayInput = document.getElementById('total_detail_display');
        function updateTotalDisplay() {
            let total = 0;
            const selectedBarang = barangSelect.options[barangSelect.selectedIndex];
            const selectedJasa = jasaSelect.options[jasaSelect.selectedIndex];
            if (selectedBarang && selectedBarang.dataset.harga) { total += parseFloat(selectedBarang.dataset.harga); }
            if (selectedJasa && selectedJasa.dataset.harga) { total += parseFloat(selectedJasa.dataset.harga); }
            totalDetailDisplayInput.value = total > 0 ? total.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }) : '';
        }
        if(barangSelect) barangSelect.addEventListener('change', updateTotalDisplay);
        if(jasaSelect) jasaSelect.addEventListener('change', updateTotalDisplay);
        updateTotalDisplay();
    });
    </script>

</body>
</html>
<?php
// Free results dan close connection (dari Kode Asli Anda)
if (isset($result_detail) && $result_detail instanceof mysqli_result) mysqli_free_result($result_detail);
if (isset($barangs_result) && $barangs_result instanceof mysqli_result) mysqli_free_result($barangs_result);
if (isset($jasas_result) && $jasas_result instanceof mysqli_result) mysqli_free_result($jasas_result);
if (isset($result_pembayaran) && $result_pembayaran instanceof mysqli_result) mysqli_free_result($result_pembayaran);
mysqli_close($koneksi);
?>