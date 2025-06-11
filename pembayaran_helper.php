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