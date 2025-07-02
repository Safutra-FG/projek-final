<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
include '../koneksi.php';
include 'auth.php';

// Pastikan fungsi ini ada di auth.php atau sesuaikan
if (function_exists('getNamaUser')) {
    $namaAkun = getNamaUser();
} else {
    $namaAkun = 'Admin';
}

// Ambil ID service dari parameter URL
$id_service = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id_service) {
    header('Location: data_service.php');
    exit();
}

// Ambil data service
$sql = "SELECT s.*, c.nama_customer, c.email
        FROM service s 
        JOIN customer c ON s.id_customer = c.id_customer 
        WHERE s.id_service = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $id_service);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();

// Query untuk mengambil data barang
$sql_barang = "SELECT b.* FROM stok b WHERE b.stok > 0";
$barangs_result = mysqli_query($koneksi, $sql_barang);

// Query untuk mengambil data jasa
$sql_jasa = "SELECT * FROM jasa";
$jasas_result = mysqli_query($koneksi, $sql_jasa);

// Query untuk mengambil detail service, disesuaikan dengan struktur baru
$sql_detail = "SELECT ds.*, b.nama_barang, j.jenis_jasa 
               FROM detail_service ds 
               LEFT JOIN stok b ON ds.id_barang = b.id_barang 
               LEFT JOIN jasa j ON ds.id_jasa = j.id_jasa 
               WHERE ds.id_service = ?";
$stmt_detail = $koneksi->prepare($sql_detail);
$stmt_detail->bind_param("i", $id_service);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

if (!$service) {
    header('Location: data_service.php');
    exit();
}

// Blok POST Handling (TIDAK ADA PERUBAHAN, SUDAH BAGUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $koneksi->begin_transaction();

    try {
        $kerusakan = $_POST['kerusakan'];
        $estimasi_waktu = $_POST['estimasi_waktu'];
        $estimasi_harga = (float)$_POST['estimasi_harga'];
        $status = 'menunggu konfirmasi';

        $update_service_sql = "UPDATE service SET kerusakan = ?, estimasi_waktu = ?, estimasi_harga = ?, status = ? WHERE id_service = ?";
        $stmt_update_service = $koneksi->prepare($update_service_sql);
        $stmt_update_service->bind_param("ssdsi", $kerusakan, $estimasi_waktu, $estimasi_harga, $status, $id_service);
        $stmt_update_service->execute();

        // Hapus semua detail lama
        $delete_detail_sql = "DELETE FROM detail_service WHERE id_service = ?";
        $stmt_delete_detail = $koneksi->prepare($delete_detail_sql);
        $stmt_delete_detail->bind_param("i", $id_service);
        $stmt_delete_detail->execute();

        // Insert detail baru sesuai struktur baru
        $insert_detail_sql = "INSERT INTO detail_service (id_service, id_barang, id_jasa, kerusakan, total) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_detail = $koneksi->prepare($insert_detail_sql);

        if (isset($_POST['item_id_barangs']) || isset($_POST['item_id_jasas'])) {
            $item_id_barangs = $_POST['item_id_barangs'] ?? [];
            $item_id_jasas = $_POST['item_id_jasas'] ?? [];
            $item_kerusakans = $_POST['item_kerusakans'] ?? [];
            $item_totals = $_POST['item_totals'] ?? [];
            $item_jumlahs = $_POST['item_jumlahs'] ?? [];

            $count = count($item_kerusakans);

            for ($i = 0; $i < $count; $i++) {
                $id_barang = $item_id_barangs[$i] !== '' ? (int)$item_id_barangs[$i] : null;
                $id_jasa = $item_id_jasas[$i] !== '' ? (int)$item_id_jasas[$i] : null;
                $kerusakan_detail = $item_kerusakans[$i];
                $total = (int)$item_totals[$i];

                $stmt_insert_detail->bind_param("iiisi", $id_service, $id_barang, $id_jasa, $kerusakan_detail, $total);
                $stmt_insert_detail->execute();
            }
        }

        $koneksi->commit();
    } catch (Exception $e) {
        $koneksi->rollback();
        die("Error: Gagal menyimpan data. " . $e->getMessage());
    }


    if (!empty($service['email'])) {
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
            $mail->addAddress($service['email'], $service['nama_customer']);     //Add a recipient

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = 'Informasi Kerusakan perangkat #[ID: ' . $id_service . ']"';
            $mail->Body    = "
                    <h3>Halo, " . htmlspecialchars($service['nama_customer']) . "!</h3>
                    <p>Ada informasi kerusakan service Anda dengan nomor <strong>#" . $id_service . "</strong>.</p>
                    <p>
                    Berikut adalah detail kerusakan perangkat Anda:<br>
                    <strong>Kerusakan:</strong> " . htmlspecialchars($kerusakan) . "<br>
                    <strong>Estimasi Waktu:</strong> " . htmlspecialchars($estimasi_waktu) . "<br>
                    <strong>Estimasi Harga:</strong> Rp " . number_format($estimasi_harga, 0, ',', '.') . " 
                    </p>
                    <br>
                    <p>Silakan konfirmasi pesanan Anda dengan mengunjungi halaman (link nanti).</p>
                    <p>Terimakasih,<br>Tim Support Servis Center</p>";

            $mail->AltBody = "Halo, " . htmlspecialchars($service['nama_customer']) . ".\Harap konfirmasi pesanan anda yang bernomor #" . $id_service . ".\nTerima kasih.";

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            redirect_back($id_service, "Terjadi error: " . $e->getMessage());
        }
    }

    header('Location: data_service.php?status=updated');
    exit();
}
