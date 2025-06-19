<?php
session_start();
include 'koneksi.php';

// ... (Blok PHP Anda tidak perlu diubah, tetap sama)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
            transition: all 0.3s ease; /* Transisi halus saat ukuran berubah */
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            padding-right: 40px;
            border: 1px solid #aaa;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 10px;
            padding-right: 40px;
            border: 1px solid #aaa;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            padding: 10px 25px;
            background-color: #0c1c4b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }

        a {
            display: block;
            margin-top: 15px;
            text-decoration: none;
            color: #0c1c4b;
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
        
        .password-container {
            position: relative;
            width: 100%;
            margin: 10px auto;
        }

        .password-container i {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
        }

        /* ----- TAMBAHAN UNTUK TAMPILAN MOBILE ----- */
        @media (max-width: 600px) {
            .login-box {
                width: 90%; /* Mengubah lebar menjadi 90% dari lebar layar */
                padding: 25px; /* Sedikit mengurangi padding agar lebih proporsional */
            }

            /* Opsional: Memperbesar sedikit ukuran font pada input dan tombol */
            input[type="text"],
            .password-container input, /* Target input password di dalam container */
            button {
                font-size: 16px;
            }
        }
        /* ----- AKHIR DARI TAMBAHAN ----- */

    </style>
</head>
<body>
    <div class="login-box">
    <h2>Login</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="masukan username" required>
        <div class="password-container">
            <input type="password" name="password" id="passwordInput" placeholder="masukan password" required>
            <i class="fa-solid fa-eye" id="toggleIcon"></i>
        </div>
        <button type="submit" name="login">Login</button><br>
        <a href="index.php" class="back-link">← Kembali ke Beranda</a>
    </form>
    </div>

<script>
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('toggleIcon');

    toggleIcon.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        if (type === 'text') {
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
</script>

</body>
</html>