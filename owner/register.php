<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek role harus owner
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: index.php");
    exit();
}

$pesan = '';

// Proses Tambah Akun (TETAP di sini)
if (isset($_POST['tambah'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $cek = $koneksi->prepare("SELECT * FROM user WHERE username = ?");
    $cek->bind_param("s", $username);
    $cek->execute();
    $cek_result = $cek->get_result();

    if ($cek_result->num_rows > 0) {
        $pesan = "Username sudah digunakan!";
    } else {
        $stmt = $koneksi->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $role);
        if ($stmt->execute()) {
            $pesan = "Akun berhasil dibuat!";
        } else {
            $pesan = "Gagal membuat akun.";
        }
    }
}

// Proses Hapus Akun (TETAP di sini)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $koneksi->prepare("DELETE FROM user WHERE id_user = ? AND role IN ('admin', 'teknisi')");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $pesan = "Akun berhasil dihapus!";
    } else {
        $pesan = "Gagal menghapus akun.";
    }
    header("Location: " . $_SERVER['PHP_SELF']); // Redirect untuk menghilangkan parameter delete dari URL
    exit();
}

// Ambil data user untuk ditampilkan di tabel (termasuk owner untuk owner itu sendiri)
$result = $koneksi->query("SELECT * FROM user");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manajemen Akun</title>
    <style>
        body {
            background: linear-gradient(to bottom, #a1c4fd, #ffffff);
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .back-to-home { /* Gaya baru untuk tombol kembali ke beranda */
            background: #6c757d; /* Warna abu-abu */
            color: white;
            margin-top: 10px; /* Jarak dari tombol Buat Akun */
        }

        table {
            width: 100%; border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd; padding: 10px; text-align: center;
        }
        
        th {
            background-color: #0a1a47; color: white;
        }

        input, select {
            padding: 8px; width: 100%; margin-bottom: 10px;
            box-sizing: border-box; /* Agar padding tidak melebihi width */
        }

        .btn {
            padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }

        .edit { background: #ffc107; }
        .delete { background: #dc3545; color: white; }
        .submit { background: #0a1a47; color: white; }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Tambah Akun Baru</h2>
    <?php if ($pesan) echo "<p class='message'><strong>$pesan</strong></p>"; ?>
    <form method="POST">
        <label for="username_tambah">Username:</label>
        <input type="text" id="username_tambah" name="username" required>

        <label for="password_tambah">Password:</label>
        <input type="password" id="password_tambah" name="password" required>

        <label for="role_tambah">Role:</label>
        <select id="role_tambah" name="role" required>
            <option value="admin">Admin</option>
            <option value="teknisi">Teknisi</option>
        </select>

        <button type="submit" class="btn submit" name="tambah">Buat Akun</button>
        <a href="dashboard.php" class="btn back-to-home">Kembali ke Beranda</a>
    </form>

    <h2>Daftar Akun</h2>
    <table>
        <tr><th>No</th><th>Username</th><th>Role</th><th>Aksi</th></tr>
        <?php $no=1; while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= $row['role'] ?></td>
            <td>
                <?php
                // Tautan edit sekarang mengarah ke edit_akun.php dengan ID akun
                echo '<a href="edit_akun.php?id=' . $row['id_user'] . '" class="btn edit">Edit</a>';

                // Tombol hapus hanya muncul jika role-nya bukan 'owner'
                if ($row['role'] !== 'owner') {
                    echo ' <a href="?delete=' . $row['id_user'] . '" class="btn delete" onclick="return confirm(\'Yakin hapus akun ini?\')">Hapus</a>';
                } else {
                    echo ' <span class="btn delete" style="background: #e0e0e0; cursor: not-allowed; color: #666;">❌ Owner</span>';
                }
                ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>