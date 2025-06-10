<?php
session_start();       // Mulai session
unset($_SESSION['username']);
unset($_SESSION['role']);
unset($_SESSION['id_user']);
session_destroy();
header("Location: login.php"); // Arahkan balik ke login
exit;
?>