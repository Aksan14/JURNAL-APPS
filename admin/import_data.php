<?php
/*
File: import_data.php
Lokasi: /jurnal_app/admin/import_data.php
Import data massal untuk Siswa, Guru, Mapel, Kelas, dan Plotting Mengajar
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$messageType = '';
$details = [];

// ==========================================================
// LOGIKA UPLOAD (saat form disubmit)
// ==========================================================
if (isset($_POST['upload_file']) && isset($_FILES['file_excel'])) {
    
    $import_type = $_POST['import_type'];
    $mode = $_POST['mode'] ?? 'skip'; // skip atau update
    $file = $_FILES['file_excel'];
    $fileTmpName = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "Terjadi error saat upload file. Kode: " . $file['error'];
        $messageType = 'danger';
    } elseif (!in_array($fileExt, ['xlsx', 'xls', 'csv'])) {
        $message = "Format file salah. Harap upload file .xlsx, .xls, atau .csv";
        $messageType = 'danger';
    } else {
        
        try {
            // Handle CSV dan Excel berbeda
            if ($fileExt == 'csv') {
                // Baca CSV
                $data = [];
                $rowNum = 0;
                if (($handle = fopen($fileTmpName, "r")) !== FALSE) {
                    while (($rowData = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $rowNum++;
                        // Convert to associative array dengan key A, B, C, D...
                        $row = [];
                        foreach ($rowData as $index => $value) {
                            $col = chr(65 + $index); // A=65, B=66, etc
                            $row[$col] = $value;
                        }
                        $data[$rowNum] = $row;
                    }
                    fclose($handle);
                }
            } else {
                // Baca Excel
                $spreadsheet = IOFactory::load($fileTmpName);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray(null, true, true, true);
            }

            $pdo->beginTransaction();
            
            $baris_ke = 1;
            $sukses = 0;
            $gagal = 0;
            $skip = 0;
            $update = 0;

            foreach ($data as $row) {
                if ($baris_ke == 1) { // Lewati header
                    $baris_ke++;
                    continue;
                }

                // ===================================
                // PROSES IMPORT SISWA
                // ===================================
                if ($import_type == 'siswa') {
                    $nis = trim($row['A'] ?? '');
                    $nama_siswa = trim($row['B'] ?? '');
                    $id_kelas = trim($row['C'] ?? '');

                    if (empty($nis) || empty($nama_siswa) || empty($id_kelas)) {
                        $gagal++;
                        $details[] = "Baris $baris_ke: Data tidak lengkap (NIS/Nama/Kelas kosong)";
                        $baris_ke++;
                        continue;
                    }

                    // Cek apakah NIS sudah ada
                    $check = $pdo->prepare("SELECT id FROM tbl_siswa WHERE nis = ?");
                    $check->execute([$nis]);
                    $existing = $check->fetch();

                    if ($existing) {
                        if ($mode == 'update') {
                            $stmt = $pdo->prepare("UPDATE tbl_siswa SET nama_siswa = ?, id_kelas = ? WHERE nis = ?");
                            $stmt->execute([$nama_siswa, $id_kelas, $nis]);
                            $update++;
                        } else {
                            $skip++;
                            $details[] = "Baris $baris_ke: NIS $nis sudah ada, di-skip";
                        }
                    } else {
                        // Insert siswa baru (tanpa akun dulu)
                        $stmt = $pdo->prepare("INSERT INTO tbl_siswa (nis, nama_siswa, id_kelas) VALUES (?, ?, ?)");
                        $stmt->execute([$nis, $nama_siswa, $id_kelas]);
                        $sukses++;
                    }
                
                // ===================================
                // PROSES IMPORT GURU
                // ===================================
                } elseif ($import_type == 'guru') {
                    $nip = trim($row['A'] ?? '');
                    $nama_guru = trim($row['B'] ?? '');
                    $email = trim($row['C'] ?? '');
                    $username = trim($row['D'] ?? '');
                    $password = trim($row['E'] ?? '');

                    if (empty($nama_guru)) {
                        $gagal++;
                        $details[] = "Baris $baris_ke: Nama guru kosong";
                        $baris_ke++;
                        continue;
                    }

                    // Cek apakah NIP sudah ada (jika NIP tidak kosong)
                    if (!empty($nip)) {
                        $check = $pdo->prepare("SELECT id FROM tbl_guru WHERE nip = ?");
                        $check->execute([$nip]);
                        $existing = $check->fetch();

                        if ($existing) {
                            if ($mode == 'update') {
                                $stmt = $pdo->prepare("UPDATE tbl_guru SET nama_guru = ?, email = ? WHERE nip = ?");
                                $stmt->execute([$nama_guru, $email, $nip]);
                                $update++;
                            } else {
                                $skip++;
                                $details[] = "Baris $baris_ke: NIP $nip sudah ada, di-skip";
                            }
                            $baris_ke++;
                            continue;
                        }
                    }

                    // Generate username jika kosong (dari nama guru atau NIP)
                    if (empty($username)) {
                        // Buat username dari nama guru (lowercase, tanpa spasi)
                        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nama_guru));
                        // Pastikan username unik
                        $base_username = $username;
                        $counter = 1;
                        while (true) {
                            $checkUser = $pdo->prepare("SELECT id FROM tbl_users WHERE username = ?");
                            $checkUser->execute([$username]);
                            if (!$checkUser->fetch()) break;
                            $username = $base_username . $counter;
                            $counter++;
                        }
                    } else {
                        // Cek apakah username sudah ada
                        $checkUser = $pdo->prepare("SELECT id FROM tbl_users WHERE username = ?");
                        $checkUser->execute([$username]);
                        if ($checkUser->fetch()) {
                            $gagal++;
                            $details[] = "Baris $baris_ke: Username '$username' sudah ada";
                            $baris_ke++;
                            continue;
                        }
                    }

                    // Password default = username jika kosong
                    if (empty($password)) {
                        $password = $username;
                    }
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // 1. Insert ke tbl_users dulu
                    $stmt1 = $pdo->prepare("INSERT INTO tbl_users (username, password_hash, role) VALUES (?, ?, 'guru')");
                    $stmt1->execute([$username, $password_hash]);
                    $user_id = $pdo->lastInsertId();

                    // 2. Insert guru baru dengan user_id
                    $stmt = $pdo->prepare("INSERT INTO tbl_guru (user_id, nip, nama_guru, email) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $nip, $nama_guru, $email]);
                    $sukses++;
                    $details[] = "Baris $baris_ke: Guru '$nama_guru' berhasil ditambah dengan username '$username'";

                // ===================================
                // PROSES IMPORT MAPEL
                // ===================================
                } elseif ($import_type == 'mapel') {
                    $kode_mapel = trim($row['A'] ?? '');
                    $nama_mapel = trim($row['B'] ?? '');

                    if (empty($nama_mapel)) {
                        $gagal++;
                        $details[] = "Baris $baris_ke: Nama mapel kosong";
                        $baris_ke++;
                        continue;
                    }

                    // Cek duplikat
                    $check = $pdo->prepare("SELECT id FROM tbl_mapel WHERE kode_mapel = ? OR nama_mapel = ?");
                    $check->execute([$kode_mapel, $nama_mapel]);
                    $existing = $check->fetch();

                    if ($existing) {
                        if ($mode == 'update') {
                            $stmt = $pdo->prepare("UPDATE tbl_mapel SET nama_mapel = ? WHERE kode_mapel = ?");
                            $stmt->execute([$nama_mapel, $kode_mapel]);
                            $update++;
                        } else {
                            $skip++;
                        }
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO tbl_mapel (kode_mapel, nama_mapel) VALUES (?, ?)");
                        $stmt->execute([$kode_mapel, $nama_mapel]);
                        $sukses++;
                    }

                // ===================================
                // PROSES IMPORT KELAS
                // ===================================
                } elseif ($import_type == 'kelas') {
                    $nama_kelas = trim($row['A'] ?? '');
                    $id_wali_kelas = !empty(trim($row['B'] ?? '')) ? trim($row['B']) : NULL;

                    if (empty($nama_kelas)) {
                        $gagal++;
                        $details[] = "Baris $baris_ke: Nama kelas kosong";
                        $baris_ke++;
                        continue;
                    }

                    // Cek duplikat
                    $check = $pdo->prepare("SELECT id FROM tbl_kelas WHERE nama_kelas = ?");
                    $check->execute([$nama_kelas]);
                    $existing = $check->fetch();

                    if ($existing) {
                        if ($mode == 'update') {
                            $stmt = $pdo->prepare("UPDATE tbl_kelas SET id_wali_kelas = ? WHERE nama_kelas = ?");
                            $stmt->execute([$id_wali_kelas, $nama_kelas]);
                            $update++;
                        } else {
                            $skip++;
                        }
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO tbl_kelas (nama_kelas, id_wali_kelas) VALUES (?, ?)");
                        $stmt->execute([$nama_kelas, $id_wali_kelas]);
                        $sukses++;
                    }

                // ===================================
                // PROSES IMPORT PLOTTING MENGAJAR
                // ===================================
                } elseif ($import_type == 'mengajar') {
                    $id_guru = trim($row['A'] ?? '');
                    $id_mapel = trim($row['B'] ?? '');
                    $id_kelas = trim($row['C'] ?? '');
                    $hari = trim($row['D'] ?? '');
                    $jam_ke = trim($row['E'] ?? '');
                    $jumlah_jam = (int)trim($row['F'] ?? 0);

                    // Validasi hari yang diizinkan
                    $hari_valid = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    if (!empty($hari) && !in_array($hari, $hari_valid)) {
                        $gagal++;
                        $details[] = "Baris $baris_ke: Hari '$hari' tidak valid (gunakan: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu)";
                        $baris_ke++;
                        continue;
                    }

                    if (empty($id_guru) || empty($id_mapel) || empty($id_kelas) || empty($hari) || empty($jam_ke)) {
                        $gagal++;
                        $details[] = "Baris $baris_ke: Data tidak lengkap (ID Guru/Mapel/Kelas/Hari/Jam_Ke kosong)";
                        $baris_ke++;
                        continue;
                    }

                    // Cek duplikat berdasarkan unique key uk_jadwal (id_kelas, hari, jam_ke)
                    $check = $pdo->prepare("SELECT id FROM tbl_mengajar WHERE id_kelas = ? AND hari = ? AND jam_ke = ?");
                    $check->execute([$id_kelas, $hari, $jam_ke]);
                    $existing = $check->fetch();

                    if ($existing) {
                        if ($mode == 'update') {
                            $stmt = $pdo->prepare("UPDATE tbl_mengajar SET id_guru = ?, id_mapel = ?, jumlah_jam_mingguan = ? WHERE id_kelas = ? AND hari = ? AND jam_ke = ?");
                            $stmt->execute([$id_guru, $id_mapel, $jumlah_jam, $id_kelas, $hari, $jam_ke]);
                            $update++;
                        } else {
                            $skip++;
                        }
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO tbl_mengajar (id_guru, id_mapel, id_kelas, hari, jam_ke, jumlah_jam_mingguan) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$id_guru, $id_mapel, $id_kelas, $hari, $jam_ke, $jumlah_jam]);
                        $sukses++;
                    }
                }
                
                $baris_ke++;
            }

            $pdo->commit();
            
            $result = [];
            if ($sukses > 0) $result[] = "<strong>$sukses</strong> data baru ditambahkan";
            if ($update > 0) $result[] = "<strong>$update</strong> data diupdate";
            if ($skip > 0) $result[] = "<strong>$skip</strong> data di-skip (duplikat)";
            if ($gagal > 0) $result[] = "<strong>$gagal</strong> data gagal (tidak lengkap)";
            
            $message = "Import selesai! " . implode(", ", $result) . ".";
            $messageType = 'success';

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Import GAGAL pada baris ke-$baris_ke. Semua data dibatalkan. Error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Import Data Massal</h1>
    </div>

    <p class="mb-4">Gunakan fitur ini untuk mengupload data master secara massal dari file Excel.</p>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php if (!empty($details)): ?>
        <div class="alert alert-warning">
            <strong>Detail:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach(array_slice($details, 0, 10) as $d): ?>
                    <li><?= htmlspecialchars($d) ?></li>
                <?php endforeach; ?>
                <?php if (count($details) > 10): ?>
                    <li>... dan <?= count($details) - 10 ?> lainnya</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="importTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="siswa-tab" data-bs-toggle="tab" data-bs-target="#siswa" type="button">Import Siswa</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="guru-tab" data-bs-toggle="tab" data-bs-target="#guru" type="button">Import Guru</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mapel-tab" data-bs-toggle="tab" data-bs-target="#mapel" type="button">Import Mapel</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="kelas-tab" data-bs-toggle="tab" data-bs-target="#kelas" type="button">Import Kelas</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mengajar-tab" data-bs-toggle="tab" data-bs-target="#mengajar" type="button">Import Plotting</button>
        </li>
    </ul>

    <div class="tab-content" id="importTabContent">
        
        <!-- TAB SISWA -->
        <div class="tab-pane fade show active" id="siswa" role="tabpanel">
            <div class="card shadow border-top-0 rounded-0 rounded-bottom">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-download text-success"></i> Langkah 1: Download Template</h5>
                            <p class="text-muted small">Download template, isi data siswa sesuai format.</p>
                            <a href="download_template_csv.php?tipe=siswa" class="btn btn-success btn-sm mb-3">
                                <i class="fas fa-file-csv"></i> Download Template Siswa (CSV)
                            </a>
                            
                            <div class="alert alert-info small">
                                <strong>Format Kolom:</strong><br>
                                A = NIS (wajib, unik)<br>
                                B = Nama Siswa (wajib)<br>
                                C = ID Kelas (wajib, lihat referensi di file)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-upload text-primary"></i> Langkah 2: Upload File</h5>
                            <form action="import_data.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="siswa">
                                <div class="mb-3">
                                    <label class="form-label">Pilih File (.csv, .xlsx, .xls)</label>
                                    <input class="form-control" type="file" name="file_excel" accept=".xlsx,.xls,.csv" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jika NIS sudah ada:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="skip" id="siswa_skip" checked>
                                        <label class="form-check-label" for="siswa_skip">Skip (lewati)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="update" id="siswa_update">
                                        <label class="form-check-label" for="siswa_update">Update data</label>
                                    </div>
                                </div>
                                <button type="submit" name="upload_file" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Siswa
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB GURU -->
        <div class="tab-pane fade" id="guru" role="tabpanel">
            <div class="card shadow border-top-0 rounded-0 rounded-bottom">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-download text-success"></i> Langkah 1: Download Template</h5>
                            <p class="text-muted small">Download template, isi data guru sesuai format.</p>
                            <a href="download_template_csv.php?tipe=guru" class="btn btn-success btn-sm mb-3">
                                <i class="fas fa-file-csv"></i> Download Template Guru (CSV)
                            </a>
                            
                            <div class="alert alert-info small">
                                <strong>Format Kolom:</strong><br>
                                A = NIP (opsional, unik jika diisi)<br>
                                B = Nama Guru (wajib)<br>
                                C = Email (opsional)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-upload text-primary"></i> Langkah 2: Upload File</h5>
                            <form action="import_data.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="guru">
                                <div class="mb-3">
                                    <label class="form-label">Pilih File (.csv, .xlsx, .xls)</label>
                                    <input class="form-control" type="file" name="file_excel" accept=".xlsx,.xls,.csv" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jika NIP sudah ada:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="skip" id="guru_skip" checked>
                                        <label class="form-check-label" for="guru_skip">Skip (lewati)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="update" id="guru_update">
                                        <label class="form-check-label" for="guru_update">Update data</label>
                                    </div>
                                </div>
                                <button type="submit" name="upload_file" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Guru
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB MAPEL -->
        <div class="tab-pane fade" id="mapel" role="tabpanel">
            <div class="card shadow border-top-0 rounded-0 rounded-bottom">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-download text-success"></i> Langkah 1: Download Template</h5>
                            <p class="text-muted small">Download template, isi data mata pelajaran.</p>
                            <a href="download_template_csv.php?tipe=mapel" class="btn btn-success btn-sm mb-3">
                                <i class="fas fa-file-csv"></i> Download Template Mapel (CSV)
                            </a>
                            
                            <div class="alert alert-info small">
                                <strong>Format Kolom:</strong><br>
                                A = Kode Mapel (opsional)<br>
                                B = Nama Mapel (wajib)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-upload text-primary"></i> Langkah 2: Upload File</h5>
                            <form action="import_data.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="mapel">
                                <div class="mb-3">
                                    <label class="form-label">Pilih File (.csv, .xlsx, .xls)</label>
                                    <input class="form-control" type="file" name="file_excel" accept=".xlsx,.xls,.csv" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jika Mapel sudah ada:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="skip" id="mapel_skip" checked>
                                        <label class="form-check-label" for="mapel_skip">Skip (lewati)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="update" id="mapel_update">
                                        <label class="form-check-label" for="mapel_update">Update data</label>
                                    </div>
                                </div>
                                <button type="submit" name="upload_file" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Mapel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB KELAS -->
        <div class="tab-pane fade" id="kelas" role="tabpanel">
            <div class="card shadow border-top-0 rounded-0 rounded-bottom">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-download text-success"></i> Langkah 1: Download Template</h5>
                            <p class="text-muted small">Download template Excel, isi data kelas.</p>
                            <a href="download_template.php?tipe=kelas" class="btn btn-success btn-sm mb-3">
                                <i class="fas fa-file-excel"></i> Download Template Kelas
                            </a>
                            
                            <div class="alert alert-info small">
                                <strong>Format Kolom:</strong><br>
                                A = Nama Kelas (wajib)<br>
                                B = ID Wali Kelas (opsional, lihat sheet referensi)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-upload text-primary"></i> Langkah 2: Upload File</h5>
                            <form action="import_data.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="kelas">
                                <div class="mb-3">
                                    <label class="form-label">Pilih File Excel (.xlsx)</label>
                                    <input class="form-control" type="file" name="file_excel" accept=".xlsx,.xls" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jika Kelas sudah ada:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="skip" id="kelas_skip" checked>
                                        <label class="form-check-label" for="kelas_skip">Skip (lewati)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="update" id="kelas_update">
                                        <label class="form-check-label" for="kelas_update">Update wali kelas</label>
                                    </div>
                                </div>
                                <button type="submit" name="upload_file" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Kelas
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB PLOTTING MENGAJAR -->
        <div class="tab-pane fade" id="mengajar" role="tabpanel">
            <div class="card shadow border-top-0 rounded-0 rounded-bottom">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-download text-success"></i> Langkah 1: Download Template</h5>
                            <p class="text-muted small">Download template Excel, isi plotting mengajar.</p>
                            <a href="download_template.php?tipe=mengajar" class="btn btn-success btn-sm mb-3">
                                <i class="fas fa-file-excel"></i> Download Template Plotting
                            </a>
                            
                            <div class="alert alert-info small">
                                <strong>Format Kolom:</strong><br>
                                A = ID Guru (wajib, lihat sheet referensi)<br>
                                B = ID Mapel (wajib, lihat sheet referensi)<br>
                                C = ID Kelas (wajib, lihat sheet referensi)<br>
                                D = Hari (wajib: Senin/Selasa/Rabu/Kamis/Jumat/Sabtu)<br>
                                E = Jam Ke (wajib, contoh: 1-2, 3-4, 5)<br>
                                F = Jumlah Jam/Minggu (opsional, default 0)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-upload text-primary"></i> Langkah 2: Upload File</h5>
                            <form action="import_data.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="mengajar">
                                <div class="mb-3">
                                    <label class="form-label">Pilih File Excel (.xlsx)</label>
                                    <input class="form-control" type="file" name="file_excel" accept=".xlsx,.xls" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jika Plotting sudah ada:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="skip" id="mengajar_skip" checked>
                                        <label class="form-check-label" for="mengajar_skip">Skip (lewati)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mode" value="update" id="mengajar_update">
                                        <label class="form-check-label" for="mengajar_update">Update jam mengajar</label>
                                    </div>
                                </div>
                                <button type="submit" name="upload_file" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Plotting
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Info Catatan -->
    <div class="card shadow mt-4">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle"></i> Catatan Penting</h6>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Pastikan data referensi (Guru, Kelas, Mapel) sudah diimport terlebih dahulu sebelum import Siswa atau Plotting.</li>
                <li>Import <strong>Siswa</strong> dan <strong>Guru</strong> tidak langsung membuat akun login. Buat akun melalui menu Kelola Guru/Siswa.</li>
                <li>Gunakan mode <strong>Update</strong> jika ingin memperbarui data yang sudah ada.</li>
                <li>File harus berformat <strong>.xlsx</strong> (Excel 2007+).</li>
                <li>Baris pertama dianggap sebagai header dan akan dilewati.</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
