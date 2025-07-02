<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
include '../koneksi.php';

if (!isset($_GET['id'])) {
  header("Location: data_service.php");
  exit;
}

$id_service = intval($_GET['id']);

// Cek apakah service ada dan statusnya 'selesai' atau 'siap diambil'
$query_check = "SELECT s.status,c.nama_customer,c.email
                FROM service s
                JOIN customer c ON s.id_customer = c.id_customer 
                WHERE id_service = ?";
$stmt_check = $koneksi->prepare($query_check);
$stmt_check->bind_param("i", $id_service);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
  echo "<script>
            alert('Service tidak ditemukan!');
            window.location.href='data_service.php';
          </script>";
  exit;
}

$service_data = $result_check->fetch_assoc();
if ($service_data['status'] !== 'selesai' && $service_data['status'] !== 'siap diambil') {
  echo "<script>
            alert('Hanya service dengan status Selesai atau Siap Diambil yang dapat dikonfirmasi pengambilannya!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
  exit;
}

// Update status service menjadi 'sudah diambil'
$query_update = "UPDATE service SET status = 'sudah diambil' WHERE id_service = ?";
$stmt_update = $koneksi->prepare($query_update);
$stmt_update->bind_param("i", $id_service);

if ($stmt_update->execute()) {
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
    $mail->addAddress($service_data['email'], $service_data['nama_customer']);     //Add a recipient

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Pengambilan Service dengan Nomor Servis #[ID: ' . $id_service . ']"';
    $mail->Body    = "
                    <h3>Halo, " . htmlspecialchars($service_data['nama_customer']) . "!</h3>
                    <p>Terimakasih telah menggunakan layanan kami</strong>.</p>
                    <br>
                    <p>Tim Support Servis Center</p>";

    $mail->AltBody = "Halo, " . htmlspecialchars($service_data['nama_customer']) . ".\Terimakasih telah menggunakan layanan kami.\nTerima kasih.";

    $mail->send();
    echo 'Message has been sent';
  } catch (Exception $e) {
    redirect_back($id_service, "Terjadi error: " . $e->getMessage());
  }

  echo "<script>
            alert('Status service berhasil diubah menjadi Sudah Diambil!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
} else {
  echo "<script>
            alert('Gagal mengubah status service!');
            window.location.href='edit_service.php?id=" . $id_service . "';
          </script>";
}

$stmt_check->close();
$stmt_update->close();
mysqli_close($koneksi);
