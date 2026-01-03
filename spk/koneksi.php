<?php
// koneksi.php
// File koneksi database MySQL
$host = 'localhost';
$user = 'root';
$pass = '';
//nama database untuk connect
$dbname = 'spk';

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>