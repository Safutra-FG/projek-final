<?php
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

$service_info = null; // Untuk menyimpan data utama service
$service_details_list = []; // Untuk menyimpan daftar item dari detail_service
$error_message = null;
$total_biaya_aktual_dari_detail = 0; // Inisialisasi total aktual dari detail

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_service'])) {
    $id_service_input = trim($_POST['id_service']);

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong.";
    } else {
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
            $stmt->bind_param("s", $id_service_input);
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
                    if ($row['id_ds'] !== null) {
                        $current_detail_total = $row['detail_total'] ?: 0;
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

// Dummy data for account name (not used in display, but present if you want to use it elsewhere)
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
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #0d6efd;
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
            color: #007bff;
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
            background-color: #0d6efd; /* Adjusted to match Bootstrap primary button color */
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
            background-color: #0a58ca; /* Darker shade for hover */
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
            color: #007bff;
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 1px solid #eee;
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
            background-color: #e9f5ff;
            border: 1px solid #b3d7ff;
            border-left: 5px solid #007bff;
            padding: 15px;
            border-radius: 0.5rem;
            margin-top: 25px;
            margin-bottom: 25px;
            text-align: center;
        }
        .status-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: #007bff;
            margin-bottom: 8px;
        }
        .status-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #28a745; /* Green for success/ready, adjust as needed */
            text-transform: uppercase;
        }
        h4 {
            color: #007bff;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .detail-item-box {
            background-color: #f8f9fa;
            border: 1px solid #e2e6ea;
            padding: 15px;
            border-radius: 0.5rem;
            margin-bottom: 15px;
        }
        .item-title {
            color: #007bff;
            margin-bottom: 10px;
            display: block;
            font-size: 1.1rem;
        }
        .total-aktual-box {
            background-color: #d4edda; /* Light green */
            border: 1px solid #28a745; /* Green */
            padding: 15px;
            border-radius: 0.5rem;
            margin-top: 30px;
            font-size: 1.3rem;
            font-weight: bold;
            color: #155724; /* Dark green */
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
            background-color: #ffc107; /* Warning color for payment */
            color: #343a40;
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
            background-color: #e0a800; /* Darker yellow for hover */
            color: #343a40;
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
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
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

    <div class="flex-grow-1 p-3">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4 pb-2 border-bottom">Cari Detail Service Anda</h5>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="id_service" class="form-label">ID Service:</label>
                        <input type="text" class="form-control" name="id_service" id="id_service" value="<?php echo isset($_POST['id_service']) ? htmlspecialchars($_POST['id_service']) : ''; ?>" placeholder="Masukkan ID Service Anda" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Tracking</button>
                </form>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($service_info): ?>
            <div class="service-details">
                <h3>Detail Service Utama</h3>

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
                    <div class="status-value"><?php echo htmlspecialchars($service_info['status']); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Estimasi Waktu:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($service_info['estimasi_waktu'] ?: '-'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Estimasi Biaya Awal:</div>
                    <div class="detail-value">Rp <?php echo number_format($service_info['estimasi_harga'] ?: 0, 0, ',', '.'); ?> (Ini hanya perkiraan awal)</div>
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

                <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $service_info): ?>
                    <p style="margin-top:20px; color: #555;">Belum ada rincian pengerjaan spesifik (sparepart atau jasa tambahan) yang dicatat untuk service ini.</p>
                    <div class="total-aktual-box">
                        <div class="detail-row">
                            <div class="detail-label">TOTAL TAGIHAN:</div>
                            <div class="detail-value">Rp <?php echo number_format(0, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                $jumlah_final_untuk_dibayar = $total_biaya_aktual_dari_detail;
                if ($service_info && $jumlah_final_untuk_dibayar > 0 &&  ($service_info['status'] == 'selesai' || $service_info['status'] == 'diperbaiki' || $service_info['status'] == 'siap diambil')) : // Sesuaikan status
                ?>
                    <div class="detail-row" style="margin-top:25px;">
                        <button type="button" onclick="bayar('<?php echo htmlspecialchars($service_info['id_service']); ?>', <?php echo $jumlah_final_untuk_dibayar; ?>)" class="btn btn-bayar">Bayar Sekarang</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="mt-auto p-4 border-top text-center text-muted small">
        &copy; Tharz Computer 2025
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function bayar(idService, amountToPay) {
        // Mengarahkan ke halaman transaksi dengan ID service dan jumlah yang harus dibayar.
        window.location.href = 'transaksi_service.php?id_service=' + encodeURIComponent(idService) + '&amount=' + amountToPay;
    }
</script>
</body>
</html>