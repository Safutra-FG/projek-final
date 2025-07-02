<?php
// Selalu letakkan session_start() di baris paling awal
session_start();
include 'koneksi.php'; // Pastikan path ini benar

require_once 'pembayaran_helper.php'; // Pastikan path ini benar

// --- BLOK PENANGANAN PESAN & KONFIRMASI ---
$konfirmasi_message = null;
$konfirmasi_error = null;

if (isset($_SESSION['konfirmasi_message'])) {
    $konfirmasi_message = $_SESSION['konfirmasi_message'];
    unset($_SESSION['konfirmasi_message']);
}
if (isset($_SESSION['konfirmasi_error'])) {
    $konfirmasi_error = $_SESSION['konfirmasi_error'];
    unset($_SESSION['konfirmasi_error']);
}

// Proses form konfirmasi (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['konfirmasi_servis'])) {

    $id_service_konfirmasi = $_POST['id_service_konfirmasi'];
    $aksi = $_POST['aksi'];

    $koneksi->begin_transaction();
    try {
        if ($aksi == 'setuju') {
            $sql_update = "UPDATE service SET konfirmasi = 1, status = 'Dikonfirmasi' WHERE id_service = ?";
            $stmt_update = $koneksi->prepare($sql_update);
            if (!$stmt_update) throw new Exception("Prepare statement gagal.");
            $stmt_update->bind_param("s", $id_service_konfirmasi);
            $pesan_sukses = "Terima kasih, persetujuan Anda telah kami catat.";
        } else {
            $new_status = 'Dibatalkan';
            $sql_update = "UPDATE service SET status = ?, konfirmasi = 0 WHERE id_service = ?";
            $stmt_update = $koneksi->prepare($sql_update);
            if (!$stmt_update) throw new Exception("Prepare statement gagal.");
            $stmt_update->bind_param("ss", $new_status, $id_service_konfirmasi);
            $pesan_sukses = "Service telah dibatalkan sesuai permintaan Anda.";
        }
        if ($stmt_update->execute()) {
            $_SESSION['konfirmasi_message'] = $pesan_sukses;
            $koneksi->commit();
        } else {
            throw new Exception("Gagal memperbarui data service.");
        }
        $stmt_update->close();
    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['konfirmasi_error'] = "Terjadi kesalahan pada server: " . $e->getMessage();
    }
    header("Location: tracking.php?id_service=" . urlencode($id_service_konfirmasi));
    exit();
}

// --- BLOK PENGAMBILAN DATA ---
$service_info = null;
$service_details_list = [];
$error_message = null;
$total_biaya_aktual_dari_detail = 0;
$namaAkun = "Customer";
    
$id_service_input = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_service'])) {
    $id_service_input = trim($_POST['id_service']);
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id_service'])) {
    $id_service_input = trim($_GET['id_service']);
}

