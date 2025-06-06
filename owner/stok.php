<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}
$namaAkun = "Owner"; // Mengatur nama akun sebagai Owner

$pesan = ''; // Inisialisasi $pesan di awal agar tidak undefined

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
            // Kosongkan nilai POST agar form kembali kosong
            $_POST = array();
            // Redirect ke halaman yang sama untuk membersihkan parameter POST dari URL
            header("Location: stok.php"); // Ini akan me-refresh halaman dan menerapkan filter jika ada
            exit();
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
        // Redirect ke halaman yang sama setelah hapus
        header("Location: stok.php");
        exit();
    } else {
        $pesan = "<div class='alert alert-danger' role='alert'>Gagal menghapus barang: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// --- Logika untuk pencarian dan pengambilan data tabel ---
// Ambil nilai dari input pencarian
$search_nama = isset($_GET['search_nama']) ? $koneksi->real_escape_string($_GET['search_nama']) : '';

// Bangun klausa WHERE untuk query
$where_clause_stok = "WHERE 1=1"; // Kondisi awal yang selalu benar
if (!empty($search_nama)) { // Jika ada input pencarian, tambahkan kondisi LIKE
    $where_clause_stok .= " AND nama_barang LIKE '%" . $search_nama . "%'";
}

// Query untuk mengambil data barang (sudah difilter jika ada input pencarian)
$sqlStokBarang = "SELECT
                     id_barang,
                     nama_barang,
                     stok,
                     harga
                   FROM
                     stok
                   " . $where_clause_stok . "
                   ORDER BY
                     nama_barang ASC";

$resultStokBarang = $koneksi->query($sqlStokBarang);

// Jika Anda masih memiliki baris ini, hapus atau komentari.
// $result = $koneksi->query("SELECT * FROM stok ORDER BY id_barang ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Barang - Thar'z Computer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            font-family: sans-serif;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #f8f9fa;
            padding: 20px;
            border-right: 1px solid #dee2e6;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .sidebar .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid #0d6efd;
        }
        .sidebar .logo-line,
        .sidebar .menu-line {
            width: 100%;
            height: 1px;
            background-color: #adb5bd;
            margin: 10px 0;
        }
        .sidebar .nav-link {
            padding: 10px 15px;
            color: #495057;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #007bff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .main-header .d-flex {
                width: 100%;
                justify-content: space-between;
            }
            .main-header .btn {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo text-center mb-4">
            <img src="../icons/logo.png" alt="logo Thar'z Computer" class="logo-img">
            <h1 class="h4 text-dark mt-2 fw-bold">Thar'z Computer</h1>
            <p class="text-muted small">Owner Panel</p> <div class="logo-line"></div>
        </div>

        <h2 class="h5 mb-3 text-dark">Menu</h2>
        <div class="menu-line"></div>
        <ul class="nav flex-column menu">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home"></i>Dashboard
                </a>
            </li>
           <li class="nav-item">
                <a class="nav-link" href="register.php">
                    <i class="fas fa-users"></i>Kelola Akun
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="stok.php">
                    <i class="fas fa-wrench"></i>Kelola Sparepart
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="laporan_keuangan.php">
                    <i class="fas fa-chart-line"></i>Laporan Keuangan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" aria-current="page" href="laporan_sparepart.php">
                    <i class="fas fa-boxes"></i>Laporan Stok Barang
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="laporan_pesanan.php">
                    <i class="fas fa-clipboard-list"></i>Laporan Pesanan
                </a>
            </li>
        </ul>

            <div class="mt-auto p-4 border-top text-center text-muted small">
            &copy; Thar'z Computer 2025
        </div>
    </div>

    <div class="main-content">
        <div class="main-header">
            <h2 class="h4 text-dark mb-0">Kelola Sparepart</h2> <div class="d-flex align-items-center">
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" title="Pemberitahuan">
                    <i class="fas fa-bell"></i>
                </button>
                <span class="text-dark fw-semibold ms-2 me-2">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($namaAkun); ?>
                </span>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-bold fs-5 border-bottom">
                Tambah Barang Baru
            </div>
            <div class="card-body">
                <?php echo $pesan; ?>

                <form method="POST" action="">
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

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">Tambah Barang</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold fs-5 border-bottom">
                Daftar Stok Barang
            </div>
            <div class="card-body">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Cari Barang</h5>
                        <form method="GET" action="stok.php">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6 col-lg-6">
                                    <label for="search_nama" class="form-label">Cari Nama Barang:</label>
                                    <input type="text" class="form-control" id="search_nama" name="search_nama" value="<?php echo htmlspecialchars($search_nama); ?>" placeholder="Cari nama barang...">
                                </div>
                                <div class="col-md-3 col-lg-3">
                                    <button type="submit" class="btn btn-primary w-100">Cari</button>
                                </div>
                                <div class="col-md-3 col-lg-3">
                                    <a href="stok.php" class="btn btn-outline-secondary w-100">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th class="text-center">ID</th>
                                <th>Nama</th>
                                <th class="text-center">Stok</th>
                                <th>Harga</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Gunakan $resultStokBarang untuk menampilkan data
                            if ($resultStokBarang && $resultStokBarang->num_rows > 0): ?>
                                <?php while($row = $resultStokBarang->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center"><?= htmlspecialchars($row['id_barang']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['stok']) ?></td>
                                        <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <a href="edit_stok.php?id=<?= $row['id_barang'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                            <a href="?hapus=<?= $row['id_barang'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus barang ini?')">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">Belum ada data barang atau tidak ditemukan hasil pencarian.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php $koneksi->close(); ?>