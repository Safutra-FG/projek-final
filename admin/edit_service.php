<?php
session_start(); // Untuk CSRF token dan pesan flash (jika dikembangkan)
include '../koneksi.php';

$namaAkun = 'Admin';

// Fungsi untuk menghasilkan CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$id_service = null;
if (isset($_GET['id'])) {
    $id_service = intval($_GET['id']);
} else {
    echo "ID Service tidak ditemukan."; // Pesan sederhana untuk sekarang
    exit;
}

$error_messages = []; // Tampung pesan error
$success_message = ""; // Tampung pesan sukses
$total_biaya_service = 0;
$pembayaran_history = [];
$total_sudah_dibayar = 0;


// Ambil data service yang akan diedit
$query_service = "SELECT s.*, c.nama_customer, c.no_telepon, c.email 
                FROM service s 
                left join customer c on s.id_customer = c.id_customer 
                WHERE id_service = ?";
$stmt_service = $koneksi->prepare($query_service);
$stmt_service->bind_param("i", $id_service);
$stmt_service->execute();
$result_service = $stmt_service->get_result();

if (!$result_service || $result_service->num_rows == 0) {
    echo "Data service tidak ditemukan.";
    exit;
}
$service = $result_service->fetch_assoc();
$stmt_service->close();

// Ambil data detail service
$query_detail = "SELECT ds.*, b.nama_barang, j.jenis_jasa 
                 FROM detail_service ds
                 LEFT JOIN stok b ON ds.id_barang = b.id_barang
                 LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa
                 WHERE ds.id_service = ? ORDER BY ds.id_ds ASC";
$stmt_detail = $koneksi->prepare($query_detail);
$stmt_detail->bind_param("i", $id_service);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

// Hitung total biaya dari detail service
if ($result_detail) {
    mysqli_data_seek($result_detail, 0);
    while ($detail_row = mysqli_fetch_assoc($result_detail)) {
        $total_biaya_service += $detail_row['total'];
    }
}

// Hitung total sudah dibayar dari SEMUA transaksi service terkait
$query_total_dibayar = "SELECT COALESCE(SUM(b.jumlah), 0) AS total_dibayar
                        FROM transaksi t
                        JOIN bayar b ON t.id_transaksi = b.id_transaksi
                        WHERE t.id_service = ? AND t.jenis = 'service'";
$stmt_total_dibayar = $koneksi->prepare($query_total_dibayar);
$stmt_total_dibayar->bind_param("i", $id_service);
$stmt_total_dibayar->execute();
$result_total_dibayar = $stmt_total_dibayar->get_result();
if ($row_total = $result_total_dibayar->fetch_assoc()) {
    $total_sudah_dibayar = $row_total['total_dibayar'];
}
$stmt_total_dibayar->close();

// Ambil riwayat detail pembayaran untuk ditampilkan
$query_pembayaran_history = "SELECT b.*
                             FROM bayar b
                             JOIN transaksi t ON b.id_transaksi = t.id_transaksi
                             WHERE t.id_service = ? AND t.jenis = 'service'
                             ORDER BY b.tanggal ASC";
$stmt_history = $koneksi->prepare($query_pembayaran_history);
$stmt_history->bind_param("i", $id_service);
$stmt_history->execute();
$result_history = $stmt_history->get_result();
while ($pembayaran_row = $result_history->fetch_assoc()) {
    $pembayaran_history[] = $pembayaran_row;
}
$stmt_history->close();

// Hitung sisa pembayaran dan tentukan status (logika ini tidak berubah)
$sisa_pembayaran = $total_biaya_service - $total_sudah_dibayar;
$status_pembayaran = 'BELUM DIBUATKAN TAGIHAN';
$status_badge_class = 'bg-gray-200 text-gray-800';

if ($pembayaran_history) { // Ubah status hanya jika transaksi sudah ada
    $status_pembayaran = 'Menunggu Pembayaran';
    $status_badge_class = 'bg-red-100 text-red-800';
    if ($sisa_pembayaran <= 0 && $total_biaya_service > 0) {
        $status_pembayaran = 'Lunas-Siap Diambil';
        $status_badge_class = 'bg-green-100 text-green-800';
    } elseif ($total_sudah_dibayar > 0) {
        $status_pembayaran = 'DP / SEBAGIAN';
        $status_badge_class = 'bg-yellow-100 text-yellow-800';
    }
}

