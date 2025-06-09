<?php
session_start();
// Pastikan file koneksi.php ada dan berisi objek $koneksi
include 'koneksi.php'; 

// Pastikan koneksi ke database berhasil
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$cart = $_SESSION['cart'] ?? []; // Ambil keranjang dari sesi

$error_checkout = null; // Variabel untuk error saat proses checkout
$id_transaksi_baru = null;

// Nomor WhatsApp tujuan untuk notifikasi
$whatsappNumber = '6281234567890'; // Ganti dengan nomor WhatsApp Anda

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cart)) {
    // Ambil data input dari formulir
    $nama = trim($_POST['nama_pembeli'] ?? ''); // Sesuaikan dengan 'name' di form
    $nohp = trim($_POST['no_hp'] ?? '');         // Sesuaikan dengan 'name' di form
    $email = trim($_POST['email'] ?? '');
    $alamat_pengiriman = trim($_POST['alamat_pengiriman'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'transfer'); // Default ke 'transfer' jika tidak ada

    // Validasi input dasar di sisi server
    if (empty($cart)) {
        $error_checkout = "Keranjang belanja Anda kosong. Silakan tambahkan barang terlebih dahulu.";
    } elseif (empty($nama) || empty($nohp) || empty($email) || empty($alamat_pengiriman)) {
        $error_checkout = "Semua kolom informasi pengiriman dan kontak wajib diisi.";
    } elseif (!preg_match("/^\d{10,12}$/", $nohp)) { 
        $error_checkout = "Nomor handphone harus 10-12 digit angka.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { 
        $error_checkout = "Alamat email tidak valid.";
    } else {
        // Mulai transaksi database untuk memastikan semua operasi berhasil atau tidak sama sekali
        $koneksi->begin_transaction();

        try {
            $total_belanja = 0;
            $items_to_process = []; // Untuk menyimpan detail item yang akan diinsert ke detail_transaksi

            // 1. Hitung total belanja & siapkan detail item (gunakan prepared statement untuk ambil harga terbaru)
            if (!empty($cart)) {
                $placeholders = implode(',', array_fill(0, count($cart), '?'));
                $types = str_repeat('i', count($cart)); // id_barang diasumsikan INT
                $item_ids = array_keys($cart);

                // Tambahkan FOR UPDATE untuk locking baris yang akan dibeli
                $sql_harga = "SELECT id_barang, nama_barang, harga, stok FROM stok WHERE id_barang IN ($placeholders) FOR UPDATE"; 
                $stmt_harga = $koneksi->prepare($sql_harga);
                if (!$stmt_harga) throw new Exception("Gagal menyiapkan statement harga: " . $koneksi->error);

                $stmt_harga->bind_param($types, ...$item_ids);
                $stmt_harga->execute();
                $result_harga = $stmt_harga->get_result();

                // Pastikan semua item di keranjang ada di database dan stok cukup
                if ($result_harga->num_rows !== count($cart)) {
                    throw new Exception("Beberapa item di keranjang tidak valid atau tidak ditemukan. Mohon refresh keranjang Anda.");
                }
                
                $temp_cart_details = []; // Digunakan untuk menyimpan detail item yang akurat dari DB
                while ($barang_db = $result_harga->fetch_assoc()) {
                    $id_b = $barang_db['id_barang'];
                    $qty_pesan = $cart[$id_b]; // Kuantitas yang dipesan dari keranjang sesi

                    if ($qty_pesan > $barang_db['stok']) {
                        throw new Exception("Stok untuk barang \"" . htmlspecialchars($barang_db['nama_barang']) . "\" tidak mencukupi (tersisa: " . $barang_db['stok'] . ", dipesan: " . $qty_pesan . ").");
                    }

                    $subtotal_item = $qty_pesan * $barang_db['harga'];
                    $total_belanja += $subtotal_item;
                    $items_to_process[] = [
                        'id_barang' => $id_b,
                        'jumlah' => $qty_pesan,
                        'harga_satuan' => $barang_db['harga'], // Harga satuan yang diambil dari DB
                        'subtotal' => $subtotal_item
                    ];
                    // Simpan detail ini juga untuk pesan WA
                    $temp_cart_details[] = [
                        'nama_barang' => $barang_db['nama_barang'],
                        'jumlah' => $qty_pesan,
                        'subtotal' => $subtotal_item
                    ];
                }
                $stmt_harga->close();
                if (empty($items_to_process)) throw new Exception("Keranjang belanja kosong atau item tidak valid setelah verifikasi.");
            } else {
                throw new Exception("Keranjang belanja kosong.");
            }

            // 2. Cek/Buat Customer (gunakan prepared statement)
            $sql_cek_customer = "SELECT id_customer FROM customer WHERE nama_customer = ? AND no_telepon = ? AND email = ?";
            $stmt_cek = $koneksi->prepare($sql_cek_customer);
            if (!$stmt_cek) throw new Exception("Gagal menyiapkan statement cek customer: " . $koneksi->error);

            $stmt_cek->bind_param("sss", $nama, $nohp, $email);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            $id_customer = null;

            if ($result_cek->num_rows > 0) {
                $row_cust = $result_cek->fetch_assoc();
                $id_customer = $row_cust['id_customer'];
            } else {
                $sql_insert_customer = "INSERT INTO customer (nama_customer, no_telepon, email, alamat) VALUES (?, ?, ?, ?)";
                $stmt_insert_cust = $koneksi->prepare($sql_insert_customer);
                if (!$stmt_insert_cust) throw new Exception("Gagal menyiapkan statement insert customer: " . $koneksi->error);

                $stmt_insert_cust->bind_param("ssss", $nama, $nohp, $email, $alamat_pengiriman);
                if (!$stmt_insert_cust->execute()) throw new Exception("Gagal menambahkan customer baru: " . $stmt_insert_cust->error);
                $id_customer = $koneksi->insert_id; 
                $stmt_insert_cust->close();
            }
            $stmt_cek->close();
            if (!$id_customer) throw new Exception("Gagal mendapatkan ID Customer untuk transaksi.");

            // 3. Buat Transaksi Utama (gunakan prepared statement)
            $tanggal_transaksi = date('Y-m-d H:i:s');
            $status_awal = 'Menunggu Pembayaran'; 
            $jenis_transaksi = 'penjualan'; 

            $sql_insert_transaksi = "INSERT INTO transaksi (id_customer, jenis, status, tanggal, total, id_service, metode_pembayaran) 
                                     VALUES (?, ?, ?, ?, ?, NULL, ?)"; 
            $stmt_trans = $koneksi->prepare($sql_insert_transaksi);
            if (!$stmt_trans) throw new Exception("Gagal menyiapkan statement insert transaksi: " . $koneksi->error);

            $stmt_trans->bind_param("isssds", $id_customer, $jenis_transaksi, $status_awal, $tanggal_transaksi, $total_belanja, $payment_method);
            if (!$stmt_trans->execute()) throw new Exception("Gagal memasukkan transaksi baru: " . $stmt_trans->error);
            $id_transaksi_baru = $koneksi->insert_id; 
            $stmt_trans->close();

            // 4. Buat Detail Transaksi & Kurangi Stok (gunakan prepared statement)
            $sql_insert_detail = "INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, subtotal) VALUES (?, ?, ?, ?)";
            $stmt_detail = $koneksi->prepare($sql_insert_detail);
            if (!$stmt_detail) throw new Exception("Gagal menyiapkan statement insert detail transaksi: " . $koneksi->error);

            $sql_update_stok = "UPDATE stok SET stok = stok - ? WHERE id_barang = ?";
            $stmt_stok = $koneksi->prepare($sql_update_stok);
            if (!$stmt_stok) throw new Exception("Gagal menyiapkan statement update stok: " . $koneksi->error);

            foreach ($items_to_process as $item) {
                $stmt_detail->bind_param("iiid", $id_transaksi_baru, $item['id_barang'], $item['jumlah'], $item['subtotal']);
                if (!$stmt_detail->execute()) throw new Exception("Gagal memasukkan detail transaksi untuk barang ID " . $item['id_barang'] . ": " . $stmt_detail->error);

                $stmt_stok->bind_param("ii", $item['jumlah'], $item['id_barang']);
                if (!$stmt_stok->execute()) throw new Exception("Gagal memperbarui stok untuk barang ID " . $item['id_barang'] . ": " . $stmt_stok->error);
            }
            $stmt_detail->close();
            $stmt_stok->close();

            // Jika semua operasi berhasil, commit transaksi ke database
            $koneksi->commit();
            $_SESSION['cart'] = []; // Kosongkan keranjang belanja setelah sukses

            // --- BAGIAN INI YANG DIUBAH UNTUK NOTIFIKASI WHATSAPP ---
            // Bangun pesan WhatsApp
            $pesan_wa = "Halo Thar'z Computer,\n\n";
            $pesan_wa .= "Saya telah melakukan pemesanan dengan detail sebagai berikut:\n\n";
            $pesan_wa .= "*ID Transaksi:* " . $id_transaksi_baru . "\n";
            $pesan_wa .= "*Nama Pembeli:* " . $nama . "\n";
            $pesan_wa .= "*No. HP:* " . $nohp . "\n";
            $pesan_wa .= "*Email:* " . $email . "\n";
            $pesan_wa .= "*Alamat Pengiriman:* " . $alamat_pengiriman . "\n\n";
            $pesan_wa .= "*Detail Pesanan:*\n";
            foreach ($temp_cart_details as $item) {
                $pesan_wa .= "- " . $item['nama_barang'] . " (Jumlah: " . $item['jumlah'] . ") - Rp " . number_format($item['subtotal'], 0, ',', '.') . "\n";
            }
            $pesan_wa .= "\n*Total Pembelian:* Rp " . number_format($total_belanja, 0, ',', '.') . "\n";
            $pesan_wa .= "*Metode Pembayaran:* " . ($payment_method === 'transfer' ? 'Transfer Bank' : 'Pembayaran di Toko') . "\n\n";
            $pesan_wa .= "Mohon konfirmasi pesanan saya. Terima kasih!";

            // Encode pesan untuk URL
            $encoded_pesan_wa = urlencode($pesan_wa);
            $whatsapp_url = "https://wa.me/{$whatsappNumber}?text={$encoded_pesan_wa}";

            // Redirect ke WhatsApp
            header("Location: " . $whatsapp_url);
            exit; 
            // --- AKHIR BAGIAN PERUBAHAN ---

        } catch (Exception $e) {
            // Jika ada kesalahan, batalkan semua perubahan database
            $koneksi->rollback();
            $error_checkout = "Terjadi kesalahan saat memproses pesanan Anda: " . $e->getMessage();
        } finally {
            // Pastikan koneksi ditutup jika tidak lagi dibutuhkan (opsional)
            // $koneksi->close(); 
        }
    }
}

// Data untuk tampilan keranjang (jika keranjang sesi kosong atau belum ada POST)
$namaAkun = "Customer"; 

$display_cart_items = [];
$current_total_harga_keranjang = 0;

if (!empty($cart)) {
    $item_ids_for_display = array_keys($cart);

    $placeholders = implode(',', array_fill(0, count($item_ids_for_display), '?'));
    $types = str_repeat('i', count($item_ids_for_display));

    $sql_display_items = "SELECT id_barang, nama_barang, harga, gambar FROM stok WHERE id_barang IN ($placeholders)";
    $stmt_display = $koneksi->prepare($sql_display_items);
    if ($stmt_display) {
        $stmt_display->bind_param($types, ...$item_ids_for_display);
        $stmt_display->execute();
        $result_display = $stmt_display->get_result();
        
        while($row = $result_display->fetch_assoc()) {
            $row['jumlah'] = $cart[$row['id_barang']]; // Tambahkan jumlah dari sesi cart
            $row['subtotal'] = $row['harga'] * $row['jumlah'];
            $display_cart_items[] = $row;
            $current_total_harga_keranjang += $row['subtotal'];
        }
        $stmt_display->close();
    } else {
        // Handle error if display query fails
        error_log("Error fetching display items: " . $koneksi->error);
        // Optionally, set a user-friendly message
        // $error_checkout = "Terjadi kesalahan saat menampilkan detail keranjang.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* (Sertakan CSS dasar dari halaman service.php atau transaksi_service.php Anda di sini) */
        body {
            display: flex;
            flex-direction: column;
            font-family: sans-serif;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #ffffff;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .navbar .logo-img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        .navbar .company-name-header {
            font-weight: bold;
            font-size: 1.25rem;
            color: #343a40;
        }
        .navbar .nav-link {
            padding: 10px 15px;
            color: #495057;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .navbar .nav-link.active,
        .navbar .nav-link:hover {
            background-color: #e9ecef;
            color: #495057; /* Kembali ke warna default jika tidak ada aksen biru */
        }
        .navbar .nav-link i {
            margin-right: 8px;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .main-header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .card {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .btn-checkout { /* Tombol utama checkout */
            background-color: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.2s ease;
        }
        .btn-checkout:hover {
            background-color: #218838;
        }
        /* Tambahan untuk Detail Pembeli */
        .form-control:focus {
            border-color: #ced4da; /* Default Bootstrap focus color */
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.075); /* Default shadow */
        }
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .form-control.is-valid {
            border-color: #28a745;
        }
        /* Detail Keranjang */
        .table {
            --bs-table-bg: #fff; /* Pastikan background tabel putih */
            --bs-table-color: #343a40;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .total-keranjang {
            background-color: #e6ffe6; /* Light green background */
            font-weight: bold;
            font-size: 1.25rem;
            color: #155724; /* Dark green text */
            padding: 10px 15px;
            border-radius: 0.5rem;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-keranjang span {
            font-size: 1.5rem; /* Ukuran angka total */
        }
        /* Metode Pembayaran */
        .payment-options {
            margin-top: 30px;
        }
        .payment-option-card {
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            background-color: #fff;
            margin-bottom: 15px;
        }
        .payment-option-card:hover {
            border-color: #28a745; /* Border hijau saat hover */
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
        }
        .payment-option-card.active {
            border-color: #28a745; /* Border hijau saat aktif */
            background-color: #e6ffe6; /* Background hijau muda saat aktif */
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
        }
        .payment-option-card h6 {
            margin: 0;
            color: #343a40;
        }
        .payment-option-card i {
            font-size: 1.5rem;
            margin-right: 15px;
            color: #6c757d; /* Ikon abu-abu */
        }
        .payment-option-card.active i {
            color: #28a745; /* Ikon hijau saat aktif */
        }
        .payment-details-area {
            margin-top: 20px;
            padding: 20px;
            background-color: #e9ecef; /* Abu-abu muda */
            border-radius: 0.75rem;
            border: 1px dashed #dee2e6;
        }
        .bank-logo {
            height: 25px; /* Ukuran logo bank */
            margin-right: 10px;
            object-fit: contain;
            max-width: 80px; /* Batasi lebar logo agar tidak terlalu besar */
        }
        .copy-btn {
            padding: 5px 10px;
            font-size: 0.8rem;
            background-color: #f0f0f0;
            border: 1px solid #dee2e6;
            color: #495057;
            margin-left: 10px;
        }
        .copy-btn:hover {
            background-color: #e2e6ea;
        }
        /* Footer styles */
        footer {
            background-color: #ffffff;
            border-top: 1px solid #dee2e6;
            padding: 30px 20px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        footer .footer-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px 50px;
            margin-bottom: 25px;
        }
        footer .footer-info div {
            flex-basis: auto;
            min-width: 200px;
            max-width: 300px;
            text-align: left;
        }
        footer .footer-info div strong {
            display: block;
            margin-bottom: 10px;
            color: #343a40;
            font-size: 1.1rem;
            font-weight: 600;
        }
        footer .footer-info div p {
            margin: 0;
            line-height: 1.6;
            color: #6c757d;
        }
        footer .footer-info div p a {
            color: #28a745 !important; /* Warna hijau untuk link WhatsApp */
            text-decoration: none;
            font-weight: 500;
        }
        footer .footer-info div p a:hover {
            text-decoration: underline;
        }
        footer .footer-info div p i {
            margin-right: 8px;
        }
        footer .social-icons {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        footer .social-icons a {
            color: #6c757d;
            font-size: 1.5rem;
            transition: color 0.2s ease-in-out;
        }
        footer .social-icons a:hover {
            color: #28a745;
        }
        footer .copyright-text {
            color: #6c757d;
            font-size: 0.85rem;
        }
        footer .copyright-text a {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        footer .copyright-text a:hover {
            color: #28a745;
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar .navbar-nav { flex-direction: column; align-items: flex-start; }
            .navbar .navbar-toggler { display: block; }
            .navbar .navbar-collapse { display: none; }
            .navbar .navbar-collapse.show { display: flex; flex-direction: column; }
            .main-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .main-header h2 { width: 100%; text-align: center; }
            .total-keranjang { flex-direction: column; align-items: flex-start; }
            .total-keranjang span { margin-top: 10px; }
            footer .footer-info { flex-direction: column; align-items: center; gap: 20px; }
            footer .footer-info div { text-align: center; min-width: unset; max-width: 80%; }
            footer .social-icons { margin-bottom: 15px; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="icons/logo.png" alt="logo Thar'z Computer" class="logo-img">
                <span class="company-name-header">THAR'Z COMPUTER</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i>Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="barang.php">
                            <i class="fas fa-box-open"></i>Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="service.php">
                            <i class="fas fa-desktop"></i>Pengajuan Service
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tracking.php">
                            <i class="fas fa-search-location"></i>Tracking Service
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="transaksi_barang.php">
                            <i class="fas fa-cash-register"></i>Checkout
                        </a>
                    </li>
                </ul>
            </div>

            <div class="d-flex align-items-center">
                <span class="text-dark fw-semibold">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($namaAkun); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="main-header">
            <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Selesaikan Pembelian Anda</h2>
        </div>

        <div class="container py-3">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <?php if ($error_checkout): // Tampilkan pesan error jika ada ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error_checkout); ?>
                        </div>
                    <?php endif; ?>

                    <form action="transaksi_barang.php" method="POST" id="checkoutForm" novalidate>
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4 pb-2 border-bottom">Informasi Pengiriman & Kontak</h5>
                                <div class="mb-3">
                                    <label for="nama_pembeli" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_pembeli" name="nama_pembeli" placeholder="Masukkan nama lengkap Anda" required>
                                    <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="no_hp" class="form-label">Nomor Handphone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="no_hp" name="no_hp" pattern="\d{10,12}" maxlength="12" placeholder="Contoh: 081234567890" required>
                                    <div class="form-text text-muted">* Nomor harus 10-12 digit angka.</div>
                                    <div class="invalid-feedback">Nomor handphone harus 10-12 digit angka.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Contoh: nama@email.com" required>
                                    <div class="invalid-feedback">Alamat email tidak valid.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="alamat_pengiriman" class="form-label">Alamat Pengiriman <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="alamat_pengiriman" name="alamat_pengiriman" rows="3" placeholder="Masukkan alamat lengkap pengiriman" required></textarea>
                                    <div class="invalid-feedback">Alamat pengiriman wajib diisi.</div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4 pb-2 border-bottom">Detail Pesanan Anda</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th scope="col">Nama Barang</th>
                                                <th scope="col" class="text-end">Harga</th>
                                                <th scope="col" class="text-end">Jumlah</th>
                                                <th scope="col" class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($display_cart_items)): ?>
                                                <?php foreach ($display_cart_items as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if (isset($item['gambar']) && !empty($item['gambar'])): ?>
                                                                <img src="<?php echo htmlspecialchars($item['gambar']); ?>" alt="<?php echo htmlspecialchars($item['nama_barang']); ?>" style="width:50px; height:50px; object-fit:cover; margin-right:10px;" class="rounded">
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($item['nama_barang']); ?>
                                                        </td>
                                                        <td class="text-end">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                                        <td class="text-end"><?php echo htmlspecialchars($item['jumlah']); ?></td>
                                                        <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">Keranjang Anda kosong.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="total-keranjang">
                                    <span>Total Pembelian:</span>
                                    <span>Rp <?php echo number_format($current_total_harga_keranjang, 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4 payment-options">
                            <div class="card-body">
                                <h5 class="card-title mb-4 pb-2 border-bottom">Pilih Metode Pembayaran</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="payment-option-card active" data-method="transfer">
                                            <i class="fas fa-money-check-alt"></i><h6>Transfer Bank</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="payment-option-card" data-method="toko">
                                            <i class="fas fa-store"></i><h6>Pembayaran di Toko</h6>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="transfer">

                                <div id="payment-details-area" class="payment-details-area mt-4">
                                    <div id="details-transfer">
                                        <h6>Detail Transfer Bank:</h6>
                                        <p class="text-muted">Lakukan transfer ke salah satu rekening bank berikut ini:</p>
                                        <ul class="list-unstyled bank-list">
                                            <li>
                                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Bank_Central_Aca.svg/1280px-Bank_Central_Aca.svg.png" alt="BCA Logo" class="bank-logo">
                                                <strong>BCA</strong>
                                                <p>No. Rekening: <span id="rekBCA">1234567890</span> <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="rekBCA"><i class="far fa-copy"></i> Salin</button></p>
                                                <p>Atas Nama: PT. Thar'z Komputerindo</p>
                                            </li>
                                            <li class="mt-3">
                                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Bank_Mandiri_logo.svg/2560px-Bank_Mandiri_logo.svg.png" alt="Mandiri Logo" class="bank-logo">
                                                <strong>Mandiri</strong>
                                                <p>No. Rekening: <span id="rekMandiri">0987654321</span> <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="rekMandiri"><i class="far fa-copy"></i> Salin</button></p>
                                                <p>Atas Nama: PT. Thar'z Komputerindo</p>
                                            </li>
                                        </ul>
                                        <p class="text-danger fw-bold">Penting: Cantumkan ID Transaksi Anda pada berita transfer!</p>
                                    </div>
                                    <div id="details-toko" style="display:none;">
                                        <h6>Pembayaran di Toko:</h6>
                                        <p class="text-muted">Anda dapat melakukan pembayaran langsung di toko kami saat pengambilan barang.</p>
                                        <p>Alamat: Jl. Raya Contoh No. 123, Kota Tasikmalaya</p>
                                        <p>Jam Operasional: Senin-Sabtu, 09:00 - 17:00 WIB</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-checkout">
                                <i class="fas fa-check-circle"></i> Selesaikan Pembelian
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-auto">
        <div class="container">
            <div class="footer-info">
                <div>
                    <strong>Tentang Kami</strong>
                    <p>Thar'z Computer menyediakan produk komputer dan jasa service terpercaya di Tasikmalaya. Kami berkomitmen untuk memberikan kualitas terbaik.</p>
                </div>
                <div>
                    <strong>Kontak Kami</strong>
                    <p><i class="fas fa-map-marker-alt"></i> Jl. Raya Contoh No. 123, Kota Tasikmalaya</p>
                    <p><i class="fas fa-phone"></i> (0265) 123456</p>
                    <p><i class="fas fa-envelope"></i> info@tharzcomputer.com</p>
                    <p><i class="fab fa-whatsapp"></i> <a href="https://wa.me/6281234567890" target="_blank">0812-3456-7890</a></p>
                </div>
                <div>
                    <strong>Jam Operasional</strong>
                    <p>Senin - Sabtu: 09:00 - 17:00 WIB</p>
                    <p>Minggu: Tutup</p>
                </div>
            </div>
            <div class="social-icons">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            </div>
            <p class="copyright-text">
                &copy; <?php echo date("Y"); ?> Thar'z Computer. All rights reserved. | <a href="#">Kebijakan Privasi</a> | <a href="#">Syarat & Ketentuan</a>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form Validation (Validasi di sisi klien)
            const checkoutForm = document.getElementById('checkoutForm');
            checkoutForm.addEventListener('submit', function(event) {
                if (!checkoutForm.checkValidity()) {
                    event.preventDefault(); // Mencegah submit jika form tidak valid
                    event.stopPropagation(); // Menghentikan event bubbling
                }
                checkoutForm.classList.add('was-validated'); // Menambahkan kelas untuk menampilkan feedback validasi
            }, false);

            // Pemilihan Metode Pembayaran (tidak berubah)
            const paymentOptionCards = document.querySelectorAll('.payment-option-card');
            const paymentDetailsArea = document.getElementById('payment-details-area');
            const selectedPaymentMethodInput = document.getElementById('selectedPaymentMethod');

            paymentOptionCards.forEach(card => {
                card.addEventListener('click', function() {
                    paymentOptionCards.forEach(c => c.classList.remove('active'));
                    this.classList.add('active');

                    const selectedMethod = this.dataset.method;
                    selectedPaymentMethodInput.value = selectedMethod; 

                    paymentDetailsArea.querySelectorAll('div[id^="details-"]').forEach(detailDiv => {
                        detailDiv.style.display = 'none';
                    });

                    const targetDetailDiv = document.getElementById('details-' + selectedMethod);
                    if (targetDetailDiv) {
                        targetDetailDiv.style.display = 'block';
                    }
                });
            });

            // Fungsi Salin ke Clipboard (tidak berubah)
            document.querySelectorAll('.copy-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        const textToCopy = targetElement.textContent;
                        navigator.clipboard.writeText(textToCopy)
                            .then(() => {
                                const originalText = this.innerHTML;
                                this.innerHTML = '<i class="fas fa-check"></i> Disalin!';
                                setTimeout(() => {
                                    this.innerHTML = originalText;
                                }, 2000); 
                            })
                            .catch(err => {
                                console.error('Gagal menyalin teks: ', err);
                                alert('Gagal menyalin teks. Silakan salin manual.');
                            });
                    }
                });
            });
        });
    </script>
</body>
</html>