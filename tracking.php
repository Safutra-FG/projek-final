<?php
session_start();
include 'koneksi.php';

require_once 'pembayaran_helper.php';

// Inisialisasi variabel
$service_info = null;
$service_details_list = [];
$error_message = null;
$total_biaya_aktual_dari_detail = 0;
$namaAkun = "Customer"; // Default account name

// Fungsi untuk membuat koneksi database
// Menangani permintaan POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_service'])) {
    $id_service_input = trim($_POST['id_service']);

    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong.";
    } else {
        // Koneksi database sudah dibuat di koneksi.php
        $sql = "SELECT
                    s.id_service, s.tanggal, s.device, s.keluhan, s.status,
                    s.estimasi_waktu, s.estimasi_harga, s.tanggal_selesai,
                    c.nama_customer
                FROM
                    service s
                JOIN
                    customer c ON s.id_customer = c.id_customer
                WHERE
                    s.id_service = ?";

        $stmt = $koneksi->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $id_service_input);
            $stmt->execute();
            $hasil = $stmt->get_result();

            if ($hasil->num_rows > 0) {
                $row = $hasil->fetch_assoc();
                $service_info = [
                    'id_service' => $row['id_service'],
                    'tanggal' => $row['tanggal'],
                    'nama_customer' => $row['nama_customer'],
                    'device' => $row['device'],
                    'keluhan' => $row['keluhan'],
                    'status' => $row['status'],
                    'estimasi_waktu' => $row['estimasi_waktu'],
                    'estimasi_harga' => $row['estimasi_harga'],
                    'tanggal_selesai' => $row['tanggal_selesai'],
                ];

                // Query untuk detail service
                $sql_detail = "SELECT 
                                ds.id_ds,
                                ds.kerusakan AS detail_kerusakan_deskripsi,
                                ds.total AS detail_total,
                                b.nama_barang,
                                j.jenis_jasa
                            FROM 
                                detail_service ds
                            LEFT JOIN
                                stok b ON ds.id_barang = b.id_barang
                            LEFT JOIN
                                jasa j ON ds.id_jasa = j.id_jasa
                            WHERE 
                                ds.id_service = ?
                            ORDER BY 
                                ds.id_ds ASC";
                
                $stmt_detail = $koneksi->prepare($sql_detail);
                $stmt_detail->bind_param("s", $id_service_input);
                $stmt_detail->execute();
                $hasil_detail = $stmt_detail->get_result();
                
                $service_details_list = [];
                $total_biaya_aktual_dari_detail = 0;
                
                while ($row_detail = $hasil_detail->fetch_assoc()) {
                    $current_detail_total = $row_detail['detail_total'] ?: 0;
                    $service_details_list[] = [
                        'nama_barang' => $row_detail['nama_barang'],
                        'jenis_jasa' => $row_detail['jenis_jasa'],
                        'detail_kerusakan_deskripsi' => $row_detail['detail_kerusakan_deskripsi'],
                        'detail_total' => $current_detail_total
                    ];
                    $total_biaya_aktual_dari_detail += $current_detail_total;
                }
                $stmt_detail->close();

                // Query untuk transaksi
                $sql_transaksi = "SELECT id_transaksi, status FROM transaksi WHERE id_service = ? ORDER BY id_transaksi DESC";
                $stmt_transaksi = $koneksi->prepare($sql_transaksi);
                $stmt_transaksi->bind_param("s", $id_service_input);
                $stmt_transaksi->execute();
                $hasil_transaksi = $stmt_transaksi->get_result();
                
                $list_id_transaksi = [];
                $status_pembayaran = 'Belum Bayar';
                $first_row = true;
                while ($row_transaksi = $hasil_transaksi->fetch_assoc()) {
                    $list_id_transaksi[] = $row_transaksi['id_transaksi'];
                    // Ambil status dari transaksi terakhir
                    if ($first_row) {
                        $status_pembayaran = $row_transaksi['status'];
                        $first_row = false;
                    }
                }
                $stmt_transaksi->close();

                // Query pembayaran (riwayat bayar)
                $riwayat_bayar = [];
                $total_bayar = 0;
                if (!empty($list_id_transaksi)) {
                    // Ambil semua pembayaran dari semua transaksi
                    $sql_total_bayar = "SELECT SUM(jumlah) as total FROM bayar WHERE id_transaksi IN (" . implode(',', $list_id_transaksi) . ")";
                    $result_total = $koneksi->query($sql_total_bayar);
                    if ($result_total && $row_total = $result_total->fetch_assoc()) {
                        $total_bayar = $row_total['total'] ?: 0;
                    }

                    // Ambil riwayat pembayaran untuk ditampilkan
                    foreach ($list_id_transaksi as $id_transaksi_item) {
                        list($_, $riwayat_bayar_item) = get_total_bayar($koneksi, $id_transaksi_item);
                        $riwayat_bayar = array_merge($riwayat_bayar, $riwayat_bayar_item);
                    }
                }

                // Simpan ke service_info
                $service_info['status_pembayaran'] = $status_pembayaran;
                $service_info['jumlah_bayar'] = $total_bayar;
                $service_info['total_tagihan'] = $total_biaya_aktual_dari_detail;
            } else {
                $error_message = "ID Service tidak ditemukan atau tidak valid.";
            }
            $stmt->close();
        } else {
            $error_message = "Terjadi kesalahan dalam menyiapkan data. Error: " . $koneksi->error;
        }
        $koneksi->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thar'z Computer - Tracking Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="tracking_style.css">
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

                <div class="status-box" style="padding: 10px; margin-top: 15px; margin-bottom: 15px;">
                    <div class="status-title" style="font-size: 1rem;">Status Service</div>
                    <div class="status-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($service_info['status']); ?></div>
                </div>

                <div class="status-box" style="background-color: #fff3cd; border-color: #ffeeba; padding: 10px; margin-top: 15px; margin-bottom: 15px;">
                    <div class="status-title" style="font-size: 1rem;">Status Pembayaran</div>
                    <div class="status-value" style="font-size: 1.2rem; color: <?php 
                        echo $service_info['status_pembayaran'] == 'Lunas' ? '#28a745' : 
                            ($service_info['status_pembayaran'] == 'DP' ? '#ffc107' : '#dc3545'); 
                    ?>">
                        <?php echo htmlspecialchars($service_info['status_pembayaran']); ?>
                    </div>
                    <?php if ($service_info['status_pembayaran'] != 'Belum Bayar'): ?>
                        <div style="margin-top: 5px; font-size: 0.9rem; color: #666;">
                            Jumlah yang sudah dibayar: Rp <?php echo number_format($service_info['jumlah_bayar'], 0, ',', '.'); ?>
                        </div>
                    <?php endif; ?>
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
                // Tombol "Bayar Sekarang" hanya muncul jika:
                // 1. Ada total biaya aktual
                // 2. Status service relevan
                // 3. Status pembayaran BUKAN Lunas
                if ($service_info && $jumlah_final_untuk_dibayar > 0 &&
                    ($service_info['status'] == 'selesai' || $service_info['status'] == 'diperbaiki' || $service_info['status'] == 'siap diambil') &&
                    $service_info['status_pembayaran'] != 'Lunas') :
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