<?php
$koneksi = mysqli_connect("localhost", "root", "", "tharz_computer");

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
