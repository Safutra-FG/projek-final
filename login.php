<?php
session_start();
$koneksi = new mysqli("localhost", "root", "", "tharz_computer");

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Ambil data user berdasarkan username aja
    $stmt = $koneksi->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data && password_verify($password, $data['password'])) {
        // Login berhasil
        $_SESSION['username'] = $data['username'];
        $_SESSION['role'] = $data['role'];
        $_SESSION['id_user'] = $data['id_user'];

        // Arahkan user berdasarkan role-nya
        switch ($data['role']) {
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            case 'teknisi':
                header("Location: teknisi/dashboard.php");
                break;
            case 'owner':
                header("Location: owner/dashboard.php");
                break;
            default:
                header("Location: index.php");
                break;
        }
        exit();
    } else {
        echo "<script>alert('Login gagal! Username atau password salah');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login User</title>
        <style>
        body {
            font-family: sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            text-align: center;
            width: 300px;
        }

        input[type="text"],
        input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #aaa;
            border-radius: 4px;
        }

        button {
            padding: 10px 25px;
            background-color: #0c1c4b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        a {
            display: block;
            margin-top: 15px;
            text-decoration: none;
            color: #0c1c4b;
        }

        .error {
            color: red;
            margin-top: 10px;
        }

        h2 {
            margin-bottom: 20px;
        }

        
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: rgb(29, 95, 171);
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-box">
    <h2>Login</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="masukan username" required><br>
        <input type="password" name="password" placeholder="masukan password"><br><br>
        <button type="submit" name="login">Login</button><br>
        <a href="index.php" class="back-link">← Kembali ke Beranda</a>
    </form>
    </div>
</body>
</html>
