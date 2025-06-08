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
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Thar'z Computer - Tracking Service</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="icons/logo.png" alt="Logo Thar'z">
            </div>
            <div class="company-name">Thar'z Computer</div>
        </div>

        <div class="menu">
            <div class="menu-item active">Tracking</div>
            <div class="menu-item">Garansi</div>
        </div>

        <div class="form-title">Masukkan ID Service yang sudah diberikan:</div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="id_service">ID Service:</label>
                <input type="text" name="id_service" id="id_service" value="<?php echo isset($_POST['id_service']) ? htmlspecialchars($_POST['id_service']) : ''; ?>" required>
            </div>
            <button type="submit" class="btn">Tracking</button>
        </form>

        <a href="index.php" class="back-link">← Kembali ke Beranda</a>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
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

                    <?php // Tampilkan Total Biaya Aktual dari Detail jika ada detail 
                    ?>
                    <div class="total-aktual-box">
                        <div class="detail-row">
                            <div class="detail-label">TOTAL TAGIHAN:</div>
                            <div class="detail-value">Rp <?php echo number_format($total_biaya_aktual_dari_detail, 0, ',', '.'); ?></div>
                        </div>
                    </div>

                <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $service_info): ?>
                    <p style="margin-top:20px; color: #555;">Belum ada rincian pengerjaan spesifik (sparepart atau jasa tambahan) yang dicatat untuk service ini.</p>
                    <?php // Jika tidak ada detail, total tagihan aktual adalah 0 berdasarkan $total_biaya_aktual_dari_detail yang diinisialisasi 0 
                    ?>
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
                        <button type="button" onclick="bayar('<?php echo htmlspecialchars($service_info['id_service']); ?>', <?php echo $jumlah_final_untuk_dibayar; ?>)" class="btn">Bayar Sekarang</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
    <script>
        function bayar(idService, amountToPay) {
            // Mengarahkan ke halaman transaksi dengan ID service dan jumlah yang harus dibayar.
            window.location.href = 'transaksi_service.php?id_service=' + encodeURIComponent(idService) + '&amount=' + amountToPay;
        }
    </script>
</body>

</html>