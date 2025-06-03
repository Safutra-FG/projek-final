<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$pesan = ''; // Variabel untuk menyimpan pesan notifikasi

// Proses form tambah barang
if (isset($_POST['submit'])) {
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
        $stmt = $koneksi->prepare("INSERT INTO stok (nama_barang, stok, harga) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama, $stok, $harga);

        if ($stmt->execute()) {
            $pesan = "<div class='alert alert-success' role='alert'>Barang berhasil ditambahkan!</div>";
            $_POST = array(); // Kosongkan nilai POST agar form kembali kosong
        } else {
            $pesan = "<div class='alert alert-danger' role='alert'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Proses hapus barang
if (isset($_GET['hapus'])) {
    $id_barang = $_GET['hapus'];
    $stmt = $koneksi->prepare("DELETE FROM stok WHERE id_barang = ?");
    $stmt->bind_param("i", $id_barang);
    if ($stmt->execute()) {
        $pesan = "<div class='alert alert-success' role='alert'>Barang berhasil dihapus!</div>";
    } else {
        $pesan = "<div class='alert alert-danger' role='alert'>Gagal menghapus barang: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Ambil semua data barang
$result = $koneksi->query("SELECT * FROM stok ORDER BY id_barang ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Barang</title>
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
        .table thead th {
            background-color: #0a1a47;
            color: white;
        }
        .btn-action {
            margin-right: 5px;
        }
        /* Tambahan gaya untuk tombol di dalam form */
        .form-buttons {
            display: flex; /* Menggunakan flexbox untuk penataan */
            gap: 10px;    /* Jarak antar tombol */
            margin-top: 20px; /* Jarak dari input di atasnya */
        }
        .form-buttons .btn {
            flex-grow: 1; /* Agar tombol mengisi ruang yang tersedia */
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mb-4">Tambah Barang Baru</h2>

    <?php echo $pesan; ?>

    <form method="POST" action="" class="mb-5">
        <div class="mb-3">
            <label for="nama_barang" class="form-label">Nama Barang</label>
            <input type="text" class="form-control" id="nama_barang" name="nama_barang" placeholder="Masukkan Nama Barang" value="<?= htmlspecialchars($_POST['nama_barang'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="stok" class="form-label">Stok</label>
            <input type="number" class="form-control" id="stok" name="stok" placeholder="Masukkan Jumlah Stok" min="0" value="<?= htmlspecialchars($_POST['stok'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="harga" class="form-label">Harga</label>
            <input type="number" class="form-control" id="harga" name="harga" placeholder="Masukkan Harga Barang" step="0.01" min="0" value="<?= htmlspecialchars($_POST['harga'] ?? '') ?>" required>
        </div>

        <div class="form-buttons">
            <button type="submit" name="submit" class="btn btn-primary">Tambah Barang</button>
            <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
        </div>
    </form>

    <h2 class="mt-5 mb-3">Daftar Stok Barang</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Stok</th>
                    <th>Harga</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_barang']) ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($row['stok']) ?></td>
                            <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                            <td>
                                <a href="edit_stok.php?id=<?= $row['id_barang'] ?>" class="btn btn-warning btn-sm btn-action">Edit</a>
                                <a href="?hapus=<?= $row['id_barang'] ?>" class="btn btn-danger btn-sm btn-action" onclick="return confirm('Yakin ingin menghapus barang ini?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">Belum ada data barang</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php $koneksi->close(); ?>