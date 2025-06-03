<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

// Cek role harus owner
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: index.php");
    exit();
}

$edit = null;
$pesan = '';

// Pastikan ada ID yang dikirim untuk mode edit
if (!isset($_GET['id'])) {
    header("Location: manajemen_akun.php"); // Jika tidak ada ID, kembalikan ke halaman manajemen
    exit();
}

$id = $_GET['id'];

// Proses Update Akun
if (isset($_POST['update'])) {
    $username = $_POST['username'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = $koneksi->prepare("UPDATE user SET username=?, password=?, role=? WHERE id_user=?");
        $sql->bind_param("sssi", $username, $hashed, $role, $id);
    } else {
        $sql = $koneksi->prepare("UPDATE user SET username=?, role=? WHERE id_user=?");
        $sql->bind_param("ssi", $username, $role, $id);
    }

    if ($sql->execute()) {
        $pesan = "Akun berhasil diupdate!";
        // Setelah update, ambil kembali data terbaru untuk ditampilkan di form
        $get = $koneksi->prepare("SELECT * FROM user WHERE id_user= ?");
        $get->bind_param("i", $id);
        $get->execute();
        $edit = $get->get_result()->fetch_assoc();
    } else {
        $pesan = "Gagal mengupdate akun.";
    }
}

// Ambil data akun yang akan diedit saat halaman pertama kali dimuat
$get = $koneksi->prepare("SELECT * FROM user WHERE id_user = ?");
$get->bind_param("i", $id);
$get->execute();
$edit = $get->get_result()->fetch_assoc();

// Jika akun tidak ditemukan (misal ID di URL salah)
if (!$edit) {
    header("Location: manajemen_akun.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Akun</title>
    <style>
        body {
            background: linear-gradient(to bottom, #a1c4fd, #ffffff);
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input, select {
            padding: 8px;
            width: 100%;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
            display: inline-block;
            margin-right: 10px;
        }
        .submit { background: #0a1a47; color: white; }
        .back { background: #6c757d; color: white; text-decoration: none; }
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
    <h2>Edit Akun</h2>
    <?php if ($pesan) echo "<p class='message'><strong>$pesan</strong></p>"; ?>
    <form method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id_user']) ?>">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required value="<?= htmlspecialchars($edit['username']) ?>">

        <label for="password">Password (Kosongkan jika tidak ingin diubah):</label>
        <input type="password" id="password" name="password">

        <label for="role">Role:</label>
        <select id="role" name="role" required>
            <option value="admin" <?= ($edit['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
            <option value="teknisi" <?= ($edit['role'] == 'teknisi') ? 'selected' : '' ?>>Teknisi</option>
            <?php if ($edit['role'] == 'owner'): // Biarkan owner tetap owner, tidak bisa diubah ?>
                <option value="owner" selected>Owner</option>
            <?php endif; ?>
        </select>

        <button type="submit" class="btn submit" name="update">Update Akun</button>
        <a href="register.php" class="btn back">Kembali</a>
    </form>
</div>
</body>
</html>