<?php
/*
File: ajax_get_jurnal_by_date.php
Untuk mengecek jurnal yang sudah diisi pada tanggal tertentu
*/

require_once '../config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // Ambil ID Guru
    $stmt_guru = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
    $stmt_guru->execute([$user_id]);
    $id_guru = $stmt_guru->fetchColumn();
    
    if (!$id_guru) {
        echo json_encode(['error' => 'Guru tidak ditemukan']);
        exit;
    }
    
    // Ambil daftar id_mengajar yang sudah diisi pada tanggal tersebut
    $stmt = $pdo->prepare("
        SELECT j.id_mengajar 
        FROM tbl_jurnal j
        JOIN tbl_mengajar m ON j.id_mengajar = m.id
        WHERE m.id_guru = ? AND j.tanggal = ?
    ");
    $stmt->execute([$id_guru, $tanggal]);
    $sudah_isi = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['sudah_isi' => $sudah_isi]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
