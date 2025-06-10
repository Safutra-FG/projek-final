<?php
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Check connection
if ($koneksi->connect_error) {
    die("Koneksi ke database gagal: " . $koneksi->connect_error);
}

$service_info = null; // Untuk menyimpan data utama service
$service_details_list = []; // Untuk menyimpan daftar item dari detail_service
$error_message = null;
$total_biaya_aktual_dari_detail = 0; // Inisialisasi total aktual dari detail

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_service'])) {
    $id_service_input = trim($_POST['id_service']);

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong.";
    } else {
        // Gunakan Prepared Statements untuk keamanan
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
                        $service_info = [
                            'id_service' => $row['id_service'],
                            'tanggal' => $row['tanggal'],
                            'nama_customer' => $row['nama_customer'],
                            'device' => $row['device'],
                            'keluhan' => $row['keluhan'],
                            'status' => $row['status'],
                            'estimasi_waktu' => $row['estimasi_waktu'],
                            'estimasi_harga' => $row['estimasi_harga'], // Ini estimasi awal
                            'tanggal_selesai' => $row['tanggal_selesai']
                        ];
                        $first_row_processed = true;
                    }
                    if ($row['id_ds'] !== null) { // Only add detail if it exists
                        $current_detail_total = $row['detail_total'] ?? 0; // Use null coalescing for PHP 7+
                        $service_details_list[] = [
                            'nama_barang' => $row['nama_barang'],
                            'jenis_jasa' => $row['jenis_jasa'],
                            'detail_kerusakan_deskripsi' => $row['detail_kerusakan_deskripsi'],
                            'detail_total' => $current_detail_total
                        ];
                        $total_biaya_aktual_dari_detail += $current_detail_total; // Akumulasi total aktual dari detail
                    }
                }
            } else {
                $error_message = "ID Service tidak ditemukan atau tidak valid.";
            }
            $stmt->close();
        } else {
            $error_message = "Terjadi kesalahan dalam menyiapkan data. Error: " . $koneksi->error;
        }
    }
}

