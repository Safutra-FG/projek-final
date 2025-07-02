<?php

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
include '../koneksi.php';

// Hanya proses jika permintaan adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {

    // Ambil data dari form dengan aman
    $nama = trim($_POST['nama'] ?? '');
    $no_hp = trim($_POST['nomor_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $device = trim($_POST['device'] ?? '');
    $keluhan = trim($_POST['keluhan'] ?? '');

    // 1. Validasi Input
    if (empty($nama) || empty($no_hp) || empty($email) || empty($device) || empty($keluhan)) {
        $_SESSION['alert_message'] = "Semua kolom wajib diisi!";
        $_SESSION['alert_type'] = 'danger';
    } elseif (!preg_match("/^[a-zA-Z\s]{3,50}$/", $nama)) {
        $_SESSION['alert_message'] = "Nama hanya boleh mengandung huruf dan spasi (3-50 karakter).";
        $_SESSION['alert_type'] = 'danger';
    } elseif (!preg_match("/^\d{10,12}$/", $no_hp)) {
        $_SESSION['alert_message'] = "Nomor handphone harus 10-12 digit angka.";
        $_SESSION['alert_type'] = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['alert_message'] = "Alamat email tidak valid.";
        $_SESSION['alert_type'] = 'danger';
    } else {
        // 2. Proses ke Database jika validasi berhasil
        mysqli_autocommit($koneksi, FALSE); // Mulai mode transaksi
        $success = TRUE;
        $id_customer = null;
        $id_service = null;

        // Cek apakah customer sudah ada
        $stmtCheck = mysqli_prepare($koneksi, "SELECT id_customer FROM customer WHERE email = ? AND no_telepon = ? AND nama_customer = ?");
        mysqli_stmt_bind_param($stmtCheck, "sss", $email, $no_hp, $nama);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);

        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            mysqli_stmt_bind_result($stmtCheck, $id_customer);
            mysqli_stmt_fetch($stmtCheck);
        } else {
            // Customer belum ada, insert baru
            $stmtCustomer = mysqli_prepare($koneksi, "INSERT INTO customer (nama_customer, no_telepon, email) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmtCustomer, "sss", $nama, $no_hp, $email);
            if (!mysqli_stmt_execute($stmtCustomer)) {
                $success = FALSE;
                // Jangan tampilkan error teknis ke user, cukup pesan umum
                $_SESSION['alert_message'] = "Terjadi kesalahan saat menyimpan data pelanggan.";
                $_SESSION['alert_type'] = 'danger';
            } else {
                $id_customer = mysqli_insert_id($koneksi);
            }
            mysqli_stmt_close($stmtCustomer);
        }
        mysqli_stmt_close($stmtCheck);

        // Jika proses customer berhasil, lanjutkan insert ke tabel service
        if ($success && $id_customer) {
            $tanggal = date('Y-m-d H:i:s');
            $stmtService = mysqli_prepare($koneksi, "INSERT INTO service (id_customer, tanggal, device, keluhan) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmtService, "isss", $id_customer, $tanggal, $device, $keluhan);

            if (!mysqli_stmt_execute($stmtService)) {
                $success = FALSE;
                $_SESSION['alert_message'] = "Terjadi kesalahan saat menyimpan data service.";
                $_SESSION['alert_type'] = 'danger';
            } else {
                $id_service = mysqli_insert_id($koneksi);
            }
            mysqli_stmt_close($stmtService);
        }
        // 3. Kelola Transaksi
        if ($success) {
            mysqli_commit($koneksi); // Simpan semua perubahan jika berhasil
            $_SESSION['alert_message'] = "Data berhasil diajukan! ID Service kamu: <strong>#$id_service</strong>";
            $_SESSION['alert_type'] = 'success';

            $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->SMTPDebug = SMTP::DEBUG_OFF;                      //Enable verbose debug output
                $mail->isSMTP();                                            //Send using SMTP
                $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
                $mail->Username   = 'jarkom1500@gmail.com';                     //SMTP username
                $mail->Password   = 'adgs wtbb xcpa cdwm';                               //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom('jarkom1500@gmail.com', "Thar'z Computer");
                $mail->addAddress($email, $nama);

                //Content
                $mail->isHTML(true);                                  //Set email format to HTML
                $mail->Subject = 'Bukti Pengajuan Servis #[ID: ' . $id_service . ']"';
                $mail->Body    = '
                    <html>
                        <body>
                            <h2>Halo,' . $nama .'!</h2>
                            <p>Terima kasih telah mengajukan permohonan servis kepada kami. Data Anda telah kami terima.</p>
                            <p>Berikut adalah detail pengajuan Anda:</p>
                            <ul>
                                <li><strong>ID Servis:</strong> #' . $id_service . '</li>   
                                <li><strong>Perangkat:</strong> ' . $device . '</li>
                                <li><strong>Keluhan:</strong> ' . $keluhan . '</li>
                                <li><strong>Tanggal Masuk:</strong> ' . $tanggal . '</li>
                            </ul>
                            <p>Harap Datang ke Konter dengan membawa perangkat yang di ajukan ke tempat sesuai dengan waktu yang ditentukan  <bold>(2 JAM) </bold>.</p>
                            <p>Simpan ID Servis Anda untuk melakukan pengecekan status di kemudian hari. Kami akan segera menghubungi Anda.</p>
                            <p>Terima kasih,<br>Tim Support Servis Center</p>
                        </body>
                        </html>
                ';

                $mail->send();
                echo 'Message has been sent';
            } catch (Exception $e) {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            mysqli_rollback($koneksi); // Batalkan semua perubahan jika ada kegagalan
        }
        mysqli_autocommit($koneksi, TRUE); // Kembalikan ke mode autocommit
    }
} else {
    // Jika file diakses langsung tanpa submit form
    $_SESSION['alert_message'] = "Akses tidak sah.";
    $_SESSION['alert_type'] = 'danger';
}


// Alihkan kembali ke halaman formulir
header("Location: ../service.php");
exit();