// Ambil data untuk dropdown
$barangs_result = mysqli_query($koneksi, "SELECT id_barang, nama_barang, harga FROM stok WHERE stok > 0 ORDER BY nama_barang");
$jasas_result = mysqli_query($koneksi, "SELECT id_jasa, jenis_jasa, harga FROM jasa ORDER BY jenis_jasa");

function getStatusBadge($status)
{
    switch (strtolower($status)) {
        case 'selesai':
        case 'siap diambil':
        case 'sudah diambil':
            return 'bg-green-100 text-green-800';
        case 'dibatalkan':
            return 'bg-red-100 text-red-800';
        case 'diperbaiki':
        case 'dikonfirmasi':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-yellow-100 text-yellow-800';
    }
}

// Proses update data service utama dan penambahan detail service
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Cek apakah service sudah diambil
    if ($service['status'] == 'sudah diambil') {
        $error_messages[] = "Service ini sudah diambil oleh pelanggan dan tidak dapat diubah lagi.";
    } else {
        // 1. Validasi CSRF Token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error_messages[] = "Kesalahan validasi CSRF token. Silakan coba lagi.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $csrf_token = $_SESSION['csrf_token'];
        } else {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $id_service_post = intval($_POST['id_service']);

            mysqli_begin_transaction($koneksi);

            try {
                // Update data service utama
                $id_customer = mysqli_real_escape_string($koneksi, $_POST['id_customer']);
                $status_baru = mysqli_real_escape_string($koneksi, $_POST['status']);

                // Ambil status lama dan tanggal selesai saat ini
                $query_cek_status_lama = "SELECT status, tanggal_selesai FROM service WHERE id_service = ?";
                $stmt_cek_status = $koneksi->prepare($query_cek_status_lama);
                $stmt_cek_status->bind_param("i", $id_service_post);
                $stmt_cek_status->execute();
                $hasil_cek = $stmt_cek_status->get_result();
                $data_lama = $hasil_cek->fetch_assoc();
                $stmt_cek_status->close();

                $status_lama = null;
                $tanggal_selesai_saat_ini = null;
                if ($data_lama) {
                    $status_lama = $data_lama['status'];
                    $tanggal_selesai_saat_ini = $data_lama['tanggal_selesai'];
                }

                $set_parts = [];
                $bind_types_update = "";
                $bind_params_update = [];

                // Kolom yang diupdate
                $set_parts[] = "id_customer = ?";
                $bind_types_update .= "s";
                $bind_params_update[] = $id_customer;

                $set_parts[] = "status = ?";
                $bind_types_update .= "s";
                $bind_params_update[] = $status_baru;

                // Logika untuk tanggal_selesai
                $status_selesai = ['selesai', 'siap diambil'];
                if (!in_array($status_lama, $status_selesai) && in_array($status_baru, $status_selesai)) {
                    $set_parts[] = "tanggal_selesai = CURDATE()";
                } elseif (in_array($status_lama, $status_selesai) && !in_array($status_baru, $status_selesai)) {
                    $set_parts[] = "tanggal_selesai = NULL";
                }

                if (!empty($set_parts)) {
                    $query_update_service = "UPDATE service SET " . implode(", ", $set_parts) . " WHERE id_service = ?";
                    $stmt_update_service = $koneksi->prepare($query_update_service);

                    if ($stmt_update_service) {
                        $bind_types_update .= "i";
                        $bind_params_update[] = $id_service_post;
                        $stmt_update_service->bind_param($bind_types_update, ...$bind_params_update);
                        if (!$stmt_update_service->execute()) {
                            $error_messages[] = "Error updating data service utama: " . $stmt_update_service->error;
                        }
                        $stmt_update_service->close();
                    } else {
                        $error_messages[] = "Error preparing statement untuk service utama: " . $koneksi->error;
                    }
                }

                // Proses penambahan detail service baru
                if (empty($error_messages) && (!empty(trim($_POST['kerusakan_detail'])) || !empty($_POST['id_barang']) || !empty($_POST['id_jasa']))) {
                    $id_barang_val = !empty($_POST['id_barang']) ? intval($_POST['id_barang']) : null;
                    $id_jasa_val = !empty($_POST['id_jasa']) ? intval($_POST['id_jasa']) : null;
                    $kerusakan_detail_val = mysqli_real_escape_string($koneksi, $_POST['kerusakan_detail']);

                    $server_calculated_total_detail = 0;
                    if ($id_barang_val) {
                        $stmt_harga_brg = $koneksi->prepare("SELECT harga FROM stok WHERE id_barang = ?");
                        $stmt_harga_brg->bind_param("i", $id_barang_val);
                        $stmt_harga_brg->execute();
                        $res_brg = $stmt_harga_brg->get_result();
                        if ($row_brg = $res_brg->fetch_assoc()) {
                            $server_calculated_total_detail += (float)$row_brg['harga'];
                        }
                        $stmt_harga_brg->close();
                    }
                    if ($id_jasa_val) {
                        $stmt_harga_jsa = $koneksi->prepare("SELECT harga FROM jasa WHERE id_jasa = ?");
                        $stmt_harga_jsa->bind_param("i", $id_jasa_val);
                        $stmt_harga_jsa->execute();
                        $res_jsa = $stmt_harga_jsa->get_result();
                        if ($row_jsa = $res_jsa->fetch_assoc()) {
                            $server_calculated_total_detail += (float)$row_jsa['harga'];
                        }
                        $stmt_harga_jsa->close();
                    }

                    // Cek apakah ini adalah update atau insert baru
                    if (!empty($_POST['edit_id_ds'])) {
                        // Update detail service yang ada
                        $id_ds = intval($_POST['edit_id_ds']);

                        // Ambil data detail service lama
                        $stmt_old_detail = $koneksi->prepare("SELECT id_barang FROM detail_service WHERE id_ds = ?");
                        $stmt_old_detail->bind_param("i", $id_ds);
                        $stmt_old_detail->execute();
                        $old_detail = $stmt_old_detail->get_result()->fetch_assoc();
                        $stmt_old_detail->close();

                        // Kembalikan stok barang lama jika ada
                        if ($old_detail && $old_detail['id_barang']) {
                            $stmt_return_stok = $koneksi->prepare("UPDATE stok SET stok = stok + 1 WHERE id_barang = ?");
                            $stmt_return_stok->bind_param("i", $old_detail['id_barang']);
                            $stmt_return_stok->execute();
                            $stmt_return_stok->close();
                        }

                        // Cek stok barang baru jika ada
                        if ($id_barang_val !== null) {
                            $stmt_cek_stok = $koneksi->prepare("SELECT stok FROM stok WHERE id_barang = ? FOR UPDATE");
                            $stmt_cek_stok->bind_param("i", $id_barang_val);
                            $stmt_cek_stok->execute();
                            $result_stok = $stmt_cek_stok->get_result();
                            $data_stok = $result_stok->fetch_assoc();

                            if ($data_stok['stok'] <= 0) {
                                $error_messages[] = "Stok barang tidak mencukupi.";
                                $stmt_cek_stok->close();
                                return;
                            }

                            // Kurangi stok
                            $stmt_update_stok = $koneksi->prepare("UPDATE stok SET stok = stok - 1 WHERE id_barang = ?");
                            $stmt_update_stok->bind_param("i", $id_barang_val);
                            if (!$stmt_update_stok->execute()) {
                                $error_messages[] = "Gagal mengupdate stok barang: " . $stmt_update_stok->error;
                                $stmt_update_stok->close();
                                return;
                            }
                            $stmt_update_stok->close();
                            $stmt_cek_stok->close();
                        }

                        // Update detail service
                        $stmt_update_detail = $koneksi->prepare("UPDATE detail_service SET id_barang = ?, id_jasa = ?, kerusakan = ?, total = ? WHERE id_ds = ?");
                        if ($stmt_update_detail) {
                            $stmt_update_detail->bind_param("iisdi", $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $server_calculated_total_detail, $id_ds);
                            if (!$stmt_update_detail->execute()) {
                                $error_messages[] = "Error mengupdate detail service: " . $stmt_update_detail->error;
                            }
                            $stmt_update_detail->close();
                        } else {
                            $error_messages[] = "Error preparing statement untuk update detail service: " . $koneksi->error;
                        }
                    } else {
                        // Insert detail service baru
                        if ($id_barang_val !== null || $id_jasa_val !== null || !empty(trim($kerusakan_detail_val))) {
                            // Cek stok barang jika ada barang yang dipilih
                            if ($id_barang_val !== null) {
                                $stmt_cek_stok = $koneksi->prepare("SELECT stok FROM stok WHERE id_barang = ? FOR UPDATE");
                                $stmt_cek_stok->bind_param("i", $id_barang_val);
                                $stmt_cek_stok->execute();
                                $result_stok = $stmt_cek_stok->get_result();
                                $data_stok = $result_stok->fetch_assoc();

                                if ($data_stok['stok'] <= 0) {
                                    $error_messages[] = "Stok barang tidak mencukupi.";
                                    $stmt_cek_stok->close();
                                    return;
                                }

                                // Kurangi stok
                                $stmt_update_stok = $koneksi->prepare("UPDATE stok SET stok = stok - 1 WHERE id_barang = ?");
                                $stmt_update_stok->bind_param("i", $id_barang_val);
                                if (!$stmt_update_stok->execute()) {
                                    $error_messages[] = "Gagal mengupdate stok barang: " . $stmt_update_stok->error;
                                    $stmt_update_stok->close();
                                    return;
                                }
                                $stmt_update_stok->close();
                                $stmt_cek_stok->close();
                            }

                            $stmt_add_detail = $koneksi->prepare("INSERT INTO detail_service (id_service, id_barang, id_jasa, kerusakan, total) VALUES (?, ?, ?, ?, ?)");
                            if ($stmt_add_detail) {
                                $stmt_add_detail->bind_param("iiisd", $id_service_post, $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $server_calculated_total_detail);
                                if (!$stmt_add_detail->execute()) {
                                    $error_messages[] = "Error menambahkan detail service: " . $stmt_add_detail->error;
                                }
                                $stmt_add_detail->close();
                            } else {
                                $error_messages[] = "Error preparing statement untuk detail service: " . $koneksi->error;
                            }
                        }
                    }
                }

                if (empty($error_messages)) {
                    mysqli_commit($koneksi);
                    $success_message = "Data service berhasil diupdate";
                    if (isset($stmt_add_detail) && $stmt_add_detail) {
                        $success_message .= " dan detail baru berhasil ditambahkan!";
                    }

                    echo "<script>
                            alert('" . addslashes($success_message) . "');
                            window.location.href='edit_service.php?id=$id_service_post';
                          </script>";
                    exit;
                } else {
                    mysqli_rollback($koneksi);
                }
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error_messages[] = "Terjadi pengecualian: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Data Service - Thar'z Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        /* Gaya tambahan untuk select agar terlihat lebih rapi dengan Tailwind */
        .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #4A5568;
            background-color: #F7FAFC;
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
            appearance: none;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-select:focus {
            outline: none;
            border-color: #63B3ED;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }

        /* Custom Select2 Styles */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
            background-color: #F7FAFC;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 0.75rem;
            color: #4A5568;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .select2-dropdown {
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #4299E1;
        }

        .select2-search__field {
            padding: 0.5rem;
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
        }
    </style>
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
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">🏠</span>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="pembayaran_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Pembayaran Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="kelola_penjualan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300  hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💰</span>
                            <span class="font-medium">Kelola Penjualan</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                            <span class="text-xl">📝</span>
                            <span class="font-medium">Data Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">👥</span>
                            <span class="font-medium">Data Pelanggan</span>
                        </a>
                    </li>
                    <li>
                        <a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">💳</span>
                            <span class="font-medium">Riwayat Transaksi</span>
                        </a>
                    </li>
                    <li>
                        <a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📦</span>
                            <span class="font-medium">Stok Gudang</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Thar'z Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Detail Service</h2>
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

            <div class="container mx-auto p-4 sm:p-6 lg:p-8">
                <div class="mb-6">
                    <a href="data_service.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali
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
                                    <div>
                                        <span class="text-gray-500">ID Transaksi:</span>
                                        <p class="font-semibold text-gray-900">#<?php echo htmlspecialchars($service['id_service']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Tanggal Pesan:</span>
                                        <p class="font-semibold text-gray-900"><?php echo date('d M Y', strtotime($service['tanggal'])); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Nama Customer:</span>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['nama_customer']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Email:</span>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['email']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">No. Telepon:</span>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['no_telepon']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Device / Keluhan:</span>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['device']); ?> - <?php echo htmlspecialchars($service['keluhan']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Estimasi Waktu:</span>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['estimasi_waktu']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Estimasi Harga:</span>
                                        <p class="font-semibold text-gray-900">Rp <?php echo number_format($service['estimasi_harga'], 0, ',', '.'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                                <h3 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-4">Rincian Service</h3>
                                <div class="space-y-2">
                                    <div class="hidden md:flex text-sm font-semibold text-gray-500 px-4">
                                        <div class="w-1/5 ">Id Detail</div>
                                        <div class="w-1/5 ">Service</div>
                                        <div class="w-1/5 md:w-1/5">Jasa</div>
                                            <div class="w-1/5 text-center">DESKRIPSI</div>
                                        <div class="w-1/5 text-right">SUBTOTAL</div>
                                        <div class="w-1/5 text-center"></div>
                                    </div>
                                    <?php
                                    // SETEL ULANG POINTER DATA DI SINI!
                                    if ($result_detail) {
                                        mysqli_data_seek($result_detail, 0);
                                    }
                                    ?>
                                    <?php if ($result_detail && $result_detail->num_rows > 0): ?>
                                        <?php while ($detail = $result_detail->fetch_assoc()): ?>
                                            <div class="flex flex-wrap md:flex-nowrap items-center border-b py-3 text-sm">
                                                <div class="w-1/5 md:w-1/5  px-4"><?php echo number_format($detail['id_ds']); ?></div>
                                                <div class="w-1/5 md:w-1/5  px-4"><?php echo htmlspecialchars($detail['nama_barang'] ?: 'N/A'); ?></div>
                                                <div class="w-1/5 md:w-1/5  px-4"><?php echo htmlspecialchars($detail['jenis_jasa'] ?: 'N/A'); ?></div>
                                                <div class="w-1/5 md:w-1/5 text-center px-4"><?php echo htmlspecialchars($detail['kerusakan']); ?></div>
                                                <div class="w-1/5 md:w-1/5 text-right font-semibold px-4">Rp <?php echo number_format($detail['total'], 0, ',', '.'); ?></div>
                                                <div class="w-1/5 md:w-1/5 text-center px-4">
                                                    <button type="button"
                                                        onclick="editDetailService(
                                                                <?php echo $detail['id_ds']; ?>, 
                                                                '<?php echo addslashes($detail['nama_barang'] ?: ''); ?>', 
                                                                '<?php echo addslashes($detail['jenis_jasa'] ?: ''); ?>', 
                                                                '<?php echo addslashes($detail['kerusakan']); ?>', 
                                                                <?php echo $detail['id_barang'] ? $detail['id_barang'] : 'null'; ?>, 
                                                                <?php echo $detail['id_jasa'] ? $detail['id_jasa'] : 'null'; ?>
                                                            )"
                                                        class="inline-flex items-center justify-center p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-lg transition-colors duration-200 cursor-pointer">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                        </svg>
                                                    </button>
                                                    <button type="button"
                                                        onclick="hapusDetailService(<?php echo $detail['id_ds']; ?>, <?php echo $detail['id_barang'] ?: 'null'; ?>)"
                                                        class="inline-flex items-center justify-center p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-lg transition-colors duration-200 cursor-pointer" title="Hapus Detail">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-center text-gray-500 py-4">Belum ada detail service yang ditambahkan.</p>
                                    <?php endif; ?>
                                </div>
                            </div>


                            <div class="bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-4">Ringkasan & Pembayaran</h3>

                                <div class="mb-4">
                                    <span class="text-gray-500">Total Biaya Service Keseluruhan:</span>
                                    <p class="font-bold text-gray-900 text-2xl">
                                        Rp <?php echo number_format($total_biaya_service, 0, ',', '.'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 italic">Berdasarkan total dari tabel "Rincian Service" di atas.</p>
                                </div>
                                <hr class="my-4">
                                <?php if (empty($pembayaran_history)): ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                                        <div>
                                            <span class="text-gray-500">Total Sudah Dibayar:</span>
                                            <p class="font-semibold text-green-600">Rp 0</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-gray-500">Sisa Pembayaran:</span>
                                            <p class="font-bold text-red-600 text-lg">
                                                Rp <?php echo number_format($total_biaya_service, 0, ',', '.'); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-center p-4 bg-yellow-50 border border-yellow-200 rounded-lg mt-4">
                                        <p class="font-semibold text-yellow-800">Tagihan belum dibuat untuk service ini.</p>
                                    </div>
                                    <div class="mt-6 border-t pt-6">
                                        <a href="pembayaran_service.php?id_service=<?php echo htmlspecialchars($id_service); ?>" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center text-center">
                                            <span class="text-xl mr-2">💰</span> Lanjutkan ke Pembayaran
                                        </a>
                                    </div>

                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                                        <div class="text-left">
                                            <span class="text-gray-500">Status Pembayaran:</span>
                                            <p class="font-semibold px-3 py-1 rounded-full inline-block text-xs <?php echo $status_badge_class; ?>">
                                                <?php echo htmlspecialchars($status_pembayaran); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-gray-500">Sisa Pembayaran:</span>
                                            <p class="font-bold text-red-600 text-lg">Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></p>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Total Sudah Dibayar:</span>
                                            <p class="font-semibold text-green-600">Rp <?php echo number_format($total_sudah_dibayar, 0, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                    <h4 class="text-md font-semibold text-gray-700 mb-3 border-t pt-4">Detail Riwayat Pembayaran</h4>
                                    <?php if (empty($pembayaran_history)): ?>
                                        <p class="text-gray-500 text-sm text-center py-4">Belum ada riwayat pembayaran yang tercatat.</p>
                                    <?php else: ?>
                                        <div class="space-y-2 text-sm">
                                            <?php foreach ($pembayaran_history as $bayar): ?>
                                                <div class="flex justify-between items-center border-b p-2 hover:bg-gray-50">
                                                    <div>
                                                        <p class="font-semibold">ID Bayar: <?php echo htmlspecialchars($bayar['id_bayar']); ?></p>
                                                        <p class="text-gray-500"><?php echo date('d M Y, H:i', strtotime($bayar['tanggal'])); ?></p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="font-bold text-green-700">Rp <?php echo number_format($bayar['jumlah'], 0, ',', '.'); ?></p>
                                                        <p class="text-gray-500 text-xs">Metode: <?php echo htmlspecialchars($bayar['metode']); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-6 border-t pt-6">
                                        <a href="pembayaran_service.php?id_service=<?php echo htmlspecialchars($id_service); ?>" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center text-center">
                                            <span class="text-xl mr-2">💰</span> Lanjutkan ke Pembayaran
                                        </a>
                                    </div>
                                <?php endif; ?>

                            </div>


                        </div>

                        <div class="w-full lg:w-1/3 px-4">

                            <div class="bg-white p-6 rounded-lg shadow-md mb-6 top-6">
                                <h3 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-6">Edit Service</h3>
                                <?php if ($service['status'] == 'sudah diambil' || $service['status'] == 'dibatalkan'): ?>
                                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">
                                                    <?php if ($service['status'] == 'sudah diambil'): ?>
                                                        Service ini sudah diambil oleh pelanggan. Form edit tidak dapat diakses.
                                                    <?php else: ?>
                                                        Service ini telah dibatalkan. Form edit tidak dapat diakses.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <div>
                                            <label for="status" class="block mb-1 text-sm font-medium text-gray-700">Status Service</label>
                                            <select name="status" id="status" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                                                <option value="dikonfirmasi" <?php echo ($service['status'] == 'dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                                <option value="menunggu sparepart" <?php echo ($service['status'] == 'menunggu sparepart') ? 'selected' : ''; ?>>Menunggu Sparepart</option>
                                                <option value="diperbaiki" <?php echo ($service['status'] == 'diperbaiki') ? 'selected' : ''; ?>>Diperbaiki</option>
                                                <option value="selesai" <?php echo ($service['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="siap diambil" <?php echo ($service['status'] == 'siap diambil') ? 'selected' : ''; ?>>Siap Diambil</option>
                                            </select>
                                        </div>
                                        <?php if ($service['status'] == 'selesai' || $service['status'] == 'siap diambil'): ?>
                                            <div class="pt-4">
                                                <a href="konfirmasi_pengambilan.php?id=<?php echo htmlspecialchars($id_service); ?>"
                                                    onclick="return confirm('Apakah Anda yakin ingin mengkonfirmasi bahwa service ini sudah diambil oleh pelanggan?');"
                                                    class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    Konfirmasi Pengambilan Service
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <label for="id_barang" class="block mb-1 text-sm font-medium text-gray-700">Barang (Sparepart):</label>
                                            <select id="id_barang" name="id_barang" class="select2-barang block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">-- Cari Barang --</option>
                                                <?php mysqli_data_seek($barangs_result, 0); ?>
                                                <?php while ($b = mysqli_fetch_assoc($barangs_result)): ?>
                                                    <option value="<?php echo $b['id_barang']; ?>" data-harga="<?php echo $b['harga']; ?>"><?php echo htmlspecialchars($b['nama_barang']); ?> - Rp <?php echo number_format($b['harga'], 0, ',', '.'); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="id_jasa" class="block mb-1 text-sm font-medium text-gray-700">Jasa:</label>
                                            <select id="id_jasa" name="id_jasa" class="select2-jasa block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">-- Cari Jasa --</option>
                                                <?php mysqli_data_seek($jasas_result, 0); ?>
                                                <?php while ($j = mysqli_fetch_assoc($jasas_result)): ?>
                                                    <option value="<?php echo $j['id_jasa']; ?>" data-harga="<?php echo $j['harga']; ?>"><?php echo htmlspecialchars($j['jenis_jasa']); ?> - Rp <?php echo number_format($j['harga'], 0, ',', '.'); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="kerusakan_detail" class="block mb-1 text-sm font-medium text-gray-700">Deskripsi/Tindakan:</label>
                                            <textarea id="kerusakan_detail" name="kerusakan_detail" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500" rows="2"></textarea>
                                        </div>
                                        <input type="hidden" id="edit_id_ds" name="edit_id_ds" value="">
                                        <div>
                                            <label for="total_detail_display" class="block mb-1 text-sm font-medium text-gray-700">Harga Detail Baru:</label>
                                            <input type="text" id="total_detail_display" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-100" placeholder="Rp 0" readonly>
                                        </div>

                                        <div class="pt-4">
                                            <button type="submit" id="submitButton" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                <span id="submitButtonText">Simpan Perubahan & Tambah Detail</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="bg-red-50 border-2 border-dashed border-red-300 p-6 rounded-lg shadow-sm">
                                <h4 class="text-lg font-bold text-red-800">Aksi Berbahaya</h4>
                                <p class="text-sm text-red-700 mt-1 mb-4">Aksi ini akan membatalkan service ini. Service yang dibatalkan tidak dapat diedit kembali.</p>
                                <?php if ($service['status'] != 'sudah diambil'): ?>
                                    <a href="batalkan_service.php?id=<?php echo htmlspecialchars($id_service); ?>"
                                        onclick="return confirm('Anda yakin ingin membatalkan service ini? Service yang dibatalkan tidak dapat diedit kembali.');"
                                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        Batalkan Service Ini
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Select2 untuk barang
            $('.select2-barang').select2({
                placeholder: "Cari barang...",
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Barang tidak ditemukan";
                    }
                }
            });

            // Inisialisasi Select2 untuk jasa
            $('.select2-jasa').select2({
                placeholder: "Cari jasa...",
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Jasa tidak ditemukan";
                    }
                }
            });

            // --- Script untuk kalkulasi total biaya detail ---
            const barangSelect = document.getElementById('id_barang');
            const jasaSelect = document.getElementById('id_jasa');
            const totalDetailDisplayInput = document.getElementById('total_detail_display');

            function updateTotalDisplay() {
                let total = 0;
                const selectedBarang = barangSelect.options[barangSelect.selectedIndex];
                const selectedJasa = jasaSelect.options[jasaSelect.selectedIndex];

                if (selectedBarang && selectedBarang.dataset.harga) {
                    total += parseFloat(selectedBarang.dataset.harga);
                }
                if (selectedJasa && selectedJasa.dataset.harga) {
                    total += parseFloat(selectedJasa.dataset.harga);
                }

                totalDetailDisplayInput.value = total > 0 ? total.toLocaleString('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                }) : '';
            }

            // Update total when Select2 selection changes
            $('.select2-barang, .select2-jasa').on('change', updateTotalDisplay);
            updateTotalDisplay();

            // --- Script untuk tanggal selesai dinamis ---
            const statusSelect = document.getElementById('status');
            // Fungsi untuk mengedit detail service
            window.editDetailService = function(id_ds, nama_barang, jenis_jasa, kerusakan, id_barang, id_jasa) {
                console.log('Editing detail:', {
                    id_ds,
                    nama_barang,
                    jenis_jasa,
                    kerusakan,
                    id_barang,
                    id_jasa
                }); // Debug log

                // Set nilai form
                document.getElementById('edit_id_ds').value = id_ds;
                document.getElementById('kerusakan_detail').value = kerusakan;

                // Set nilai select2
                if (id_barang && id_barang !== 'null') {
                    $('#id_barang').val(id_barang).trigger('change');
                } else {
                    $('#id_barang').val('').trigger('change');
                }

                if (id_jasa && id_jasa !== 'null') {
                    $('#id_jasa').val(id_jasa).trigger('change');
                } else {
                    $('#id_jasa').val('').trigger('change');
                }

                // Update tampilan tombol submit
                document.getElementById('submitButtonText').textContent = 'Simpan Perubahan Detail';
                document.getElementById('submitButton').classList.remove('bg-green-500', 'hover:bg-green-600');
                document.getElementById('submitButton').classList.add('bg-blue-500', 'hover:bg-blue-600');

                // Scroll ke form
                document.querySelector('.bg-white.p-6.rounded-lg.shadow-md.mb-6.top-6').scrollIntoView({
                    behavior: 'smooth'
                });
            }
            window.hapusDetailService = function(id_ds, id_barang) {
                // 1. Minta konfirmasi dari pengguna
                const pesanKonfirmasi = 'Anda yakin ingin menghapus detail ini? Jika detail ini menggunakan sparepart, stok akan dikembalikan.';
                if (confirm(pesanKonfirmasi)) {

                    // 2. Siapkan data untuk dikirim ke server
                    const formData = new FormData();
                    formData.append('id_ds', id_ds);
                    if (id_barang) {
                        formData.append('id_barang', id_barang);
                    }

                    // 3. Kirim permintaan ke server menggunakan Fetch API
                    fetch('hapus_detail_service.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                window.location.reload(); // Muat ulang halaman untuk melihat perubahan
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Terjadi kesalahan:', error);
                            alert('Tidak dapat terhubung ke server.');
                        });
                }
            }

            // Reset form saat halaman dimuat
            function resetForm() {
                document.getElementById('edit_id_ds').value = '';
                document.getElementById('kerusakan_detail').value = '';
                $('#id_barang').val('').trigger('change');
                $('#id_jasa').val('').trigger('change');
                document.getElementById('submitButtonText').textContent = 'Simpan Perubahan & Tambah Detail';
                document.getElementById('submitButton').classList.remove('bg-blue-500', 'hover:bg-blue-600');
                document.getElementById('submitButton').classList.add('bg-green-500', 'hover:bg-green-600');
            }

            // Tambahkan event listener untuk reset form
            document.querySelector('form').addEventListener('submit', function() {
                setTimeout(resetForm, 1000); // Reset form setelah submit
            });
        });
    </script>

</body>

</html>
<?php
// Free results
if (isset($result_service) && $result_service instanceof mysqli_result) mysqli_free_result($result_service);
if (isset($result_detail) && $result_detail instanceof mysqli_result) mysqli_free_result($result_detail);
if (isset($barangs_result) && $barangs_result instanceof mysqli_result) mysqli_free_result($barangs_result);
if (isset($jasas_result) && $jasas_result instanceof mysqli_result) mysqli_free_result($jasas_result);

// Close connection
mysqli_close($koneksi);
?>