if ($id_service_input) {
    if (empty($id_service_input)) {
        $error_message = "ID Service tidak boleh kosong.";
    } else {
        // PERBAIKAN DI SINI: Langsung gunakan $koneksi, jangan buat koneksi baru
        if (!isset($koneksi) || !$koneksi->ping()) {
            include 'koneksi.php';
        }

        $sql = "SELECT
                    s.id_service, s.tanggal, s.device, s.keluhan, s.status, s.konfirmasi, s.kerusakan,
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
                    'konfirmasi' => $row['konfirmasi'],
                    'kerusakan' => $row['kerusakan'],
                    'estimasi_waktu' => $row['estimasi_waktu'],
                    'estimasi_harga' => $row['estimasi_harga'],
                    'tanggal_selesai' => $row['tanggal_selesai'],
                ];

                // Sisa kode query menggunakan $koneksi (bukan $koneksi_internal)
                $sql_detail = "SELECT ds.id_ds, ds.kerusakan AS detail_kerusakan_deskripsi, ds.total AS detail_total, b.nama_barang, j.jenis_jasa FROM detail_service ds LEFT JOIN stok b ON ds.id_barang = b.id_barang LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa WHERE ds.id_service = ? ORDER BY ds.id_ds ASC";
                $stmt_detail = $koneksi->prepare($sql_detail);
                $stmt_detail->bind_param("s", $id_service_input);
                $stmt_detail->execute();
                $hasil_detail = $stmt_detail->get_result();
                while ($row_detail = $hasil_detail->fetch_assoc()) {
                    $current_detail_total = $row_detail['detail_total'] ?: 0;
                    $service_details_list[] = ['nama_barang' => $row_detail['nama_barang'], 'jenis_jasa' => $row_detail['jenis_jasa'], 'detail_kerusakan_deskripsi' => $row_detail['detail_kerusakan_deskripsi'], 'detail_total' => $current_detail_total];
                    $total_biaya_aktual_dari_detail += $current_detail_total;
                }
                $stmt_detail->close();

                $sql_transaksi = "SELECT id_transaksi, status FROM transaksi WHERE id_service = ? ORDER BY id_transaksi DESC";
                $stmt_transaksi = $koneksi->prepare($sql_transaksi);
                $stmt_transaksi->bind_param("s", $id_service_input);
                $stmt_transaksi->execute();
                $hasil_transaksi = $stmt_transaksi->get_result();
                $list_id_transaksi = [];
                $status_pembayaran = 'Menunggu Pembayaran';
                $first_row = true;
                while ($row_transaksi = $hasil_transaksi->fetch_assoc()) {
                    $list_id_transaksi[] = $row_transaksi['id_transaksi'];
                    if ($first_row) {
                        $status_pembayaran = $row_transaksi['status'];
                        $first_row = false;
                    }
                }
                $stmt_transaksi->close();

                $total_bayar = 0;
                if (!empty($list_id_transaksi)) {
                    $placeholders = implode(',', array_fill(0, count($list_id_transaksi), '?'));
                    $sql_total_bayar = "SELECT SUM(jumlah) as total FROM bayar WHERE id_transaksi IN ($placeholders)";
                    $stmt_total_bayar = $koneksi->prepare($sql_total_bayar);
                    if ($stmt_total_bayar) {
                        $types = str_repeat('i', count($list_id_transaksi));
                        $stmt_total_bayar->bind_param($types, ...$list_id_transaksi);
                        $stmt_total_bayar->execute();
                        $result_total = $stmt_total_bayar->get_result();
                        if ($result_total && $row_total = $result_total->fetch_assoc()) {
                            $total_bayar = $row_total['total'] ?: 0;
                        }
                        $stmt_total_bayar->close();
                    }
                }

                // Tentukan status pembayaran berdasarkan total pembayaran vs total tagihan
                if ($total_bayar >= $total_biaya_aktual_dari_detail && $total_biaya_aktual_dari_detail > 0) {
                    $status_pembayaran = 'Lunas';
                } elseif ($total_bayar > 0) {
                    $status_pembayaran = 'DP';
                } else {
                    $status_pembayaran = 'Menunggu Pembayaran';
                }

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
            <a class="navbar-brand d-flex align-items-center" href="#"><img src="icons/logo.png" alt="logo Thar'z Computer" class="logo-img"><span class="company-name-header">THAR'Z COMPUTER</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="service.php"><i class="fas fa-desktop"></i> Pengajuan Service</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="tracking.php"><i class="fas fa-search-location"></i> Tracking Service</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home"></i> Kembali ke Beranda</a></li>
                </ul>
            </div>
            <div class="d-flex align-items-center"><span class="text-dark fw-semibold"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($namaAkun); ?></span></div>
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
                    <form method="POST" action="tracking.php">
                        <div class="mb-3">
                            <label for="id_service" class="form-label">ID Service:</label>
                            <input type="text" class="form-control" name="id_service" id="id_service" value="<?php echo isset($id_service_input) ? htmlspecialchars($id_service_input) : ''; ?>" placeholder="Masukkan ID Service Anda" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Tracking</button>
                    </form>
                </div>
            </div>

            <?php if ($error_message): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($konfirmasi_message): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $konfirmasi_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($konfirmasi_error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $konfirmasi_error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

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
                        <div class="detail-label">Keluhan Utama:</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($service_info['keluhan'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Diagnosa Kerusakan:</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($service_info['kerusakan'])); ?></div>
                    </div>
                    <div class="status-box" style="padding: 10px; margin-top: 15px; margin-bottom: 15px;">
                        <div class="status-title" style="font-size: 1rem;">Status Service</div>
                            <div class="status-value" style="font-size: 1.2rem;">
                            <?php echo htmlspecialchars($service_info['status']); ?>
                            <?php if (strtolower($service_info['status']) == 'diajukan' && !empty($service_info['tanggal'])): ?>
                                <br><span id="countdown-waktu" style="font-size:0.95rem;color:#dc3545;"></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (strtolower($service_info['status']) == 'menunggu konfirmasi'): ?>
                        <div class="card mt-4 mb-4 border-warning shadow">
                            <div class="card-header bg-warning text-dark">
                                <strong><i class="fas fa-exclamation-triangle"></i> Konfirmasi Service</strong>
                            </div>
                            <div class="card-body">
                                <p>Service Anda sedang menunggu konfirmasi. Silakan konfirmasi untuk melanjutkan proses service.</p>
                                <form method="POST" action="tracking.php">
                                    <input type="hidden" name="konfirmasi_servis" value="1">
                                    <input type="hidden" name="id_service_konfirmasi" value="<?php echo htmlspecialchars($service_info['id_service']); ?>">
                                    <button type="submit" name="aksi" value="setuju" class="btn btn-success me-2">
                                        <i class="fas fa-check"></i> Konfirmasi Service
                                    </button>
                                    <button type="submit" name="aksi" value="batal" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan service ini?');">
                                        <i class="fas fa-times"></i> Batalkan Service
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="status-box" style="background-color: #fff3cd; border-color: #ffeeba; padding: 10px; margin-top: 15px; margin-bottom: 15px;">
                        <div class="status-title" style="font-size: 1rem;">Status Pembayaran</div>
                        <div class="status-value" style="font-size: 1.2rem; color: <?php echo $service_info['status_pembayaran'] == 'Lunas' ? '#28a745' : ($service_info['status_pembayaran'] == 'DP' ? '#ffc107' : '#dc3545'); ?>;">
                            <?php echo htmlspecialchars($service_info['status_pembayaran']); ?>
                        </div>
                        <?php if ($service_info['status_pembayaran'] != 'MENUNGGU PEMBAYARAN'): ?>
                            <div style="margin-top: 5px; font-size: 0.9rem; color: #666;">Jumlah yang sudah dibayar: Rp <?php echo number_format($service_info['jumlah_bayar'], 0, ',', '.'); ?></div>
                        <?php endif; ?>
                    </div>

                    <?php
                    // --- RINCIAN PEMBAYARAN ---
                    $riwayat_pembayaran = [];
                    if (!empty($list_id_transaksi)) {
                        $placeholders = implode(',', array_fill(0, count($list_id_transaksi), '?'));
                        $sql_riwayat = "SELECT * FROM bayar WHERE id_transaksi IN ($placeholders) ORDER BY tanggal ASC";
                        $stmt_riwayat = $koneksi->prepare($sql_riwayat);
                        if ($stmt_riwayat) {
                            $types = str_repeat('i', count($list_id_transaksi));
                            $stmt_riwayat->bind_param($types, ...$list_id_transaksi);
                            $stmt_riwayat->execute();
                            $result_riwayat = $stmt_riwayat->get_result();
                            while ($row_bayar = $result_riwayat->fetch_assoc()) {
                                $riwayat_pembayaran[] = $row_bayar;
                            }
                            $stmt_riwayat->close();
                        }
                    }
                    ?>
                    <div class="card mt-3 mb-3">
                        <div class="card-header bg-light"><strong>Rincian Pembayaran</strong></div>
                        <div class="card-body p-2">
                            <?php if (!empty($riwayat_pembayaran)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="white-space:nowrap">Tanggal</th>
                                                <th>Jumlah</th>
                                                <th>Metode</th>
                                                <th>Status</th>
                                                <th>Bukti</th>
                                                <th>Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($riwayat_pembayaran as $bayar): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($bayar['tanggal']); ?></td>
                                                    <td>Rp <?php echo number_format($bayar['jumlah'], 0, ',', '.'); ?></td>
                                                    <td><?php echo htmlspecialchars($bayar['metode']); ?></td>
                                                    <td><?php echo htmlspecialchars($bayar['status']); ?></td>
                                                    <td>
                                                        <?php if (!empty($bayar['bukti'])): ?>
                                                            <a href="<?php echo htmlspecialchars($bayar['bukti']); ?>" target="_blank">Lihat</a>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($bayar['catatan']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">Belum ada pembayaran yang tercatat.</div>
                            <?php endif; ?>
                        </div>
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
                                <?php if (count($service_details_list) > 1) : ?><strong class="item-title">Rincian Item <?php echo $index + 1; ?>:</strong><?php endif; ?>
                                <?php if (!empty($detail['nama_barang'])): ?><div class="detail-row">
                                        <div class="detail-label">Sparepart:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($detail['nama_barang']); ?></div>
                                    </div><?php endif; ?>
                                <?php if (!empty($detail['jenis_jasa'])): ?><div class="detail-row">
                                        <div class="detail-label">Jasa Tambahan:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($detail['jenis_jasa']); ?></div>
                                    </div><?php endif; ?>
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
                    <?php elseif (isset($id_service_input) && $service_info): ?>
                        <p style="margin-top:20px; color: #555;">Belum ada rincian pengerjaan spesifik.</p>
                        <div class="total-aktual-box">
                            <div class="detail-row">
                                <div class="detail-label">TOTAL TAGIHAN:</div>
                                <div class="detail-value">Rp <?php echo number_format($total_biaya_aktual_dari_detail, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $jumlah_final_untuk_dibayar = $total_biaya_aktual_dari_detail;
                    if (
                        $service_info && $jumlah_final_untuk_dibayar > 0 &&
                        (
                            strtolower($service_info['status']) == 'selesai' ||
                            strtolower($service_info['status']) == 'diperbaiki' ||
                            strtolower($service_info['status']) == 'siap diambil' ||
                            (strtolower($service_info['status']) == 'dikonfirmasi' && $service_info['konfirmasi'] == 1)
                        ) &&
                        strtolower($service_info['status_pembayaran']) != 'lunas'
                    ) :
                    ?>
                        <div class="detail-row" style="margin-top:25px;">
                            <button type="button" onclick="bayar('<?php echo htmlspecialchars($service_info['id_service']); ?>', <?php echo $jumlah_final_untuk_dibayar; ?>)" class="btn btn-bayar">Bayar Sekarang</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <footer class="mt-auto p-4 border-top text-center text-muted small">&copy; Tharz Computer 2025</footer>
    </div>
    <?php
        // Letakkan penutup koneksi di sini, setelah semua query selesai
    if (isset($koneksi)) {
        $koneksi->close();
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function bayar(idService, amountToPay) {
            window.location.href = 'transaksi_service.php?id_service=' + encodeURIComponent(idService) + '&amount=' + amountToPay;
        }

        <?php if (strtolower($service_info['status']) == 'diajukan' && !empty($service_info['tanggal'])): ?>
            var waktuDiajukan = <?php echo strtotime($service_info['tanggal']) * 1000; ?>;
            var batas = 2 * 60 * 60 * 1000; // 2 jam dalam ms
            var waktuAkhir = waktuDiajukan + batas;
            var countdownEl = document.getElementById('countdown-waktu');
            function updateCountdown() {
                var now = new Date().getTime();
                var sisa = waktuAkhir - now;
                if (sisa > 0) {
                    var jam = Math.floor(sisa / (1000 * 60 * 60));
                    var menit = Math.floor((sisa % (1000 * 60 * 60)) / (1000 * 60));
                    var detik = Math.floor((sisa % (1000 * 60)) / 1000);
                    countdownEl.innerHTML = 'Harap datang ke konter sebelum: ' + jam + ' jam ' + menit + ' menit ';
                } else {
                    countdownEl.innerHTML = 'Waktu habis, data akan dihapus.';
                }
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // Polling AJAX untuk update status service
        var idService = '<?php echo $service_info['id_service']; ?>';
        function pollingStatus() {
            $.get('tracking_status_api.php', {id_service: idService}, function(data) {
                if (data && data.status) {
                    var statusBar = document.querySelector('.status-value');
                    if (statusBar) {
                        statusBar.innerHTML = data.status;
                        // Jika status berubah dari diajukan ke status lain, hapus countdown
                        if (data.status.toLowerCase() !== 'diajukan') {
                            var countdown = document.getElementById('countdown-waktu');
                            if (countdown) countdown.style.display = 'none';
                        } else if (data.tanggal) {
                            // Jika status tetap diajukan, update countdown dengan waktu baru jika ada perubahan
                            waktuDiajukan = parseInt(data.tanggal) * 1000;
                            waktuAkhir = waktuDiajukan + batas;
                            var countdown = document.getElementById('countdown-waktu');
                            if (countdown) countdown.style.display = '';
                        }
                    }
                }
            }, 'json');
        }
        setInterval(pollingStatus, 10000); // polling setiap 10 detik
    </script>
</body>

</html>