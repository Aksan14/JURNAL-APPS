<?php
/* File: guru/laporan_bulanan.php */
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];
// Cari ID Guru berdasarkan user_id session
$stmt_g = $pdo->prepare("SELECT id, nama_guru FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$guru = $stmt_g->fetch();

// Validasi: Pastikan guru ditemukan
if (!$guru) {
    $_SESSION['error_message'] = 'Akun Anda tidak terhubung dengan data guru. Silakan hubungi administrator.';
    header('Location: ' . BASE_URL . '/guru/index.php');
    exit;
}

$id_guru = $guru['id'];

// Default bulan dan tahun sekarang
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Query ambil data jurnal per bulan untuk guru ini dengan statistik kehadiran
$query = "
    SELECT j.*, k.nama_kelas, mp.nama_mapel,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = :id_guru 
    AND MONTH(j.tanggal) = :bulan 
    AND YEAR(j.tanggal) = :tahun
    ORDER BY j.tanggal ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['id_guru' => $id_guru, 'bulan' => $bulan, 'tahun' => $tahun]);
$laporan = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Rekap Jurnal Bulanan</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label">Pilih Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php
                        $nama_bulan = [
                            '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
                            '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                        ];
                        foreach($nama_bulan as $key => $val) {
                            $sel = ($bulan == $key) ? 'selected' : '';
                            echo "<option value='$key' $sel>$val</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun</label>
                    <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>">
                </div>
                <div class="col-md-4 mt-4 pt-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                    <?php if(count($laporan) > 0): ?>
                        <a href="export_jurnal_csv.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-success">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="export_jurnal_pdf.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light">
            <h6 class="m-0 font-weight-bold text-dark">Data Jurnal: <?= $nama_bulan[$bulan] ?> <?= $tahun ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>Tgl</th>
                            <th>Kelas</th>
                            <th>Mapel</th>
                            <th>Materi</th>
                            <th>H</th>
                            <th>S</th>
                            <th>I</th>
                            <th>A</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($laporan) > 0): ?>
                            <?php foreach($laporan as $row): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                                <td><?= htmlspecialchars($row['topik_materi'] ?? '-') ?></td>
                                <td class="text-center"><?= $row['hadir'] ?? 0 ?></td>
                                <td class="text-center"><?= $row['sakit'] ?? 0 ?></td>
                                <td class="text-center"><?= $row['izin'] ?? 0 ?></td>
                                <td class="text-center"><?= $row['alpa'] ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">Tidak ada data untuk bulan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>