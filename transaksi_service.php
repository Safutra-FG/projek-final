<?php
// Koneksi ke database
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");
if ($koneksi->connect_error) {
    error_log("Koneksi database gagal: " . $koneksi->connect_error);
    die("Terjadi masalah koneksi. Silakan coba beberapa saat lagi atau hubungi administrator.");
}

$id_service_input = null;
$amount_to_pay = null;
$service_info = null; // Ganti $service_data menjadi $service_info untuk konsistensi
$service_details_list = []; // Untuk menyimpan daftar item dari detail_service
$total_biaya_aktual_dari_detail = 0; // Ini akan dihitung ulang dari detail_service atau diambil dari $amount_to_pay
$error_message = null;

// 1. Ambil dan validasi parameter dari URL
if (isset($_GET['id_service']) && isset($_GET['amount'])) {
    $id_service_input = trim($_GET['id_service']);
    $amount_to_pay = filter_var($_GET['amount'], FILTER_VALIDATE_FLOAT); // Ini adalah jumlah yang di-pass dari tracking

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong atau tidak valid.";
    } elseif ($amount_to_pay === false || $amount_to_pay < 0) {
        $error_message = "Jumlah tagihan tidak valid.";
    } else {
        // 2. Ambil data service, customer, dan detail_service
        $sql = "SELECT
                    s.id_service, s.tanggal, s.device, s.keluhan, s.status,
                    s.estimasi_waktu, s.estimasi_harga, s.tanggal_selesai,
                    c.nama_customer,
                    ds.id_ds,
                    ds.kerusakan AS detail_kerusakan_deskripsi,
                    ds.total AS detail_total,
                    b.nama_barang,
                    j.jenis_jasa
                FROM
                    service s
                JOIN
                    customer c ON s.id_customer = c.id_customer
                LEFT JOIN
                    detail_service ds ON s.id_service = ds.id_service
                LEFT JOIN
                    stok b ON ds.id_barang = b.id_barang
                LEFT JOIN
                    jasa j ON ds.id_jasa = j.id_jasa
                WHERE
                    s.id_service = ?
                ORDER BY
                    ds.id_ds ASC";
        $stmt = $koneksi->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $id_service_input); // 'i' for integer, as id_service is typically INT
            $stmt->execute();
            $hasil = $stmt->get_result();

            if ($hasil->num_rows > 0) {
                $first_row_processed = false;
                while ($row = $hasil->fetch_assoc()) {
                    if (!$first_row_processed) {
                        // Simpan informasi service utama hanya sekali
                        $service_info = [
                            'id_service' => $row['id_service'],
                            'tanggal' => $row['tanggal'],
                            'nama_customer' => $row['nama_customer'],
                            'device' => $row['device'],
                            'keluhan' => $row['keluhan'],
                            'status' => $row['status'],
                            'estimasi_waktu' => $row['estimasi_waktu'],
                            'estimasi_harga' => $row['estimasi_harga'],
                            'tanggal_selesai' => $row['tanggal_selesai']
                        ];
                        $first_row_processed = true;
                    }
                    if ($row['id_ds'] !== null) { // Tambahkan detail jika ada
                        $current_detail_total = $row['detail_total'] ?? 0;
                        $service_details_list[] = [
                            'nama_barang' => $row['nama_barang'],
                            'jenis_jasa' => $row['jenis_jasa'],
                            'detail_kerusakan_deskripsi' => $row['detail_kerusakan_deskripsi'],
                            'detail_total' => $current_detail_total
                        ];
                        $total_biaya_aktual_dari_detail += $current_detail_total;
                    }
                }
                // Jika $total_biaya_aktual_dari_detail 0 tapi $amount_to_pay ada, gunakan $amount_to_pay
                // Ini untuk kasus service yang tidak ada detail, tapi ada estimasi harga yang dibayar
                if ($total_biaya_aktual_dari_detail == 0 && $amount_to_pay > 0) {
                    $total_biaya_aktual_dari_detail = $amount_to_pay;
                }

            } else {
                $error_message = "Data service dengan ID '" . htmlspecialchars($id_service_input) . "' tidak ditemukan.";
            }
            $stmt->close();
        } else {
            error_log("Gagal menyiapkan query: " . $koneksi->error);
            $error_message = "Terjadi kesalahan dalam mengambil data service.";
        }
    }
} else {
    $error_message = "Informasi ID Service atau jumlah tagihan tidak lengkap. Silakan kembali ke halaman Tracking Service.";
}

