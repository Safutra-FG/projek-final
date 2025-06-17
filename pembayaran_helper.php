<?php
// Helper pembayaran untuk tracking service

define('STATUS_BELUM_BAYAR', 'Belum Bayar');
define('STATUS_DP', 'DP');
define('STATUS_LUNAS', 'Lunas');

function get_total_bayar($koneksi, $id_transaksi) {
    $total_bayar = 0;
    $riwayat_bayar = [];
    if ($id_transaksi) {
        $sql_bayar = "SELECT * FROM bayar WHERE id_transaksi = ? ORDER BY tanggal ASC";
        $stmt_bayar = $koneksi->prepare($sql_bayar);
        $stmt_bayar->bind_param("i", $id_transaksi);
        $stmt_bayar->execute();
        $result_bayar = $stmt_bayar->get_result();
        while ($row_bayar = $result_bayar->fetch_assoc()) {
            $riwayat_bayar[] = $row_bayar;
            $total_bayar += $row_bayar['jumlah'];
        }
        $stmt_bayar->close();
    }
    return [$total_bayar, $riwayat_bayar];
}

function get_status_pembayaran($total_bayar, $total_tagihan) {
    if ($total_bayar > 0) {
        if ($total_tagihan > 0 && $total_bayar >= $total_tagihan) {
            return STATUS_LUNAS;
        } else {
            return STATUS_DP;
        }
    } else {
        return STATUS_BELUM_BAYAR;
    }
}

// Fungsi baru untuk memperbaiki status transaksi
function fix_transaction_status($koneksi, $id_service) {
    // Ambil total tagihan dari detail_service
    $sql_tagihan = "SELECT COALESCE(SUM(ds.total), 0) as total_tagihan 
                   FROM service s 
                   LEFT JOIN detail_service ds ON s.id_service = ds.id_service 
                   WHERE s.id_service = ?";
    $stmt_tagihan = $koneksi->prepare($sql_tagihan);
    $stmt_tagihan->bind_param("s", $id_service);
    $stmt_tagihan->execute();
    $result_tagihan = $stmt_tagihan->get_result();
    $total_tagihan = 0;
    if ($row_tagihan = $result_tagihan->fetch_assoc()) {
        $total_tagihan = $row_tagihan['total_tagihan'];
    }
    $stmt_tagihan->close();

    // Ambil semua transaksi untuk service ini
    $sql_transaksi = "SELECT id_transaksi FROM transaksi WHERE id_service = ? AND jenis = 'service'";
    $stmt_transaksi = $koneksi->prepare($sql_transaksi);
    $stmt_transaksi->bind_param("s", $id_service);
    $stmt_transaksi->execute();
    $result_transaksi = $stmt_transaksi->get_result();
    
    while ($row_transaksi = $result_transaksi->fetch_assoc()) {
        $id_transaksi = $row_transaksi['id_transaksi'];
        
        // Hitung total pembayaran untuk transaksi ini
        $sql_total_bayar = "SELECT COALESCE(SUM(b.jumlah), 0) as total_bayar 
                           FROM bayar b 
                           WHERE b.id_transaksi = ?";
        $stmt_total = $koneksi->prepare($sql_total_bayar);
        $stmt_total->bind_param("i", $id_transaksi);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $total_bayar = 0;
        if ($row_total = $result_total->fetch_assoc()) {
            $total_bayar = $row_total['total_bayar'];
        }
        $stmt_total->close();

        // Tentukan status baru
        $status_baru = 'menunggu pembayaran';
        if ($total_bayar >= $total_tagihan && $total_tagihan > 0) {
            $status_baru = 'lunas';
        } elseif ($total_bayar > 0) {
            $status_baru = 'dp';
        }

        // Update status transaksi
        $sql_update = "UPDATE transaksi SET status = ? WHERE id_transaksi = ?";
        $stmt_update = $koneksi->prepare($sql_update);
        $stmt_update->bind_param("si", $status_baru, $id_transaksi);
        $stmt_update->execute();
        $stmt_update->close();
    }
    $stmt_transaksi->close();
    
    return true;
} 