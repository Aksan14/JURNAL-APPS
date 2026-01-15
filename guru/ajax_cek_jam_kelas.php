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

    // ============================================
    // CEK HARI LIBUR (Umum atau Khusus Kelas)
    // ============================================
    $stmt_libur = $pdo->prepare("
        SELECT nama_libur, jenis, id_kelas FROM tbl_hari_libur 
        WHERE tanggal = ? AND (id_kelas IS NULL OR id_kelas = ?)
        ORDER BY id_kelas DESC LIMIT 1
    ");
    $stmt_libur->execute([$tanggal, $id_kelas]);
    $hari_libur = $stmt_libur->fetch();
    
    if ($hari_libur) {
        // Ambil nama kelas untuk response
        $stmt_nama_kelas = $pdo->prepare("SELECT nama_kelas FROM tbl_kelas WHERE id = ?");
        $stmt_nama_kelas->execute([$id_kelas]);
        $nama_kelas = $stmt_nama_kelas->fetchColumn();
        
        $jenis_libur = ucfirst(str_replace('_', ' ', $hari_libur['jenis']));
        $keterangan_kelas = $hari_libur['id_kelas'] ? ' (Khusus kelas ini)' : ' (Semua kelas)';
        
        echo json_encode([
            'success' => true,
            'is_libur' => true,
            'nama_libur' => $hari_libur['nama_libur'],
            'jenis_libur' => $jenis_libur,
            'keterangan_kelas' => $keterangan_kelas,
            'id_kelas' => $id_kelas,
            'nama_kelas' => $nama_kelas,
            'tanggal' => $tanggal,
            'total_jam_terisi' => 0,
            'sisa_jam' => 0,
            'max_jam' => 0,
            'is_valid' => false,
            'message' => "Tanggal ini adalah HARI LIBUR: {$hari_libur['nama_libur']} ({$jenis_libur}){$keterangan_kelas}"
        ]);
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

    // Cek jam khusus untuk tanggal ini (prioritas: khusus kelas > global)
    $stmt_jam_khusus = $pdo->prepare("
        SELECT max_jam, alasan FROM tbl_jam_khusus 
        WHERE tanggal = ? AND (id_kelas IS NULL OR id_kelas = ?)
        ORDER BY id_kelas DESC LIMIT 1
    ");
    $stmt_jam_khusus->execute([$tanggal, $id_kelas]);
    $jam_khusus = $stmt_jam_khusus->fetch();
    
    $max_jam_hari_ini = $jam_khusus ? (int)$jam_khusus['max_jam'] : MAX_JAM_PER_HARI;
    $alasan_jam_khusus = $jam_khusus ? $jam_khusus['alasan'] : null;

    // Hitung jam yang akan diinput
    $jam_akan_diinput = 1;
    if (!empty($jam_ke) && preg_match('/^(\d+)-(\d+)$/', $jam_ke, $matches)) {
        $jam_akan_diinput = $matches[2] - $matches[1] + 1;
    } elseif (!empty($jam_ke) && is_numeric($jam_ke)) {
        $jam_akan_diinput = 1;
    }

    // Hitung sisa jam yang tersedia
    $sisa_jam = $max_jam_hari_ini - $total_jam_terisi;
    $is_valid = ($total_jam_terisi + $jam_akan_diinput) <= $max_jam_hari_ini;
    
    // Cek apakah jam sudah penuh (untuk jam khusus/pulang cepat)
    $is_jam_penuh = ($sisa_jam <= 0);

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
        'is_libur' => false,
        'id_kelas' => $id_kelas,
        'nama_kelas' => $nama_kelas,
        'tanggal' => $tanggal,
        'total_jam_terisi' => $total_jam_terisi,
        'sisa_jam' => max(0, $sisa_jam),
        'max_jam' => $max_jam_hari_ini,
        'max_jam_normal' => MAX_JAM_PER_HARI,
        'jam_khusus' => $alasan_jam_khusus,
        'jam_akan_diinput' => $jam_akan_diinput,
        'is_valid' => $is_valid,
        'is_jam_penuh' => $is_jam_penuh,
        'detail_jurnal' => $detail_jurnal,
        'message' => $is_jam_penuh 
            ? "JAM SUDAH PENUH! Tidak bisa mengisi jurnal untuk tanggal ini." . ($alasan_jam_khusus ? " ({$alasan_jam_khusus}: Maks {$max_jam_hari_ini} jam)" : "")
            : ($is_valid 
                ? "Masih tersedia " . max(0, $sisa_jam) . " jam untuk kelas ini hari ini."
                : "Peringatan: Total jam melebihi batas maksimal " . $max_jam_hari_ini . " jam per hari!")
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