$koneksi->close();

$namaAkun = "Customer";
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instruksi Pembayaran - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
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
            /* Jika logo bukan bulat, hapus border-radius dan border */
            /* border-radius: 50%; */
            /* border: 2px solid #0d6efd; */
            margin-right: 10px;
        }
        .navbar .company-name-header {
            font-weight: bold;
            font-size: 1.25rem;
            color: #343a40; /* Nama perusahaan hitam/abu-abu gelap */
        }
        .navbar .nav-link {
            padding: 10px 15px;
            color: #495057; /* Warna teks link default abu-abu */
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .navbar .nav-link.active,
        .navbar .nav-link:hover {
            background-color: #e9ecef; /* Background hover abu-abu muda */
            color: #495057; /* Warna teks hover tetap abu-abu atau sedikit lebih gelap */
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
        .total-tagihan-display {
            font-size: 1.25em;
            font-weight: bold;
            color: #28a745; /* Hijau untuk total tagihan */
        }
        .label-summary {
            min-width: 140px;
            display: inline-block;
            font-weight: 500;
            color: #343a40;
        }

        /* --- Tambahan untuk Ringkasan Tagihan yang lebih detail --- */
        .total-tagihan-box {
            background-color: #d4edda; /* Light green */
            border: 1px solid #28a745; /* Green border */
            padding: 15px;
            border-radius: 0.5rem;
            margin-top: 20px;
            color: #155724; /* Dark green */
        }
        .total-tagihan-box .fs-4 {
            font-weight: bold;
        }
        .detail-row { /* Reuse from tracking.php */
            display: flex;
            margin-bottom: 8px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 5px;
        }
        .detail-row:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label { /* Reuse from tracking.php */
            font-weight: 600;
            color: #343a40;
            flex: 0 0 160px; /* Lebar label yang disesuaikan */
        }
        .detail-value { /* Reuse from tracking.php */
            flex: 1;
            color: #495057;
        }
        .detail-item-box { /* Reuse from tracking.php */
            background-color: #f8f9fa;
            border: 1px solid #e2e6ea;
            padding: 10px 15px;
            border-radius: 0.5rem;
            margin-bottom: 10px;
        }
        .detail-item-box .detail-row {
            margin-bottom: 5px; /* Kurangi margin bawah */
            border-bottom: none; /* Hapus border di dalam item box */
            padding-bottom: 0;
        }
        .detail-item-box .detail-label,
        .detail-item-box .detail-value {
            font-size: 0.9rem; /* Lebih kecil untuk detail item */
        }

        /* --- Gaya untuk Metode Pembayaran --- */
        .payment-method-box {
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 20px;
            height: 100%; /* Agar tinggi kolom sama */
            background-color: #ffffff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }
        .payment-method-box .payment-title {
            font-weight: bold;
            color: #343a40;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            font-size: 1.25rem;
        }
        .payment-method-box .payment-title i {
            color: #28a745; /* Ikon hijau untuk metode pembayaran */
        }
        .bank-list {
            padding-left: 0;
            list-style: none;
        }
        .bank-list li {
            border: 1px solid #e9ecef;
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px 10px;
            position: relative;
        }
        .bank-list li strong {
            color: #343a40;
            margin-right: 10px;
        }
        .bank-list li p {
            margin: 0;
            color: #495057;
            font-size: 0.95rem;
        }
        .bank-list .bank-logo {
            height: 25px; /* Ukuran logo bank */
            margin-right: 10px;
            object-fit: contain;
            max-width: 80px; /* Batasi lebar logo agar tidak terlalu besar */
        }
        .copy-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            padding: 5px 10px;
            font-size: 0.8rem;
            background-color: #f0f0f0;
            border: 1px solid #dee2e6;
            color: #495057;
        }
        .copy-btn:hover {
            background-color: #e2e6ea;
        }
        .copy-btn i {
            margin-right: 5px;
        }
        /* Konfirmasi Pembayaran Section (akan dihapus) */
        /* .alert-warning {
            font-size: 0.95rem;
        }
        .alert-warning i {
            color: #ffc107;
        }
        .btn-konfirmasi {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 0.5rem;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }
        .btn-konfirmasi:hover {
            background-color: #218838;
        } */

        /* Footer styles */
        footer {
            background-color: #ffffff; /* Footer tetap putih */
            border-top: 1px solid #dee2e6;
            padding: 30px 20px; /* Tambah padding vertikal */
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        footer .footer-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center; /* Pusatkan kolom-kolom */
            gap: 30px 50px; /* Tambah jarak antar kolom */
            margin-bottom: 25px; /* Jarak antara info dan sosial media */
        }
        footer .footer-info div {
            flex-basis: auto; /* Biarkan browser menentukan lebar optimal */
            min-width: 200px; /* Minimal lebar kolom agar tidak terlalu sempit */
            max-width: 300px; /* Maksimal lebar kolom */
            text-align: left; /* Teks di dalam kolom rata kiri */
        }
        footer .footer-info div strong {
            display: block;
            margin-bottom: 10px; /* Jarak antara judul dan isi */
            color: #343a40; /* Judul info footer kembali ke hitam/abu-abu gelap */
            font-size: 1.1rem; /* Sedikit lebih besar untuk judul */
            font-weight: 600; /* Lebih tebal */
        }
        footer .footer-info div p {
            margin: 0;
            line-height: 1.6; /* Jarak baris lebih nyaman */
            color: #6c757d; /* Teks paragraf info footer kembali ke abu-abu muted */
        }
        /* Untuk link WhatsApp di footer */
        footer .footer-info div p a {
            color: #28a745 !important; /* Warna hijau untuk link WhatsApp */
            text-decoration: none;
            font-weight: 500;
        }
        footer .footer-info div p a:hover {
            text-decoration: underline;
        }
        footer .footer-info div p i {
            margin-right: 8px; /* Jarak ikon dengan teks */
        }

        footer .social-icons {
            margin-bottom: 20px; /* Jarak antara ikon sosial dan copyright */
            display: flex;
            justify-content: center; /* Pusatkan ikon sosial */
            gap: 15px; /* Jarak antar ikon */
        }
        footer .social-icons a {
            color: #6c757d; /* Ikon sosial kembali ke abu-abu muted */
            font-size: 1.5rem; /* Ukuran ikon sedikit lebih besar */
            transition: color 0.2s ease-in-out;
        }
        footer .social-icons a:hover {
            color: #28a745; /* Ikon sosial hover hijau */
        }

        footer .copyright-text {
            color: #6c757d;
            font-size: 0.85rem;
        }
        footer .copyright-text a {
            color: #6c757d; /* Teks link kembali ke abu-abu muted */
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        footer .copyright-text a:hover {
            color: #28a745; /* Link hover hijau */
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar .navbar-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            .navbar .navbar-toggler {
                display: block;
            }
            .navbar .navbar-collapse {
                display: none;
            }
            .navbar .navbar-collapse.show {
                display: flex;
                flex-direction: column;
            }
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .main-header h2 {
                width: 100%;
                text-align: center;
            }
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .detail-label {
                flex: none;
                width: auto;
                margin-bottom: 5px;
            }
            .total-aktual-box .detail-label {
                flex: none;
                width: auto;
                margin-bottom: 5px;
            }
            .welcome-section .instruction-steps {
                flex-direction: column;
            }
            footer .footer-info {
                flex-direction: column; /* Kolom menumpuk di mobile */
                align-items: center; /* Pusatkan di mobile */
                gap: 20px;
            }
            footer .footer-info div {
                text-align: center; /* Teks di dalam kolom rata tengah di mobile */
                min-width: unset; /* Hapus min-width di mobile */
                max-width: 80%; /* Batasi lebar agar tidak terlalu melebar */
            }
            footer .social-icons {
                margin-bottom: 15px;
            }
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
                        <a class="nav-link" href="service.php">
                            <i class="fas fa-desktop"></i>Pengajuan Service
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="transaksi_service.php">
                            <i class="fas fa-cash-register"></i>Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tracking.php">
                            <i class="fas fa-search-location"></i>Tracking Service
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i>Kembali ke Beranda
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
            <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Instruksi Pembayaran Service</h2>
        </div>

        <div class="container py-3">
            <?php if ($error_message): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php elseif ($service_info && $total_biaya_aktual_dari_detail !== null): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4 pb-2 border-bottom">Ringkasan Tagihan Service</h5>
                        <div class="detail-row">
                            <div class="detail-label">ID Service:</div>
                            <div class="detail-value">#<?php echo htmlspecialchars($service_info['id_service']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Nama Customer:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($service_info['nama_customer']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Perangkat:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($service_info['device']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Keluhan Utama:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($service_info['keluhan'] ?: '-')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status Service:</div>
                            <div class="detail-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $service_info['status'] ?: '-'))); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Tanggal Masuk:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($service_info['tanggal'] ?: '-'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Estimasi Waktu:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($service_info['estimasi_waktu'] ?: '-'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Estimasi Biaya Awal:</div>
                            <div class="detail-value">Rp <?php echo number_format($service_info['estimasi_harga'] ?? 0, 0, ',', '.'); ?></div>
                        </div>

                        <?php if (!empty($service_details_list)): ?>
                            <h6 class="mt-4 pb-2 border-bottom">Rincian Pengerjaan:</h6>
                            <?php foreach ($service_details_list as $index => $detail): ?>
                                <div class="detail-item-box">
                                    <?php if (count($service_details_list) > 1) : ?>
                                        <strong class="item-title">Item <?php echo $index + 1; ?>:</strong>
                                    <?php endif; ?>

                                    <?php if (!empty($detail['nama_barang'])): ?>
                                        <div class="detail-row"><div class="detail-label small">Sparepart:</div><div class="detail-value small"><?php echo htmlspecialchars($detail['nama_barang']); ?></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($detail['jenis_jasa'])): ?>
                                        <div class="detail-row"><div class="detail-label small">Jasa:</div><div class="detail-value small"><?php echo htmlspecialchars($detail['jenis_jasa']); ?></div></div>
                                    <?php endif; ?>
                                    <div class="detail-row"><div class="detail-label small">Deskripsi:</div><div class="detail-value small"><?php echo nl2br(htmlspecialchars($detail['detail_kerusakan_deskripsi'])); ?></div></div>
                                    <div class="detail-row"><div class="detail-label small">Biaya:</div><div class="detail-value small">Rp <?php echo number_format($detail['detail_total'], 0, ',', '.'); ?></div></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="total-tagihan-box mt-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-bold fs-5">TOTAL TAGIHAN:</div>
                                <div class="fw-bold fs-4 text-success">Rp <?php echo number_format($total_biaya_aktual_dari_detail, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4 pb-2 border-bottom">Pilih Metode Pembayaran</h5>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="payment-method-box">
                                    <h6 class="payment-title"><i class="fas fa-store me-2"></i>1. Bayar Langsung di Toko</h6>
                                    <p class="text-muted">Anda bisa melakukan pembayaran secara tunai atau metode lain yang tersedia di toko kami:</p>
                                    <div class="detail-row">
                                        <div class="detail-label">Jam Operasional:</div>
                                        <div class="detail-value">Senin - Jumat: 09:00 - 18:00 WIB<br>Sabtu: 09:00 - 15:00 WIB</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Lokasi:</div>
                                        <div class="detail-value">Jl. Perintis Kemerdekaan No. 100, Tasikmalaya</div>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.575608678287!2d108.2185250147754!3d-7.291244994783353!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e657f20224168fd%3A0xc3f6d71f5c6e8e8!2sJl.%20Perintis%20Kemerdekaan%20No.100%2C%20Cihideung%2C%20Kec.%20Cihideung%2C%20Kota%20Tasikmalaya%2C%20Jawa%20Barat%2046123%2C%20Indonesia!5e0!3m2!1sen!2sid!4v1678901234567!5m2!1sen!2sid" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                        <p class="small text-muted mt-2">Pastikan perangkat sudah siap diambil. Hubungi kami jika ada pertanyaan: <br><a href="https://wa.me/6281234567890" target="_blank" class="text-decoration-none"><i class="fab fa-whatsapp"></i> 0812-3456-7890</a></p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="payment-method-box">
                                    <h6 class="payment-title"><i class="fas fa-money-check-alt me-2"></i>2. Transfer Bank Manual</h6>
                                    <p class="text-muted">Lakukan transfer ke salah satu rekening bank berikut ini:</p>
                                    <ul class="list-unstyled bank-list">
                                        <li>
                                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Bank_Central_Asia.svg/1280px-Bank_Central_Aca.svg.png" alt="BCA Logo" class="bank-logo">
                                            <strong>BCA</strong>
                                            <p>No. Rekening: <span id="rekBCA">1234567890</span> <button class="btn btn-sm btn-outline-secondary copy-btn" data-target="rekBCA"><i class="far fa-copy"></i> Salin</button></p>
                                            <p>Atas Nama: PT. Thar'z Computer</p>
                                        </li>
                                        <li>
                                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Bank_Mandiri_logo.svg/1280px-Bank_Mandiri_logo.svg.png" alt="Mandiri Logo" class="bank-logo">
                                            <strong>Mandiri</strong>
                                            <p>No. Rekening: <span id="rekMandiri">0987654321</span> <button class="btn btn-sm btn-outline-secondary copy-btn" data-target="rekMandiri"><i class="far fa-copy"></i> Salin</button></p>
                                            <p>Atas Nama: PT. Thar'z Computer</p>
                                        </li>
                                        <li>
                                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/be/DANA_logo_PNG.png/800px-DANA_logo_PNG.png" alt="Dana Logo" class="bank-logo">
                                            <strong>DANA (E-Wallet)</strong>
                                            <p>No. Dana: <span id="rekDana">081234567890</span> <button class="btn btn-sm btn-outline-secondary copy-btn" data-target="rekDana"><i class="far fa-copy"></i> Salin</button></p>
                                            <p>Atas Nama: [Isi Nama Pemilik Akun Dana]</p>
                                        </li>
                                    </ul>
                                    <p class="small text-muted mt-3">**Penting:** Pastikan jumlah transfer **tepat** sesuai Total Tagihan (Rp <?php echo number_format($total_biaya_aktual_dari_detail, 0, ',', '.'); ?>).</p>
                                    <p class="small text-muted">Untuk transfer via Mobile Banking, pilih menu 'Transfer', lalu 'Antar Bank' (jika berbeda bank) dan masukkan nomor rekening di atas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
            <div class="text-center mt-4 py-4 border-top">
                <?php if ($id_service_input): ?>
                    <a href="tracking.php?id_service=<?php echo htmlspecialchars($id_service_input); ?>" class="btn btn-primary btn-lg"><i class="fas fa-eye"></i> Lihat Status Service Saya</a>
                <?php else: ?>
                    <a href="tracking.php" class="btn btn-primary btn-lg"><i class="fas fa-search"></i> Kembali ke Halaman Tracking</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2"><i class="fas fa-home"></i> Kembali ke Beranda</a>
            </div>
        </div>

        <footer class="mt-auto">
            <div class="container footer-info">
                <div>
                    <strong>Alamat Kami</strong>
                    <p>Jl. Perintis Kemerdekaan No. 100</p>
                    <p>Tasikmalaya, Jawa Barat 46123</p>
                </div>
                <div>
                    <strong>Jam Operasional</strong>
                    <p>Senin - Jumat: 09:00 - 18:00 WIB</p>
                    <p>Sabtu: 09:00 - 15:00 WIB</p>
                    <p>Minggu: Tutup</p>
                </div>
                <div>
                    <strong>Hubungi Kami</strong>
                    <p>Telepon: (0265) 123456</p>
                    <p>Email: info@tharizcomputer.com</p>
                    <p><a href="https://wa.me/6281234567890" target="_blank"><i class="fab fa-whatsapp"></i> WhatsApp</a></p>
                </div>
            </div>
            <div class="social-icons mb-2">
                <a href="#" target="_blank" title="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="#" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
            </div>
            <p class="copyright-text">© <?php echo date("Y"); ?> Thar'z Computer. All rights reserved. | <a href="#" class="text-muted text-decoration-none">Kebijakan Privasi</a> | <a href="#" class="text-muted text-decoration-none">Syarat & Ketentuan</a></p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fungsi untuk menyalin teks
            document.querySelectorAll('.copy-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const textToCopy = document.getElementById(targetId).innerText;
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        alert('Nomor rekening berhasil disalin!');
                        // Opsional: ganti teks tombol menjadi "Tersalin!" sementara
                        const originalHtml = this.innerHTML; // Simpan HTML asli
                        this.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
                        setTimeout(() => {
                            this.innerHTML = originalHtml; // Kembalikan HTML asli
                        }, 2000);
                    }).catch(err => {
                        console.error('Gagal menyalin: ', err);
                        alert('Gagal menyalin nomor rekening. Silakan salin manual.');
                    });
                });
            });

            // Adjust company name header font size for responsiveness
            function adjustCompanyNameSize() {
                const companyName = document.querySelector('.company-name-header');
                if (window.innerWidth <= 576) { // Bootstrap's 'sm' breakpoint
                    companyName.style.fontSize = '1rem';
                } else {
                    companyName.style.fontSize = '1.25rem';
                }
            }

            adjustCompanyNameSize(); // Call on load
            window.addEventListener('resize', adjustCompanyNameSize); // Call on resize
        });
    </script>
</body>

</html>