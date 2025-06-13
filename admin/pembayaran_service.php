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
        // ... sekitar baris 33
        $sql_cari = "SELECT
                s.id_service, s.device, s.status AS status_service_sekarang,
                c.id_customer, c.nama_customer,
                COALESCE(SUM(ds.total), 0) AS total_tagihan,
                (SELECT COALESCE(SUM(b.jumlah), 0)
                FROM transaksi t
                JOIN bayar b ON t.id_transaksi = b.id_transaksi
                WHERE t.id_service = s.id_service AND t.jenis = 'service') AS total_dibayar
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
                // ...
                $total_tagihan_aktual = $data_service_dan_tagihan['total_tagihan'];
                $total_sudah_dibayar = $data_service_dan_tagihan['total_dibayar']; // Ambil data baru dari query
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
                // ...
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

            // Jika semua berhasil
            $koneksi->commit();
            $success_message_payment = "Pembayaran untuk service ID " . htmlspecialchars($id_service_proses) . " berhasil dicatat!";
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
                <h2 class="text-2xl font-bold text-gray-800">Riwayat Transaksi</h2>
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
                            <h3 class="text-xl font-semibold mb-6 text-gray-700">3. Input Detail Pembayaran Diterima</h3>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id_service_proses" value="<?php echo htmlspecialchars($id_service_dipilih); ?>">
                                <input type="hidden" name="id_customer_proses" value="<?php echo htmlspecialchars($customer_info['id_customer']); ?>">
                                <input type="hidden" name="total_tagihan_hidden" value="<?php echo htmlspecialchars($total_tagihan_aktual); ?>">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
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
                                            <input type="number" name="jumlah_dibayar" id="jumlah_dibayar" value="<?php echo $sisa_tagihan > 0 ? $sisa_tagihan : ''; ?>" required class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700">Metode Pembayaran <span class="text-red-500">*</span></label>
                                        <select id="metode_pembayaran" name="metode_pembayaran" required class="block w-full pl-2 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="Cash">Cash</option>
                                            <option value="Transfer">Transfer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="status_pembayaran" class="block text-sm font-medium text-gray-700">Status Pembayaran <span class="text-red-500">*</span></label>
                                        <select id="status_pembayaran" name="status_pembayaran" required class="block w-full pl-2 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="Lunas">Lunas</option>
                                            <option value="DP">DP (Down Payment)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="bukti_pembayaran" class="block text-sm font-medium text-gray-700">Bukti Pembayaran</label>
                                        <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" accept="image/*,.pdf" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        <p class="mt-1 text-sm text-gray-500">Format yang didukung: JPG, PNG, PDF (Maks. 2MB)</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="catatan_pembayaran" class="block text-sm font-medium text-gray-700">Catatan Tambahan</label>
                                        <textarea name="catatan_pembayaran" id="catatan_pembayaran" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <button type="submit" name="simpan_pembayaran" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 font-medium">
                                        Simpan Pembayaran
                                    </button>
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
            </div>
        </div>
    </div>
    <?php $koneksi->close(); ?>
</body>

</html>