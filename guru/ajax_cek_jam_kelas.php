<?php
/*
File: guru/ajax_cek_jam_kelas.php
Lokasi: /jurnal_app/guru/ajax_cek_jam_kelas.php
Deskripsi: AJAX endpoint untuk mengecek total jam yang sudah terisi di suatu kelas pada tanggal tertentu
*/

require_once '../config.php';

header('Content-Type: application/json');

// Konstanta batas maksimal jam per kelas per hari
define('MAX_JAM_PER_HARI', 10);

if (!isset($_GET['id_mengajar']) || !isset($_GET['tanggal'])) {
    echo json_encode(['error' => 'Parameter tidak lengkap']);
    exit;
}

$id_mengajar = (int)$_GET['id_mengajar'];
$tanggal = $_GET['tanggal'];
$jam_ke = $_GET['jam_ke'] ?? '';

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    echo json_encode(['error' => 'Format tanggal tidak valid']);
    exit;
}

try {
    // Ambil id_kelas dari id_mengajar
    $stmt_kelas = $pdo->prepare("SELECT id_kelas FROM tbl_mengajar WHERE id = ?");
    $stmt_kelas->execute([$id_mengajar]);
    $id_kelas = $stmt_kelas->fetchColumn();

    if (!$id_kelas) {
        echo json_encode(['error' => 'Data mengajar tidak ditemukan']);
        exit;
    }

    // Hitung total jam yang sudah terisi untuk kelas ini pada tanggal tersebut
    $stmt_jam = $pdo->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN j.jam_ke LIKE '%-%' THEN 
                        CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                        CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
                    ELSE 1
                END
            ), 0) as total_jam
        FROM tbl_jurnal j
        JOIN tbl_mengajar m ON j.id_mengajar = m.id
        WHERE m.id_kelas = ? AND j.tanggal = ?
    ");
    $stmt_jam->execute([$id_kelas, $tanggal]);
    $total_jam_terisi = (int)$stmt_jam->fetchColumn();

    // Hitung jam yang akan diinput
    $jam_akan_diinput = 1;
    if (!empty($jam_ke) && preg_match('/^(\d+)-(\d+)$/', $jam_ke, $matches)) {
        $jam_akan_diinput = $matches[2] - $matches[1] + 1;
    } elseif (!empty($jam_ke) && is_numeric($jam_ke)) {
        $jam_akan_diinput = 1;
    }

    // Hitung sisa jam yang tersedia
    $sisa_jam = MAX_JAM_PER_HARI - $total_jam_terisi;
    $is_valid = ($total_jam_terisi + $jam_akan_diinput) <= MAX_JAM_PER_HARI;

    // Ambil detail jurnal yang sudah terisi hari itu
    $stmt_detail = $pdo->prepare("
        SELECT j.jam_ke, g.nama_guru, mp.nama_mapel,
               CASE 
                   WHEN j.jam_ke LIKE '%-%' THEN 
                       CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                       CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
                   ELSE 1
               END as jumlah_jam
        FROM tbl_jurnal j
        JOIN tbl_mengajar m ON j.id_mengajar = m.id
        JOIN tbl_guru g ON m.id_guru = g.id
        JOIN tbl_mapel mp ON m.id_mapel = mp.id
        WHERE m.id_kelas = ? AND j.tanggal = ?
        ORDER BY j.jam_ke ASC
    ");
    $stmt_detail->execute([$id_kelas, $tanggal]);
    $detail_jurnal = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

    // Ambil nama kelas
    $stmt_nama_kelas = $pdo->prepare("SELECT nama_kelas FROM tbl_kelas WHERE id = ?");
    $stmt_nama_kelas->execute([$id_kelas]);
    $nama_kelas = $stmt_nama_kelas->fetchColumn();

    echo json_encode([
        'success' => true,
        'id_kelas' => $id_kelas,
        'nama_kelas' => $nama_kelas,
        'tanggal' => $tanggal,
        'total_jam_terisi' => $total_jam_terisi,
        'sisa_jam' => $sisa_jam,
        'max_jam' => MAX_JAM_PER_HARI,
        'jam_akan_diinput' => $jam_akan_diinput,
        'is_valid' => $is_valid,
        'detail_jurnal' => $detail_jurnal,
        'message' => $is_valid 
            ? "Masih tersedia $sisa_jam jam untuk kelas ini hari ini."
            : "Peringatan: Total jam melebihi batas maksimal " . MAX_JAM_PER_HARI . " jam per hari!"
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
