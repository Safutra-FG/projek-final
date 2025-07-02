<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include '../../koneksi.php';

// Fungsi untuk redirect
function redirect_back($id_service, $msg = null)
{
    $url = '../edit_service.php?id=' . $id_service;
    if ($msg) {
        $url .= '&msg=' . urlencode($msg);
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Akses tidak valid.";
    exit;
}

$id_service = isset($_POST['id_service']) ? intval($_POST['id_service']) : 0;
if (!$id_service) {
    echo "ID Service tidak ditemukan.";
    exit;
}

// Ambil data service lama
$stmt_service = $koneksi->prepare(
    "SELECT s.*, c.nama_customer, c.email 
     FROM service s 
     JOIN customer c ON s.id_customer = c.id_customer 
     WHERE s.id_service = ?"
);
$stmt_service->bind_param("i", $id_service);
$stmt_service->execute();
$result_service = $stmt_service->get_result();
$service = $result_service->fetch_assoc();
$stmt_service->close();
if (!$service) {
    echo "Data service tidak ditemukan.";
    exit;
}

$error_messages = [];
$success_message = "";

if ($service['status'] == 'sudah diambil') {
    redirect_back($id_service, "Service sudah diambil, tidak dapat diubah.");
}

// Validasi CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    redirect_back($id_service, "CSRF token tidak valid. Silakan coba lagi.");
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$id_service_post = $id_service;

mysqli_begin_transaction($koneksi);
try {
    // Update data service utama
    $id_customer = mysqli_real_escape_string($koneksi, $_POST['id_customer']);
    $status_baru = mysqli_real_escape_string($koneksi, $_POST['status']);

    // Ambil status lama dan tanggal selesai saat ini
    $query_cek_status_lama = "SELECT status, tanggal_selesai FROM service WHERE id_service = ?";
    $stmt_cek_status = $koneksi->prepare($query_cek_status_lama);
    $stmt_cek_status->bind_param("i", $id_service_post);
    $stmt_cek_status->execute();
    $hasil_cek = $stmt_cek_status->get_result();
    $data_lama = $hasil_cek->fetch_assoc();
    $stmt_cek_status->close();

    $status_lama = $data_lama ? $data_lama['status'] : null;
    $tanggal_selesai_saat_ini = $data_lama ? $data_lama['tanggal_selesai'] : null;

    $customer_info = [
        'nama' => $service['nama_customer'],
        'email' => $service['email']
    ];

    if ($id_customer_baru != $service['id_customer']) {
        $stmt_cust_baru = $koneksi->prepare("SELECT nama_customer, email FROM customer WHERE id_customer = ?");
        $stmt_cust_baru->bind_param("s", $id_customer_baru);
        $stmt_cust_baru->execute();
        $result_cust_baru = $stmt_cust_baru->get_result()->fetch_assoc();
        $stmt_cust_baru->close();
        if ($result_cust_baru) {
            $customer_info['nama'] = $result_cust_baru['nama_customer'];
            $customer_info['email'] = $result_cust_baru['email'];
        }
    }

    $set_parts = [];
    $bind_types_update = "";
    $bind_params_update = [];

    $set_parts[] = "id_customer = ?";
    $bind_types_update .= "s";
    $bind_params_update[] = $id_customer;
    $set_parts[] = "status = ?";
    $bind_types_update .= "s";
    $bind_params_update[] = $status_baru;

    $status_selesai = ['selesai', 'siap diambil'];
    if (!in_array($status_lama, $status_selesai) && in_array($status_baru, $status_selesai)) {
        $set_parts[] = "tanggal_selesai = CURDATE()";
    } elseif (in_array($status_lama, $status_selesai) && !in_array($status_baru, $status_selesai)) {
        $set_parts[] = "tanggal_selesai = NULL";
    }

    if (!empty($set_parts)) {
        $query_update_service = "UPDATE service SET " . implode(", ", $set_parts) . " WHERE id_service = ?";
        $stmt_update_service = $koneksi->prepare($query_update_service);
        if ($stmt_update_service) {
            $bind_types_update .= "i";
            $bind_params_update[] = $id_service_post;
            $stmt_update_service->bind_param($bind_types_update, ...$bind_params_update);
            if (!$stmt_update_service->execute()) {
                throw new Exception("Error updating data service utama: " . $stmt_update_service->error);
            }
            $stmt_update_service->close();
        } else {
            throw new Exception("Error preparing statement untuk service utama: " . $koneksi->error);
        }
    }

    // Proses penambahan detail service baru
    if ((!empty(trim($_POST['kerusakan_detail'])) || !empty($_POST['id_barang']) || !empty($_POST['id_jasa']))) {
        $id_barang_val = !empty($_POST['id_barang']) ? intval($_POST['id_barang']) : null;
        $id_jasa_val = !empty($_POST['id_jasa']) ? intval($_POST['id_jasa']) : null;
        $kerusakan_detail_val = mysqli_real_escape_string($koneksi, $_POST['kerusakan_detail']);

        $server_calculated_total_detail = 0;
        if ($id_barang_val) {
            $stmt_harga_brg = $koneksi->prepare("SELECT harga FROM stok WHERE id_barang = ?");
            $stmt_harga_brg->bind_param("i", $id_barang_val);
            $stmt_harga_brg->execute();
            $res_brg = $stmt_harga_brg->get_result();
            if ($row_brg = $res_brg->fetch_assoc()) {
                $server_calculated_total_detail += (float)$row_brg['harga'];
            }
            $stmt_harga_brg->close();
        }
        if ($id_jasa_val) {
            $stmt_harga_jsa = $koneksi->prepare("SELECT harga FROM jasa WHERE id_jasa = ?");
            $stmt_harga_jsa->bind_param("i", $id_jasa_val);
            $stmt_harga_jsa->execute();
            $res_jsa = $stmt_harga_jsa->get_result();
            if ($row_jsa = $res_jsa->fetch_assoc()) {
                $server_calculated_total_detail += (float)$row_jsa['harga'];
            }
            $stmt_harga_jsa->close();
        }

        if (!empty($_POST['edit_id_ds'])) {
            // Update detail service yang ada
            $id_ds = intval($_POST['edit_id_ds']);
            $stmt_old_detail = $koneksi->prepare("SELECT id_barang FROM detail_service WHERE id_ds = ?");
            $stmt_old_detail->bind_param("i", $id_ds);
            $stmt_old_detail->execute();
            $old_detail = $stmt_old_detail->get_result()->fetch_assoc();
            $stmt_old_detail->close();
            if ($old_detail && $old_detail['id_barang']) {
                $stmt_return_stok = $koneksi->prepare("UPDATE stok SET stok = stok + 1 WHERE id_barang = ?");
                $stmt_return_stok->bind_param("i", $old_detail['id_barang']);
                $stmt_return_stok->execute();
                $stmt_return_stok->close();
            }
            if ($id_barang_val !== null) {
                $stmt_cek_stok = $koneksi->prepare("SELECT stok FROM stok WHERE id_barang = ? FOR UPDATE");
                $stmt_cek_stok->bind_param("i", $id_barang_val);
                $stmt_cek_stok->execute();
                $result_stok = $stmt_cek_stok->get_result();
                $data_stok = $result_stok->fetch_assoc();
                if ($data_stok['stok'] <= 0) {
                    throw new Exception("Stok barang tidak mencukupi.");
                }
                $stmt_update_stok = $koneksi->prepare("UPDATE stok SET stok = stok - 1 WHERE id_barang = ?");
                $stmt_update_stok->bind_param("i", $id_barang_val);
                if (!$stmt_update_stok->execute()) {
                    throw new Exception("Gagal mengupdate stok barang: " . $stmt_update_stok->error);
                }
                $stmt_update_stok->close();
                $stmt_cek_stok->close();
            }
            $stmt_update_detail = $koneksi->prepare("UPDATE detail_service SET id_barang = ?, id_jasa = ?, kerusakan = ?, total = ? WHERE id_ds = ?");
            if ($stmt_update_detail) {
                $stmt_update_detail->bind_param("iisdi", $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $server_calculated_total_detail, $id_ds);
                if (!$stmt_update_detail->execute()) {
                    throw new Exception("Error mengupdate detail service: " . $stmt_update_detail->error);
                }
                $stmt_update_detail->close();
            } else {
                throw new Exception("Error preparing statement untuk update detail service: " . $koneksi->error);
            }
        } else {
            // Insert detail service baru
            if ($id_barang_val !== null || $id_jasa_val !== null || !empty(trim($kerusakan_detail_val))) {
                if ($id_barang_val !== null) {
                    $stmt_cek_stok = $koneksi->prepare("SELECT stok FROM stok WHERE id_barang = ? FOR UPDATE");
                    $stmt_cek_stok->bind_param("i", $id_barang_val);
                    $stmt_cek_stok->execute();
                    $result_stok = $stmt_cek_stok->get_result();
                    $data_stok = $result_stok->fetch_assoc();
                    if ($data_stok['stok'] <= 0) {
                        throw new Exception("Stok barang tidak mencukupi.");
                    }
                    $stmt_update_stok = $koneksi->prepare("UPDATE stok SET stok = stok - 1 WHERE id_barang = ?");
                    $stmt_update_stok->bind_param("i", $id_barang_val);
                    if (!$stmt_update_stok->execute()) {
                        throw new Exception("Gagal mengupdate stok barang: " . $stmt_update_stok->error);
                    }
                    $stmt_update_stok->close();
                    $stmt_cek_stok->close();
                }
                $stmt_add_detail = $koneksi->prepare("INSERT INTO detail_service (id_service, id_barang, id_jasa, kerusakan, total) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_add_detail) {
                    $stmt_add_detail->bind_param("iiisd", $id_service_post, $id_barang_val, $id_jasa_val, $kerusakan_detail_val, $server_calculated_total_detail);
                    if (!$stmt_add_detail->execute()) {
                        throw new Exception("Error menambahkan detail service: " . $stmt_add_detail->error);
                    }
                    $stmt_add_detail->close();
                } else {
                    throw new Exception("Error preparing statement untuk detail service: " . $koneksi->error);
                }
            }
        }
    }

    if ($status_lama !== $status_baru && !empty($customer_info['email'])) {
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
            $mail->addAddress($customer_info['email'], $customer_info['nama']);

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = 'Bukti Pengajuan Servis #[ID: ' . $id_service . ']"';
            $mail->Body    = "
                    <h3>Halo, " . htmlspecialchars($customer_info['nama']) . "!</h3>
                    <p>Ada pembaruan untuk status service Anda dengan nomor <strong>#" . $id_service . "</strong>.</p>
                    <p>
                        Status lama: <strong>" . htmlspecialchars(ucfirst($status_lama)) . "</strong><br>
                        Status baru: <strong>" . htmlspecialchars(ucfirst($status_baru)) . "</strong>
                    </p>
                    <br>
                    <p>Terimakasih,<br>Tim Support Servis Center</p>";

            $mail->AltBody = "Halo, " . htmlspecialchars($customer_info['nama']) . ".\nAda pembaruan untuk status service Anda #" . $id_service . ".\nStatus lama: " . ucfirst($status_lama) . ".\nStatus baru: " . ucfirst($status_baru) . ".\nTerima kasih.";

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            redirect_back($id_service, "Terjadi error: " . $e->getMessage());
        }
    }

    mysqli_commit($koneksi);
    redirect_back($id_service, "Data service berhasil diupdate.");
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    redirect_back($id_service, "Terjadi error: " . $e->getMessage());
}
