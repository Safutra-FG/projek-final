<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include '../../koneksi.php';
include '../auth.php';


// Proses form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = $_POST['nama'];
    $nomor_telepon = $_POST['nomor_telepon'];
    $email = $_POST['email'];
    $device = $_POST['device'];
    $keluhan = $_POST['keluhan'];
    $tanggal = date('Y-m-d H:i:s');

    // Cek apakah customer sudah ada
    $check_sql = "SELECT id_customer FROM customer WHERE nama_customer = ? AND no_telepon = ?";
    $check_stmt = $koneksi->prepare($check_sql);
    $check_stmt->bind_param("ss", $nama, $nomor_telepon);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Customer sudah ada, gunakan id_customer yang ada
        $customer = $result->fetch_assoc();
        $id_customer = $customer['id_customer'];
    } else {
        // Customer belum ada, insert customer baru
        $insert_customer_sql = "INSERT INTO customer (nama_customer, no_telepon, email) VALUES (?, ?, ?)";
        $insert_customer_stmt = $koneksi->prepare($insert_customer_sql);
        $insert_customer_stmt->bind_param("sss", $nama, $nomor_telepon, $email);

        if ($insert_customer_stmt->execute()) {
            $id_customer = $koneksi->insert_id;
        } else {
            echo "<script>alert('Error: " . $insert_customer_stmt->error . "');</script>";
            exit;
        }
        $insert_customer_stmt->close();
    }
    $check_stmt->close();

    // Query untuk menyimpan data service
    $sql = "INSERT INTO service (id_customer, device, keluhan, tanggal) 
            VALUES (?, ?, ?, ?)";

    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("isss", $id_customer, $device, $keluhan, $tanggal);

    if ($stmt->execute()) {

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
                        <h2>Halo,' . $nama . '!</h2>
                        <p>Terima kasih telah mengajukan permohonan servis kepada kami. Data Anda telah kami terima.</p>
                        <p>Berikut adalah detail pengajuan Anda:</p>
                        <ul>
                            <li><strong>ID Servis:</strong> #' . $id_service . '</li>   
                            <li><strong>Perangkat:</strong> ' . $device . '</li>
                            <li><strong>Keluhan:</strong> ' . $keluhan . '</li>
                            <li><strong>Tanggal Masuk:</strong> ' . $tanggal . '</li>
                        </ul>
                        <p>Harap simpan ID Servis Anda untuk melakukan pengecekan status di kemudian hari. Kami akan segera menghubungi Anda.</p>
                        <p>Terima kasih,<br>Tim Support Servis Center</p>
                    </body>
                    </html>";
            ';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        echo "<script>
                alert('Service berhasil ditambahkan!'); 
                window.location.href = '../dashboard.php';
              </script>";
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }

    $stmt->close();
}