// Dummy data for account name
$namaAkun = "Customer";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thar'z Computer - Tracking Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            font-family: sans-serif;
            min-height: 100vh;
            background-color: #f8f9fa; /* Latar belakang umum yang cerah */
        }
        .navbar {
            background-color: #ffffff; /* Navbar tetap putih */
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
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .btn-submit {
            background-color: #28a745; /* Tombol submit hijau */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.2s ease;
        }
        .btn-submit:hover {
            background-color: #218838; /* Hover tombol submit hijau lebih gelap */
        }
        .alert-dismissible .btn-close {
            position: absolute;
            right: 0;
            padding: 0.5rem 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Tracking specific styles */
        .service-details {
            margin-top: 30px;
            background-color: #fff;
            padding: 25px;
            border-radius: 0.75rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .service-details h3 {
            color: #343a40; /* Judul detail service utama hitam/abu-abu gelap */
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 1px solid #dee2e6; /* Garis bawah abu-abu */
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 8px;
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label {
            font-weight: 600;
            color: #343a40;
            flex: 0 0 180px; /* Fixed width for labels */
        }
        .detail-value {
            flex: 1;
            color: #495057;
        }
        .status-box {
            background-color: #e6ffe6; /* Light green for status box */
            border: 1px solid #28a745; /* Green border */
            border-left: 5px solid #28a745; /* Green left border */
            padding: 15px;
            border-radius: 0.5rem;
            margin-top: 25px;
            margin-bottom: 25px;
            text-align: center;
        }
        .status-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: #28a745; /* Green for status title */
            margin-bottom: 8px;
        }
        .status-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #155724; /* Dark green for status value */
            text-transform: uppercase;
        }
        h4 {
            color: #343a40; /* Judul sub-section hitam/abu-abu gelap */
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 1px solid #dee2e6; /* Garis bawah abu-abu */
            padding-bottom: 8px;
        }
        .detail-item-box {
            background-color: #f8f9fa; /* Latar belakang item detail abu-abu muda */
            border: 1px solid #e2e6ea;
            padding: 15px;
            border-radius: 0.5rem;
            margin-bottom: 15px;
        }
        .item-title {
            color: #343a40; /* Judul item detail hitam/abu-abu gelap */
            margin-bottom: 10px;
            display: block;
            font-size: 1.1rem;
        }
        .total-aktual-box {
            background-color: #d4edda; /* Light green for total box */
            border: 1px solid #28a745; /* Green border */
            padding: 15px;
            border-radius: 0.5rem;
            margin-top: 30px;
            font-size: 1.3rem;
            font-weight: bold;
            color: #155724; /* Dark green for total text */
            text-align: center;
        }
        .total-aktual-box .detail-label,
        .total-aktual-box .detail-value {
            font-size: 1.3rem;
            font-weight: bold;
        }
        .total-aktual-box .detail-label {
            flex: 0 0 200px;
        }
        .btn-bayar {
            background-color: #28a745; /* Tombol bayar hijau */
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.2s ease;
            width: 100%;
            max-width: 300px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-bayar:hover {
            background-color: #218838; /* Hover tombol bayar hijau lebih gelap */
            color: white;
        }
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 0.5rem;
            margin-top: 20px;
            text-align: center;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #495057; /* Kembali ke abu-abu untuk link */
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        /* Hero/Welcome Section */
        .welcome-section {
            background-color: #e9ecef; /* Abu-abu muda */
            border-radius: 0.75rem;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }
        .welcome-section h3 {
            color: #343a40;
            margin-bottom: 15px;
        }
        .welcome-section p {
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .welcome-section .instruction-steps {
            list-style: none;
            padding: 0;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .welcome-section .instruction-steps li {
            background-color: #ffffff;
            padding: 10px 20px;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-weight: 500;
            color: #495057;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* FAQ Section (reused from service.php, adjusted colors) */
        .faq-item {
            margin-bottom: 10px;
        }
        .faq-item .faq-question {
            font-weight: bold;
            color: #343a40;
            cursor: pointer;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .faq-item .faq-answer {
            display: none;
            padding: 10px 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .faq-item.active .faq-answer {
            display: block;
        }
        .faq-item .faq-question i {
            transition: transform 0.2s ease-in-out;
            color: #6c757d; /* Ikon chevron kembali ke abu-abu muted */
        }
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* Footer styles (reused from service.php, adjusted colors) */
        footer {
            background-color: #ffffff; /* Footer kembali ke putih */
            border-top: 1px solid #dee2e6;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        footer .footer-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px 40px;
            margin-bottom: 15px;
        }
        footer .footer-info div {
            flex-basis: auto;
        }
        footer .footer-info div strong {
            display: block;
            margin-bottom: 5px;
            color: #343a40; /* Judul info footer kembali ke hitam/abu-abu gelap */
            font-size: 1rem;
        }
        footer .footer-info div p {
            margin: 0;
            color: #6c757d; /* Teks paragraf info footer kembali ke abu-abu muted */
        }
        footer .social-icons a {
            color: #6c757d; /* Ikon sosial kembali ke abu-abu muted */
            margin: 0 8px;
            font-size: 1.2rem;
            transition: color 0.2s;
        }
        footer .social-icons a:hover {
            color: #28a745; /* Ikon sosial hover hijau */
        }
        footer p a { /* Link Kebijakan Privasi dll. */
            color: #6c757d !important; /* Teks link kembali ke abu-abu muted */
        }
        footer p a:hover {
            color: #28a745 !important; /* Link hover hijau */
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

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                    <a class="nav-link active" aria-current="page" href="tracking.php">
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
        <h2 class="h4 text-dark mb-0 text-center flex-grow-1">Tracking Service</h2>
    </div>

    <div class="container py-3">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4 pb-2 border-bottom">Cari Detail Service Anda</h5>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="id_service" class="form-label">ID Service:</label>
                        <input type="text" class="form-control" name="id_service" id="id_service" value="<?php echo isset($_POST['id_service']) ? htmlspecialchars($_POST['id_service']) : ''; ?>" placeholder="Masukkan ID Service Anda" required>
                    </div>
                    <button type="submit" class="btn btn-submit w-100">Tracking</button>
                </form>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($service_info): ?>
            <div class="service-details">
                <h3>Detail Service Utama <span class="text-muted">(ID: #<?php echo htmlspecialchars($service_info['id_service']); ?>)</span></h3>

                <div class="detail-row">
                    <div class="detail-label">Tanggal Masuk:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($service_info['tanggal']); ?></div>
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
                    <div class="detail-label">Keluhan Utama: </div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($service_info['keluhan'])); ?></div>
                </div>

                <div class="status-box">
                    <div class="status-title">Status Service</div>
                    <div class="status-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $service_info['status']))); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Estimasi Waktu:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($service_info['estimasi_waktu'] ?: '-'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Estimasi Biaya Awal:</div>
                    <div class="detail-value">Rp <?php echo number_format($service_info['estimasi_harga'] ?? 0, 0, ',', '.'); ?> (Ini hanya perkiraan awal)</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Tanggal Selesai:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($service_info['tanggal_selesai'] ?: '-'); ?></div>
                </div>

                <?php if (!empty($service_details_list)): ?>
                    <h4>Rincian Pengerjaan & Biaya Sparepart/Tambahan:</h4>
                    <?php foreach ($service_details_list as $index => $detail): ?>
                        <div class="detail-item-box">
                            <?php if (count($service_details_list) > 1) : ?>
                                <strong class="item-title">Rincian Item <?php echo $index + 1; ?>:</strong>
                            <?php endif; ?>

                            <?php if (!empty($detail['nama_barang'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Sparepart:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($detail['nama_barang']); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($detail['jenis_jasa'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Jasa Tambahan:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($detail['jenis_jasa']); ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="detail-row">
                                <div class="detail-label">Deskripsi Pengerjaan:</div>
                                <div class="detail-value"><?php echo nl2br(htmlspecialchars($detail['detail_kerusakan_deskripsi'])); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Biaya Item Ini:</div>
                                <div class="detail-value">Rp <?php echo number_format($detail['detail_total'], 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="total-aktual-box">
                        <div class="detail-row">
                            <div class="detail-label">TOTAL TAGIHAN:</div>
                            <div class="detail-value">Rp <?php echo number_format($total_biaya_aktual_dari_detail, 0, ',', '.'); ?></div>
                        </div>
                    </div>

                <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $service_info): /* Tampil jika ID ditemukan tapi detail kosong */ ?>
                    <p style="margin-top:20px; color: #555; text-align: center;">Belum ada rincian pengerjaan spesifik (sparepart atau jasa tambahan) yang dicatat untuk service ini. Silakan hubungi admin untuk info lebih lanjut.</p>
                    <div class="total-aktual-box">
                        <div class="detail-row">
                            <div class="detail-label">TOTAL TAGIHAN:</div>
                            <div class="detail-value">Rp <?php echo number_format(0, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                $jumlah_final_untuk_dibayar = $total_biaya_aktual_dari_detail;
                // Tombol Bayar hanya muncul jika ada total biaya aktual > 0 DAN statusnya 'Dikonfirmasi', 'selesai' atau 'siap diambil'
                if ($service_info && $jumlah_final_untuk_dibayar > 0 &&
                    ($service_info['status'] == 'dikonfirmasi' || $service_info['status'] == 'selesai' || $service_info['status'] == 'siap diambil')):
                ?>
                    <div class="detail-row" style="margin-top:25px;">
                        <button type="button" onclick="bayar('<?php echo htmlspecialchars($service_info['id_service']); ?>', <?php echo $jumlah_final_untuk_dibayar; ?>)" class="btn btn-bayar">Bayar Sekarang</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: /* Tampilan awal ketika halaman dimuat pertama kali atau setelah submit kosong/error */ ?>
            <div class="welcome-section">
                <h3>Lacak Status Perbaikan Perangkat Anda</h3>
                <p>Masukkan ID Service yang Anda terima saat mengajukan perbaikan untuk melihat status terkini, estimasi waktu, dan rincian biaya.</p>
                <ul class="instruction-steps">
                    <li><i class="fas fa-hand-point-right me-2"></i>Dapatkan ID Service dari bukti pengajuan.</li>
                    <li><i class="fas fa-keyboard me-2"></i>Masukkan ID Service ke kolom di atas.</li>
                    <li><i class="fas fa-search me-2"></i>Klik tombol "Tracking".</li>
                </ul>
                <p class="text-muted mt-3">Jika Anda belum mengajukan service, silakan kunjungi halaman <a href="service.php" class="text-decoration-none">Pengajuan Service</a> kami.</p>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h5 class="card-title mb-4 pb-2 border-bottom text-center">Pertanyaan Seputar Tracking Service (FAQ)</h5>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            Apa itu ID Service? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            ID Service adalah nomor unik yang diberikan kepada Anda saat pertama kali mengajukan perbaikan perangkat di Thar'z Computer. Nomor ini digunakan untuk melacak status dan detail perbaikan Anda.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            Di mana saya bisa menemukan ID Service? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            ID Service biasanya tercetak pada tanda terima atau bukti pengajuan service yang Anda terima dari kami. Jika Anda mengajukan secara online, nomor tersebut akan muncul di halaman konfirmasi dan mungkin dikirimkan melalui email.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            Kenapa status service saya belum berubah? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Update status akan dilakukan secara berkala oleh teknisi kami. Jika Anda merasa status terlalu lama tidak berubah atau ada kekhawatiran, Anda bisa menghubungi customer service kami.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            Apakah saya bisa langsung membayar setelah service selesai? <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Ya, jika status service sudah "Selesai" atau "Siap Diambil" dan rincian biaya sudah tersedia, Anda akan melihat tombol "Bayar Sekarang" untuk memproses pembayaran.
                        </div>
                    </div>
                </div>
            </div>
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
                <p><a href="https://wa.me/6281234567890" target="_blank" class="text-decoration-none text-muted"><i class="fab fa-whatsapp"></i> WhatsApp</a></p>
            </div>
        </div>
        <div class="social-icons mb-2">
            <a href="#" target="_blank" title="Facebook"><i class="fab fa-facebook"></i></a>
            <a href="#" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Thar'z Computer. All rights reserved. | <a href="#" class="text-muted text-decoration-none">Kebijakan Privasi</a> | <a href="#" class="text-muted text-decoration-none">Syarat & Ketentuan</a></p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // FAQ Accordion
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            item.querySelector('.faq-question').addEventListener('click', () => {
                // Tutup semua item FAQ yang sedang aktif kecuali yang diklik
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });
                // Toggle kelas 'active' pada item yang diklik
                item.classList.toggle('active');
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

    function bayar(idService, amountToPay) {
        // Mengarahkan ke halaman transaksi dengan ID service dan jumlah yang harus dibayar.
        window.location.href = 'transaksi_service.php?id_service=' + encodeURIComponent(idService) + '&amount=' + amountToPay;
    }
</script>
</body>
</html>