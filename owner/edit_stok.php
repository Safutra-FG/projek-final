<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$pesan = ''; // Variabel untuk menyimpan pesan notifikasi

// Ambil data barang berdasarkan ID
if (isset($_GET['id'])) {
    $id_barang = $_GET['id'];
    $result = $koneksi->query("SELECT * FROM stok WHERE id_barang = $id_barang");
    $row = $result->fetch_assoc();

    // Jika data tidak ditemukan
    if (!$row) {
        $pesan = "<div class='alert alert-danger' role='alert'>Data barang tidak ditemukan.</div>";
        // Anda mungkin ingin mengarahkan pengguna kembali ke halaman daftar stok
        // header("Location: stok.php");
        // exit();
    }
} else {
    $pesan = "<div class='alert alert-danger' role='alert'>ID barang tidak valid.</div>";
    // header("Location: stok.php");
    // exit();
}

// Proses update data
if (isset($_POST['update'])) {
    $nama = trim($_POST['nama_barang']);
    $stok = $_POST['stok'];
    $harga = $_POST['harga'];

    // Validasi input
    if (empty($nama)) {
        $pesan = "<div class='alert alert-danger' role='alert'>Nama barang tidak boleh kosong!</div>";
    } elseif (!is_numeric($stok) || $stok < 0) {
        $pesan = "<div class='alert alert-danger' role='alert'>Stok harus berupa angka positif!</div>";
    } elseif (!is_numeric($harga) || $harga < 0) {
        $pesan = "<div class='alert alert-danger' role='alert'>Harga harus berupa angka positif!</div>";
    } else {
        $stmt = $koneksi->prepare("UPDATE stok SET nama_barang = ?, stok = ?, harga = ? WHERE id_barang = ?");
        $stmt->bind_param("siid", $nama, $stok, $harga, $id_barang);

        if ($stmt->execute()) {
             $pesan = "<div class='alert alert-success' role='alert'>Barang berhasil diupdate! <a href='stok.php'>Kembali ke Daftar Stok</a></div>";
        } else {
            $pesan = "<div class='alert alert-danger' role='alert'>Gagal mengupdate barang: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stok Barang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body {
            background: linear-gradient(to bottom, #a1c4fd, #ffffff);
            min-height: 100vh;
            padding-top: 30px;
            padding-bottom: 30px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Edit Barang</h2>

        <?php echo $pesan; ?>

        <?php if (isset($row)): ?>
            <form method="POST" action="" class="mb-4">
                <div class="mb-3">
                    <label for="nama_barang" class="form-label">Nama Barang</label>
                    <input type="text" class="form-control" id="nama_barang" name="nama_barang" value="<?= htmlspecialchars($row['nama_barang']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="stok" class="form-label">Stok</label>
                    <input type="number" class="form-control" id="stok" name="stok" value="<?= htmlspecialchars($row['stok']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="harga" class="form-label">Harga</label>
                    <input type="number" class="form-control" id="harga" name="harga" step="0.01" value="<?= htmlspecialchars($row['harga']) ?>" required>
                </div>
                <button type="submit" name="update" class="btn btn-primary">Update Barang</button>
                <a href="stok.php" class="btn btn-secondary">Batal</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

<?php $koneksi->close(); ?>