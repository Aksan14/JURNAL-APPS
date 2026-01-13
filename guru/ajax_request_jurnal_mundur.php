<?php
/*
File: ajax_request_jurnal_mundur.php
Lokasi: /jurnal_app/guru/ajax_request_jurnal_mundur.php
Fungsi: Handle request izin jurnal mundur dari guru
*/

// Disable output buffering untuk clean JSON output
while (ob_get_level()) {
    ob_end_clean();
}

// Set header JSON
header('Content-Type: application/json; charset=utf-8');

// Load config
require_once '../config.php';

// Cek login secara manual untuk AJAX
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login ulang.']);
    exit;
}

// Pastikan user adalah guru/walikelas
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'walikelas'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil id_guru
$stmt_guru = $pdo->prepare("SELECT id, nama_guru FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();

if (!$guru) {
    echo json_encode(['success' => false, 'message' => 'Data guru tidak ditemukan']);
    exit;
}

$id_guru = $guru['id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_request') {
        $id_mengajar = isset($_POST['id_mengajar']) ? trim($_POST['id_mengajar']) : '';
        $tanggal_jurnal = isset($_POST['tanggal_jurnal']) ? trim($_POST['tanggal_jurnal']) : '';
        $alasan = isset($_POST['alasan']) ? trim($_POST['alasan']) : '';
        
        // Validasi input
        if (empty($id_mengajar) || empty($tanggal_jurnal) || empty($alasan)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Semua field harus diisi',
                'debug' => [
                    'id_mengajar' => $id_mengajar,
                    'tanggal_jurnal' => $tanggal_jurnal,
                    'alasan_empty' => empty($alasan)
                ]
            ]);
            exit;
        }
        
        // Validasi format tanggal
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_jurnal)) {
            echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid']);
            exit;
        }
        
        // Validasi id_mengajar milik guru ini
        $stmt_valid = $pdo->prepare("SELECT id FROM tbl_mengajar WHERE id = ? AND id_guru = ?");
        $stmt_valid->execute([$id_mengajar, $id_guru]);
        if (!$stmt_valid->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Jadwal mengajar tidak valid atau bukan milik Anda']);
            exit;
        }
        
        // Cek apakah sudah ada request pending untuk tanggal & mengajar yang sama
        $stmt_check = $pdo->prepare("
            SELECT id FROM tbl_request_jurnal_mundur 
            WHERE id_guru = ? AND id_mengajar = ? AND tanggal_jurnal = ? AND status = 'pending'
        ");
        $stmt_check->execute([$id_guru, $id_mengajar, $tanggal_jurnal]);
        
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah memiliki permintaan pending untuk tanggal ini']);
            exit;
        }
        
        // Cek apakah sudah ada request approved
        $stmt_approved = $pdo->prepare("
            SELECT id FROM tbl_request_jurnal_mundur 
            WHERE id_guru = ? AND id_mengajar = ? AND tanggal_jurnal = ? AND status = 'approved'
        ");
        $stmt_approved->execute([$id_guru, $id_mengajar, $tanggal_jurnal]);
        
        if ($stmt_approved->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Permintaan untuk tanggal ini sudah disetujui sebelumnya']);
            exit;
        }
        
        try {
            $stmt_insert = $pdo->prepare("
                INSERT INTO tbl_request_jurnal_mundur (id_guru, id_mengajar, tanggal_jurnal, alasan) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt_insert->execute([$id_guru, $id_mengajar, $tanggal_jurnal, $alasan]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Permintaan berhasil dikirim! Mohon tunggu persetujuan dari admin.'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengirim permintaan: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'get_my_requests') {
        // Ambil daftar request dari guru ini
        $stmt = $pdo->prepare("
            SELECT r.*, k.nama_kelas, mp.nama_mapel, m.hari,
                   DATE_FORMAT(r.tanggal_jurnal, '%d/%m/%Y') as tanggal_format,
                   DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_format
            FROM tbl_request_jurnal_mundur r
            JOIN tbl_mengajar m ON r.id_mengajar = m.id
            JOIN tbl_kelas k ON m.id_kelas = k.id
            JOIN tbl_mapel mp ON m.id_mapel = mp.id
            WHERE r.id_guru = ?
            ORDER BY r.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$id_guru]);
        $requests = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $requests]);
        exit;
    }
    
    if ($action === 'mark_read') {
        // Tandai notifikasi sudah dibaca oleh guru
        $request_id = $_POST['request_id'] ?? '';
        
        if (empty($request_id)) {
            echo json_encode(['success' => false, 'message' => 'Request ID tidak valid']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE tbl_request_jurnal_mundur 
                SET notified_guru = 1 
                WHERE id = ? AND id_guru = ?
            ");
            $stmt->execute([$request_id, $id_guru]);
            
            echo json_encode(['success' => true, 'message' => 'Notifikasi ditandai dibaca']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Handle GET request - cek apakah ada izin untuk tanggal tertentu
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id_mengajar = $_GET['id_mengajar'] ?? '';
    $tanggal = $_GET['tanggal'] ?? '';
    
    if ($id_mengajar && $tanggal) {
        $stmt = $pdo->prepare("
            SELECT status FROM tbl_request_jurnal_mundur 
            WHERE id_guru = ? AND id_mengajar = ? AND tanggal_jurnal = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$id_guru, $id_mengajar, $tanggal]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'has_request' => $result ? true : false,
            'status' => $result ? $result['status'] : null
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
