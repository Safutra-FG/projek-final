<?php
$koneksi = mysqli_connect("localhost", "root", "", "revisi");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
