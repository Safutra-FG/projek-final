<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Cek role harus owner
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php"); // Pastikan path ke index.php benar
    exit();
}

$pesan = ''; // Inisialisasi $pesan di awal
$namaAkun = "Owner"; // Mengatur nama akun sebagai Owner

// Variabel pencarian (search_username) DIHAPUS dari sini
// $search_username = isset($_GET['search_username']) ? $koneksi->real_escape_string($_GET['search_username']) : '';

// Proses Tambah Akun
if (isset($_POST['tambah'])) {
    $username = trim($_POST['username']);
    $password_input = $_POST['password']; // Ambil password mentah
    $role = $_POST['role'];

    // Validasi input tambah akun
    if (empty($username)) {
        $pesan = "<div class='alert alert-danger' role='alert'>Nama pengguna tidak boleh kosong!</div>";
    } elseif (empty($password_input)) {
        $pesan = "<div class='alert alert-danger' role='alert'>Password tidak boleh kosong!</div>";
    } else {
        $password_hashed = password_hash($password_input, PASSWORD_DEFAULT); // Hash password

        $cek = $koneksi->prepare("SELECT * FROM user WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        $cek_result = $cek->get_result();

        if ($cek_result->num_rows > 0) {
            $pesan = "<div class='alert alert-warning' role='alert'>Nama pengguna sudah digunakan!</div>";
        } else {
            $stmt = $koneksi->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password_hashed, $role);
            if ($stmt->execute()) {
                $pesan = "<div class='alert alert-success' role='alert'>Akun berhasil dibuat!</div>";
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=success_add");
                exit();
            } else {
                $pesan = "<div class='alert alert-danger' role='alert'>Gagal membuat akun: " . $stmt->error . "</div>";
            }
        }
        $cek->close();
    }
}

// Proses Hapus Akun
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $koneksi->prepare("DELETE FROM user WHERE id_user = ? AND role IN ('admin', 'teknisi')");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $pesan = "<div class='alert alert-success' role='alert'>Akun berhasil dihapus!</div>";
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success_delete");
        exit();
    } else {
        $pesan = "<div class='alert alert-danger' role='alert'>Gagal menghapus akun: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Mengambil semua data user TANPA filter pencarian
// $where_clause_user DIHAPUS dari sini
$sqlUsers = "SELECT
                id_user,
                username,
                role
              FROM
                user
              ORDER BY
                username ASC";

$resultUsers = $koneksi->query($sqlUsers);

// Mengambil pesan dari URL setelah redirect (misal dari tambah/hapus)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_add') {
        $pesan = "<div class='alert alert-success' role='alert'>Akun berhasil dibuat!</div>";
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan = "<div class='alert alert-success' role='alert'>Akun berhasil dihapus!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Akun - Thar'z Computer</title>
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

        /* Custom styles for Edit/Delete buttons to match the image */
        .btn.edit, .btn.delete {
            padding: .25rem .75rem; /* Menyesuaikan padding agar tidak terlalu besar */
            font-size: .875rem; /* Ukuran font lebih kecil */
            border-radius: .3rem; /* Membulatkan sudut sedikit, sesuaikan jika perlu */
            box-shadow: none; /* Menghilangkan shadow default Bootstrap jika ada */
            display: inline-block; /* Penting untuk margin antar tombol */
            margin: 0 .15rem; /* Sedikit margin antar tombol */
        }

        .btn.edit {
            background-color: #ffc107; /* Kuning */
            border-color: #ffc107;
            color: #212529; /* Teks hitam/gelap */
        }

        .btn.edit:hover {
            background-color: #e0a800; /* Kuning lebih gelap saat hover */
            border-color: #e0a800;
        }

        .btn.delete {
            background-color: #dc3545; /* Merah */
            border-color: #dc3545;
            color: #fff; /* Teks putih */
        }

        .btn.delete:hover {
            background-color: #c82333; /* Merah lebih gelap saat hover */
            border-color: #c82333;
        }

        /* Style untuk tombol Owner yang tidak bisa dihapus */
        .btn.delete.owner-disabled { /* Gunakan kelas khusus untuk owner */
            background-color: #e0e0e0 !important; /* Pastikan override */
            border-color: #e0e0e0 !important;
            color: #6c757d !important;
            cursor: not-allowed !important;
            padding: .25rem .75rem;
            font-size: .875rem;
            border-radius: .3rem;
            pointer-events: none; /* Mencegah klik */
        }
    </style>
</head>

<body>
    <div></div>
    <div class="sidebar">
        <div class="logo text-center mb-4">
            <img src="../icons/logo.png" alt="logo Thar'z Computer" class="logo-img">
            <h1 class="h4 text-dark mt-2 fw-bold">Thar'z Computer</h1>
            <p class="text-muted small">Owner Panel</p>
            <div class="logo-line"></div>
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
                <a class="nav-link active" href="register.php">
                    <i class="fas fa-users"></i>Kelola Akun
                </a>
            <li class="nav-item">
                <a class="nav-link" href="stok.php">
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
            <h2 class="h4 text-dark mb-0">Kelola Akun</h2>
            <div class="d-flex align-items-center">
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
                Tambah Akun Baru
            </div>
            <div class="card-body">
                <?php if ($pesan) echo "<p class='message'><strong>$pesan</strong></p>"; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="username_tambah" class="form-label">Username:</label>
                        <input type="text" class="form-control" placeholder="Masukan Username!" id="username_tambah" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_tambah" class="form-label">Password:</label>
                        <input type="password" class="form-control" placeholder="Masukan Password!" id="password_tambah" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role_tambah">Role:</label>
                        <select class="form-control" id="role_tambah" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="teknisi">Teknisi</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary submit" name="tambah">Buat Akun</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold fs-5 border-bottom">
                Daftar Akun
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>Username</th>
                                <th class="text-center">Role</th>
                                <th class="text-center">Aksi</th> </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            // Query mengambil semua data tanpa filter pencarian
                            if ($resultUsers && $resultUsers->num_rows > 0):
                                while ($row = $resultUsers->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['username']) ?></td>
                                        <td class="text-center"><?= $row['role'] ?></td>
                                        <td class="text-center"> <a href="edit_akun.php?id=<?= $row['id_user'] ?>" class="btn edit">Edit</a>
                                            <?php if ($row['role'] !== 'owner'): ?>
                                                <a href="?delete=<?= $row['id_user'] ?>" class="btn delete" onclick="return confirm('Yakin hapus akun ini?')">Hapus</a>
                                            <?php else: ?>
                                                <span class="btn delete owner-disabled">Owner</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">Tidak ada data akun.</td>
                                </tr>
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