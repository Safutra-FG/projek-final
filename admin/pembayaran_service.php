<?php
include '../koneksi.php';
include 'auth.php';


$namaAkun = getNamaUser();

$id_service_dipilih = null;
$service_info = null;
$sisa_tagihan = 0;
$customer_info = null;
$total_tagihan_aktual = 0;
$error_message_service = null;
$success_message_payment = null;

// Logika untuk Tahap 1: Jika admin mencari service berdasarkan ID
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['cari_id_service'])) {
    $id_service_input = trim($_GET['id_service']);
    if (!empty($id_service_input)) {
        // Query untuk mengambil data service, customer, dan total tagihan dari detail_service
        // Query baru untuk mengambil data service, customer, tagihan, DAN total yang sudah dibayar
        $sql_cari = "SELECT
                s.id_service, s.device, s.status AS status_service_sekarang,
                c.id_customer, c.nama_customer,
                COALESCE(SUM(ds.total), 0) AS total_tagihan,
                (SELECT COALESCE(SUM(b.jumlah), 0)
                FROM transaksi t
                JOIN bayar b ON t.id_transaksi = b.id_transaksi
                WHERE t.id_service = s.id_service AND t.jenis = 'service') AS total_dibayar,
                (SELECT b.bukti 
                FROM transaksi t 
                JOIN bayar b ON t.id_transaksi = b.id_transaksi 
                WHERE t.id_service = s.id_service 
                AND b.status = 'menunggu konfirmasi' 
                ORDER BY b.tanggal DESC LIMIT 1) AS bukti_pembayaran_terakhir
            FROM service s
            JOIN customer c ON s.id_customer = c.id_customer
            LEFT JOIN detail_service ds ON s.id_service = ds.id_service
            WHERE s.id_service = ?
            GROUP BY s.id_service, c.id_customer";

        $stmt_cari = $koneksi->prepare($sql_cari);
        if ($stmt_cari) {
            $stmt_cari->bind_param("s", $id_service_input);
            $stmt_cari->execute();
            $result_cari = $stmt_cari->get_result();
            if ($result_cari->num_rows > 0) {
                $data_service_dan_tagihan = $result_cari->fetch_assoc();
                $id_service_dipilih = $data_service_dan_tagihan['id_service'];
                $service_info = [
                    'device' => $data_service_dan_tagihan['device'],
                    'status_service_sekarang' => $data_service_dan_tagihan['status_service_sekarang']
                ];
                $customer_info = [
                    'id_customer' => $data_service_dan_tagihan['id_customer'],
                    'nama_customer' => $data_service_dan_tagihan['nama_customer']
                ];
                $total_tagihan_aktual = $data_service_dan_tagihan['total_tagihan'];
                $total_sudah_dibayar = $data_service_dan_tagihan['total_dibayar'];
                $bukti_pembayaran = $data_service_dan_tagihan['bukti_pembayaran_terakhir'];
                $sisa_tagihan = $total_tagihan_aktual - $total_sudah_dibayar;

                // Logika untuk menentukan status pembayaran dan warna badge
                if ($total_sudah_dibayar <= 0) {
                    $status_pembayaran_info = "Belum Bayar";
                    $badge_class = "bg-red-200 text-red-800";
                } elseif ($total_sudah_dibayar < $total_tagihan_aktual) {
                    $status_pembayaran_info = "Sudah DP";
                    $badge_class = "bg-orange-200 text-orange-800";
                } else {
                    $status_pembayaran_info = "Lunas";
                    $badge_class = "bg-green-200 text-green-800";
                }
            } else {
                $error_message_service = "Service dengan ID \"" . htmlspecialchars($id_service_input) . "\" tidak ditemukan.";
            }
            $stmt_cari->close();
        } else {
            $error_message_service = "Gagal menyiapkan query pencarian: " . $koneksi->error;
        }
    } else {
        $error_message_service = "Silakan masukkan ID Service.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_pembayaran'])) {
    // Ambil semua data dari form
    $id_service_proses = $_POST['id_service_proses'];
    $id_customer_proses = $_POST['id_customer_proses'];
    $total_tagihan_hidden = floatval($_POST['total_tagihan_hidden']);

    $tanggal_pembayaran = $_POST['tanggal_pembayaran'];
    $jumlah_dibayar = floatval($_POST['jumlah_dibayar']);
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $status_pembayaran = $_POST['status_pembayaran'];
    $catatan_pembayaran = trim($_POST['catatan_pembayaran']);

    // Tentukan nilai untuk kolom status di tabel transaksi
    $status_transaksi_db = ($status_pembayaran == 'Lunas') ? 'lunas' : 'menunggu pembayaran';

    // Inisialisasi variabel untuk path bukti pembayaran
    $path_bukti_pembayaran = null;

    // Proses upload bukti pembayaran jika ada
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
        $file = $_FILES['bukti_pembayaran'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];

        // Validasi ukuran file (maksimal 2MB)
        $max_size = 2 * 1024 * 1024; // 2MB dalam bytes
        if ($file_size > $max_size) {
            $error_message_payment = "Ukuran file terlalu besar. Maksimal 2MB.";
        } else {
            // Validasi tipe file
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $file_type = mime_content_type($file_tmp);
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message_payment = "Tipe file tidak didukung. Gunakan JPG, PNG, atau PDF.";
            } else {
                // Buat direktori jika belum ada
                $upload_dir = "../uploads/bukti_pembayaran/" . date('Y/m');
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Generate nama file unik
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_file_name = "bukti_" . $id_service_proses . "_" . time() . "." . $file_extension;
                $target_path = $upload_dir . "/" . $new_file_name;

                // Upload file
                if (move_uploaded_file($file_tmp, $target_path)) {
                    $path_bukti_pembayaran = "uploads/bukti_pembayaran/" . date('Y/m') . "/" . $new_file_name;
                } else {
                    $error_message_payment = "Gagal mengupload file. Silakan coba lagi.";
                }
            }
        }
    }

    // Validasi dasar
    if (empty($id_service_proses) || empty($id_customer_proses) || empty($tanggal_pembayaran) || $jumlah_dibayar <= 0 || empty($metode_pembayaran) || empty($status_pembayaran)) {
        $error_message_payment = "Semua field yang wajib diisi.";
    } else {
        // Mulai transaksi database
        $koneksi->begin_transaction();
        try {
            // 2. Perbarui SQL INSERT untuk tabel `transaksi`
            // PASTIKAN nama kolomnya adalah `status`. Jika `status_transaksi`, ganti di bawah.
            $sql_insert_transaksi = "INSERT INTO transaksi (id_service, id_customer, jenis, status, tanggal, total) 
                                     VALUES (?, ?, 'service', ?, ?, ?)";
            $stmt_transaksi = $koneksi->prepare($sql_insert_transaksi);
            if (!$stmt_transaksi) throw new Exception("Prepare `transaksi` gagal: " . $koneksi->error);

            $tanggal_transaksi_dicatat = date('Y-m-d H:i:s');

            // 3. Perbarui `bind_param` dengan menambahkan $status_transaksi_db (tipe: s)
            $stmt_transaksi->bind_param("iissd", $id_service_proses, $id_customer_proses, $status_transaksi_db, $tanggal_transaksi_dicatat, $total_tagihan_hidden);
            if (!$stmt_transaksi->execute()) throw new Exception("Insert `transaksi` gagal: " . $stmt_transaksi->error);
            $id_transaksi_baru = $koneksi->insert_id;
            $stmt_transaksi->close();

            // --- AKHIR DARI PERBAIKAN ---

            // Bagian `bayar` dan `commit/rollback` tetap sama
            // Buat Record di `bayar`
            $sql_insert_bayar = "INSERT INTO bayar (id_transaksi, tanggal, jumlah, metode, status, bukti, catatan)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_bayar = $koneksi->prepare($sql_insert_bayar);
            if (!$stmt_bayar) throw new Exception("Prepare `bayar` gagal: " . $koneksi->error);

            $timestamp_pembayaran = date('Y-m-d H:i:s', strtotime($tanggal_pembayaran . " " . date("H:i:s")));

            $stmt_bayar->bind_param("isdssss", $id_transaksi_baru, $timestamp_pembayaran, $jumlah_dibayar, $metode_pembayaran, $status_pembayaran, $path_bukti_pembayaran, $catatan_pembayaran);
            if (!$stmt_bayar->execute()) throw new Exception("Insert `bayar` gagal: " . $stmt_bayar->error);
            $stmt_bayar->close();

            // Update status pembayaran yang menunggu konfirmasi
            if (isset($bukti_pembayaran) && $bukti_pembayaran) {
                $sql_update_bayar = "UPDATE bayar b 
                                    JOIN transaksi t ON b.id_transaksi = t.id_transaksi 
                                    SET b.status = 'dikonfirmasi' 
                                    WHERE t.id_service = ? AND b.status = 'menunggu konfirmasi'";
                $stmt_update = $koneksi->prepare($sql_update_bayar);
                $stmt_update->bind_param("s", $id_service_proses);
                $stmt_update->execute();
                $stmt_update->close();
            }

            // Hitung total pembayaran setelah pembayaran baru
            $sql_total_bayar_setelah = "SELECT COALESCE(SUM(b.jumlah), 0) as total_bayar 
                                       FROM transaksi t 
                                       JOIN bayar b ON t.id_transaksi = b.id_transaksi 
                                       WHERE t.id_service = ?";
            $stmt_total_setelah = $koneksi->prepare($sql_total_bayar_setelah);
            $stmt_total_setelah->bind_param("s", $id_service_proses);
            $stmt_total_setelah->execute();
            $result_total_setelah = $stmt_total_setelah->get_result();
            $total_bayar_setelah = 0;
            if ($row_total_setelah = $result_total_setelah->fetch_assoc()) {
                $total_bayar_setelah = $row_total_setelah['total_bayar'];
            }
            $stmt_total_setelah->close();

            // Update status transaksi berdasarkan total pembayaran vs total tagihan
            $status_transaksi_baru = 'menunggu pembayaran';
            if ($total_bayar_setelah >= $total_tagihan_hidden && $total_tagihan_hidden > 0) {
                $status_transaksi_baru = 'lunas';
            } elseif ($total_bayar_setelah > 0) {
                $status_transaksi_baru = 'dp';
            }

            // Update status transaksi yang baru dibuat
            $sql_update_transaksi_status = "UPDATE transaksi SET status = ? WHERE id_transaksi = ?";
            $stmt_update_transaksi_status = $koneksi->prepare($sql_update_transaksi_status);
            $stmt_update_transaksi_status->bind_param("si", $status_transaksi_baru, $id_transaksi_baru);
            $stmt_update_transaksi_status->execute();
            $stmt_update_transaksi_status->close();

            // Jika semua berhasil
            $koneksi->commit();
            $success_message_payment = "Pembayaran untuk service ID " . htmlspecialchars($id_service_proses) . " berhasil dicatat! Status transaksi: " . ucfirst($status_transaksi_baru);
            // Reset form
            $id_service_dipilih = null;
            $service_info = null;
            $customer_info = null;
            $total_tagihan_aktual = 0;
        } catch (Exception $e) {
            $koneksi->rollback();
            $error_message_payment = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verifikasi_submit'])) {
    $id_bayar = intval($_POST['verifikasi_id_bayar']);
    $id_transaksi = intval($_POST['verifikasi_id_transaksi']);
    $jumlah = floatval($_POST['jumlah_dibayar']);
    $status = $_POST['status_pembayaran']; // 'lunas' atau 'dp'
    $catatan = trim($_POST['catatan_pembayaran']);

    $koneksi->begin_transaction();
    try {
        // Update data bayar
        $sql_update_bayar = "UPDATE bayar SET jumlah = ?, status = ?, catatan = ? WHERE id_bayar = ?";
        $stmt_update_bayar = $koneksi->prepare($sql_update_bayar);
        $stmt_update_bayar->bind_param("dssi", $jumlah, $status, $catatan, $id_bayar);
        $stmt_update_bayar->execute();
        $stmt_update_bayar->close();

        // Hitung total pembayaran untuk service ini
        $sql_total_bayar = "SELECT COALESCE(SUM(b.jumlah), 0) as total_bayar 
                           FROM transaksi t 
                           JOIN bayar b ON t.id_transaksi = b.id_transaksi 
                           WHERE t.id_service = (SELECT id_service FROM transaksi WHERE id_transaksi = ?)";
        $stmt_total = $koneksi->prepare($sql_total_bayar);
        $stmt_total->bind_param("i", $id_transaksi);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $total_bayar = 0;
        if ($row_total = $result_total->fetch_assoc()) {
            $total_bayar = $row_total['total_bayar'];
        }
        $stmt_total->close();

        // Ambil total tagihan service
        $sql_tagihan = "SELECT COALESCE(SUM(ds.total), 0) as total_tagihan 
                       FROM service s 
                       LEFT JOIN detail_service ds ON s.id_service = ds.id_service 
                       WHERE s.id_service = (SELECT id_service FROM transaksi WHERE id_transaksi = ?)";
        $stmt_tagihan = $koneksi->prepare($sql_tagihan);
        $stmt_tagihan->bind_param("i", $id_transaksi);
        $stmt_tagihan->execute();
        $result_tagihan = $stmt_tagihan->get_result();
        $total_tagihan = 0;
        if ($row_tagihan = $result_tagihan->fetch_assoc()) {
            $total_tagihan = $row_tagihan['total_tagihan'];
        }
        $stmt_tagihan->close();

        // Update status transaksi berdasarkan total pembayaran vs total tagihan
        $status_transaksi_baru = 'menunggu pembayaran';
        if ($total_bayar >= $total_tagihan && $total_tagihan > 0) {
            $status_transaksi_baru = 'lunas';
        } elseif ($total_bayar > 0) {
            $status_transaksi_baru = 'dp';
        }

        $sql_update_transaksi = "UPDATE transaksi SET status = ? WHERE id_transaksi = ?";
        $stmt_update_transaksi = $koneksi->prepare($sql_update_transaksi);
        $stmt_update_transaksi->bind_param("si", $status_transaksi_baru, $id_transaksi);
        $stmt_update_transaksi->execute();
        $stmt_update_transaksi->close();

        $koneksi->commit();
        $success_message_payment = "Pembayaran berhasil diverifikasi! Status transaksi: " . ucfirst($status_transaksi_baru);
    } catch (Exception $e) {
        $koneksi->rollback();
        $error_message_payment = "Gagal verifikasi pembayaran: " . $e->getMessage();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tolak_submit'])) {
    $id_bayar = intval($_POST['verifikasi_id_bayar']);
    $catatan = trim($_POST['catatan_pembayaran']);
    $koneksi->begin_transaction();
    try {
        $sql_update_bayar = "UPDATE bayar SET status = 'ditolak', catatan = ? WHERE id_bayar = ?";
        $stmt_update_bayar = $koneksi->prepare($sql_update_bayar);
        $stmt_update_bayar->bind_param("si", $catatan, $id_bayar);
        $stmt_update_bayar->execute();
        $stmt_update_bayar->close();
        $koneksi->commit();
        $success_message_payment = "Pembayaran berhasil ditolak!";
    } catch (Exception $e) {
        $koneksi->rollback();
        $error_message_payment = "Gagal menolak pembayaran: " . $e->getMessage();
    }
}

// Tambahkan setelah pengambilan data service dan pembayaran
if ($id_service_dipilih) {
    // Ambil pembayaran menunggu konfirmasi
    $sql_bayar_pending = "SELECT b.*, t.id_transaksi FROM bayar b JOIN transaksi t ON b.id_transaksi = t.id_transaksi WHERE t.id_service = ? AND b.status = 'menunggu konfirmasi' ORDER BY b.tanggal DESC";
    $stmt_bayar_pending = $koneksi->prepare($sql_bayar_pending);
    $stmt_bayar_pending->bind_param("i", $id_service_dipilih);
    $stmt_bayar_pending->execute();
    $result_bayar_pending = $stmt_bayar_pending->get_result();
    $pembayaran_pending = [];
    while ($row = $result_bayar_pending->fetch_assoc()) {
        $pembayaran_pending[] = $row;
    }
    $stmt_bayar_pending->close();
}

// Cek jika ada pembayaran pending
$pending = !empty($pembayaran_pending) ? $pembayaran_pending[0] : null;

// --- Tambahan: Daftar Pembayaran Service dengan Filter Status ---
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'menunggu konfirmasi';
$daftar_pembayaran_service = [];

// --- Pagination untuk daftar pembayaran service ---
$items_per_page = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Hitung total data untuk pagination
$sql_count = "SELECT COUNT(*) as total FROM transaksi t WHERE t.jenis = 'service' AND t.status = ?";
$stmt_count = $koneksi->prepare($sql_count);
$stmt_count->bind_param("s", $status_filter);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_items = ($row = $result_count->fetch_assoc()) ? $row['total'] : 0;
$stmt_count->close();
$total_pages = ceil($total_items / $items_per_page);

// Query data dengan LIMIT dan OFFSET
$sql_daftar_service = "SELECT 
    t.id_transaksi, 
    t.id_service,
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
    t.jenis = 'service' 
    AND t.status = ?
ORDER BY 
    t.tanggal ASC
LIMIT ? OFFSET ?";

$stmt_service = $koneksi->prepare($sql_daftar_service);
if ($stmt_service) {
    $stmt_service->bind_param("sii", $status_filter, $items_per_page, $offset);
    $stmt_service->execute();
    $result_service = $stmt_service->get_result();
    $daftar_pembayaran_service = [];
    while ($row = $result_service->fetch_assoc()) {
        $daftar_pembayaran_service[] = $row;
    }
    $stmt_service->close();
} else {
    $error_db = "Gagal menyiapkan query daftar pembayaran service: " . $koneksi->error;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Input Pembayaran Service - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tambahan style jika perlu */
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Pembayaran service</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <span class="text-2xl">🔔</span>
                    </button>
                    <div class="flex items-center space-x-3">
                        <span class="text-xl text-gray-600">👤</span>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-auto">
                <?php if ($success_message_payment): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Berhasil!</p>
                        <p><?php echo htmlspecialchars($success_message_payment); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_message_payment) && $error_message_payment): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Gagal!</p>
                        <p><?php echo htmlspecialchars($error_message_payment); ?></p>
                    </div>
                <?php endif; ?>


                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">1. Identifikasi Service</h3>
                    <form action="" method="GET" class="flex items-end space-x-4">
                        <div>
                            <label for="id_service" class="block text-sm font-medium text-gray-700 mb-1">Masukkan ID Service:</label>
                            <input type="text" name="id_service" id="id_service" value="<?php echo isset($_GET['id_service']) ? htmlspecialchars($_GET['id_service']) : ''; ?>" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm w-full lg:w-80" placeholder="Contoh: SRV001">
                        </div>
                        <button type="submit" name="cari_id_service" class="px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm font-medium">
                            Cari Service
                        </button>
                    </form>
                    <?php if ($error_message_service): ?>
                        <p class="text-red-500 text-sm mt-2"><?php echo htmlspecialchars($error_message_service); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($id_service_dipilih && $service_info && $customer_info): ?>
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                        <h3 class="text-xl font-semibold mb-4 text-gray-700">2. Detail Service & Tagihan</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div><strong class="text-gray-600">ID Service:</strong> <?php echo htmlspecialchars($id_service_dipilih); ?></div>
                            <div><strong class="text-gray-600">Nama Customer:</strong> <?php echo htmlspecialchars($customer_info['nama_customer']); ?></div>
                            <div><strong class="text-gray-600">Perangkat:</strong> <?php echo htmlspecialchars($service_info['device']); ?></div>
                            <div><strong class="text-gray-600">Status Service:</strong> <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-200 text-yellow-800"><?php echo htmlspecialchars($service_info['status_service_sekarang']); ?></span></div>
                        </div>
                        <hr class="my-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <strong class="block text-gray-600">Total Tagihan:</strong>
                                <span class="font-bold text-lg text-gray-800">Rp <?php echo number_format($total_tagihan_aktual, 0, ',', '.'); ?></span>
                            </div>
                            <div>
                                <strong class="block text-gray-600">Sudah Dibayar:</strong>
                                <span class="font-bold text-lg text-green-600">Rp <?php echo number_format($total_sudah_dibayar, 0, ',', '.'); ?></span>
                            </div>
                            <div>
                                <strong class="block text-gray-600">Sisa Tagihan:</strong>
                                <span class="font-bold text-lg text-red-600">Rp <?php echo number_format($sisa_tagihan, 0, ',', '.'); ?></span>
                            </div>
                            <div class="md:col-span-3 mt-2">
                                <strong class="text-gray-600">Status Pembayaran:</strong>
                                <span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_pembayaran_info); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($sisa_tagihan > 0): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md">
                            <h3 class="text-xl font-semibold mb-6 text-gray-700">3. Input/Verifikasi Pembayaran</h3>
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                                <?php if ($pending): ?>
                                    <input type="hidden" name="verifikasi_id_bayar" value="<?php echo intval($pending['id_bayar']); ?>">
                                    <input type="hidden" name="verifikasi_id_transaksi" value="<?php echo intval($pending['id_transaksi']); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="id_service_proses" value="<?php echo htmlspecialchars($id_service_dipilih); ?>">
                                <input type="hidden" name="id_customer_proses" value="<?php echo htmlspecialchars($customer_info['id_customer']); ?>">
                                <input type="hidden" name="total_tagihan_hidden" value="<?php echo htmlspecialchars($total_tagihan_aktual); ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                    <div>
                                        <label for="tanggal_pembayaran" class="block text-sm font-medium text-gray-700">Tanggal Pembayaran Diterima <span class="text-red-500">*</span></label>
                                        <input type="date" name="tanggal_pembayaran" id="tanggal_pembayaran" value="<?php echo $pending ? date('Y-m-d', strtotime($pending['tanggal'])) : date('Y-m-d'); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="jumlah_dibayar" class="block text-sm font-medium text-gray-700">Jumlah Dibayar <span class="text-red-500">*</span></label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">Rp</span>
                                            </div>
                                            <input type="number" name="jumlah_dibayar" id="jumlah_dibayar" value="<?php echo $pending ? intval($pending['jumlah']) : ($sisa_tagihan > 0 ? $sisa_tagihan : ''); ?>" required class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700">Metode Pembayaran <span class="text-red-500">*</span></label>
                                        <select id="metode_pembayaran" name="metode_pembayaran" required class="block w-full pl-2 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="Cash" <?php echo ($pending && strtolower($pending['metode']) == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                            <option value="Transfer" <?php echo ($pending && strtolower($pending['metode']) == 'transfer') ? 'selected' : ''; ?>>Transfer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="status_pembayaran" class="block text-sm font-medium text-gray-700">Status Pembayaran <span class="text-red-500">*</span></label>
                                        <select id="status_pembayaran" name="status_pembayaran" required class="block w-full pl-2 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="Lunas" <?php echo ($pending && $pending['status'] == 'lunas') ? 'selected' : ''; ?>>Lunas</option>
                                            <option value="DP" <?php echo ($pending && strtolower($pending['status']) == 'dp') ? 'selected' : ''; ?>>DP (Down Payment)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="bukti_pembayaran" class="block text-sm font-medium text-gray-700">Bukti Pembayaran</label>
                                        <?php if ($pending && $pending['bukti']): ?>
                                            <?php $file_extension = strtolower(pathinfo($pending['bukti'], PATHINFO_EXTENSION)); ?>
                                            <div class="mt-2">
                                                <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($pending['bukti']); ?>" alt="Bukti Pembayaran" class="max-w-xs rounded-lg shadow-md cursor-pointer" onclick="window.open('../<?php echo htmlspecialchars($pending['bukti']); ?>', '_blank')">
                                                <?php elseif ($file_extension == 'pdf'): ?>
                                                    <a href="../<?php echo htmlspecialchars($pending['bukti']); ?>" target="_blank" class="text-blue-600 hover:underline">Lihat Bukti PDF</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" accept="image/*,.pdf" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <p class="mt-1 text-sm text-gray-500">Format yang didukung: JPG, PNG, PDF (Maks. 2MB)</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="catatan_pembayaran" class="block text-sm font-medium text-gray-700">Catatan Tambahan</label>
                                        <textarea name="catatan_pembayaran" id="catatan_pembayaran" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Tambahkan catatan jika diperlukan..."><?php echo $pending ? htmlspecialchars($pending['catatan']) : ''; ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end gap-2">
                                    <button type="submit" name="<?php echo $pending ? 'verifikasi_submit' : 'simpan_pembayaran'; ?>" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 font-medium">
                                        <?php echo $pending ? 'Setujui' : 'Simpan Pembayaran'; ?>
                                    </button>
                                    <?php if ($pending): ?>
                                        <button type="submit" name="tolak_submit" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 font-medium" onclick="return confirm('Yakin tolak pembayaran ini?')">
                                            Tolak
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md" role="alert">
                            <p class="font-bold">Informasi</p>
                            <p>Tagihan untuk service ini belum ada Tagihan/Lunas.</p>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Lihat Daftar Pembayaran Service Berdasarkan Status</h3>
                    <form action="" method="GET" class="flex items-center space-x-4 mb-4">
                        <label for="status_filter_select" class="text-sm font-medium text-gray-700">Tampilkan:</label>
                        <select id="status_filter_select" name="status_filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-50 focus:ring-blue-500 focus:border-blue-500 focus:outline-none" onchange="this.form.submit()">
                            <option value="menunggu konfirmasi" <?php if ($status_filter == 'menunggu konfirmasi') echo 'selected'; ?>>Menunggu Konfirmasi</option>
                            <option value="menunggu pembayaran" <?php if ($status_filter == 'menunggu pembayaran') echo 'selected'; ?>>Menunggu Pembayaran</option>
                            <option value="lunas" <?php if ($status_filter == 'lunas') echo 'selected'; ?>>Lunas</option>
                            <option value="dp" <?php if ($status_filter == 'dp') echo 'selected'; ?>>DP</option>
                            <option value="ditolak" <?php if ($status_filter == 'ditolak') echo 'selected'; ?>>Ditolak</option>
                        </select>
                        <noscript><button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md">Filter</button></noscript>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaksi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Service</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Pesan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode Bayar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan Admin</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($daftar_pembayaran_service)): ?>
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada pesanan dengan status yang dipilih.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daftar_pembayaran_service as $pesanan): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">#<?php echo htmlspecialchars($pesanan['id_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($pesanan['id_service']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($pesanan['nama_customer']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesan'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">Rp <?php echo number_format($pesanan['total_tagihan'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                <?= htmlspecialchars(ucwords($pesanan['metode'] ?? '-')) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                <?php if ($pesanan['bukti']): ?>
                                                    <a href="../<?= htmlspecialchars($pesanan['bukti']) ?>" target="_blank" class="text-blue-600 hover:underline" >Lihat</a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-700 text-center">
                                                <?= nl2br(htmlspecialchars($pesanan['catatan_bayar'] ?? '')) ?>
                                                <?php if (!$pesanan['catatan_bayar']): ?>
                                                    <span class="text-gray-400" >N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium">
                                                <form action="" method="GET" class="inline">
                                                    <input type="hidden" name="id_service" value="<?php echo htmlspecialchars($pesanan['id_service']); ?>">
                                                    <button type="submit" name="cari_id_service" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded text-xs inline-block">
                                                        <i class="bi bi-search"></i> Proses
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <nav class="inline-flex rounded-md shadow-sm" aria-label="Pagination">
                            <?php $prev_page = $page - 1; $next_page = $page + 1; ?>
                            <a href="?status_filter=<?= urlencode($status_filter) ?>&page=<?= $prev_page ?>" class="px-3 py-2 border border-gray-300 bg-white text-sm font-medium rounded-l-md <?= $page <= 1 ? 'text-gray-400 cursor-not-allowed' : 'hover:bg-gray-50 text-gray-700' ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Sebelumnya</a>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?status_filter=<?= urlencode($status_filter) ?>&page=<?= $i ?>" class="px-3 py-2 border-t border-b border-gray-300 bg-white text-sm font-medium <?= $i == $page ? 'text-blue-600 font-bold' : 'text-gray-700 hover:bg-gray-50' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="?status_filter=<?= urlencode($status_filter) ?>&page=<?= $next_page ?>" class="px-3 py-2 border border-gray-300 bg-white text-sm font-medium rounded-r-md <?= $page >= $total_pages ? 'text-gray-400 cursor-not-allowed' : 'hover:bg-gray-50 text-gray-700' ?>" <?= $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Berikutnya</a>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php $koneksi->close(); ?>
</body>

</html>