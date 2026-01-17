-- =====================================================
-- Database: jurnal_app
-- Sistem Manajemen Jurnal Pembelajaran
-- =====================================================

-- Buat database
CREATE DATABASE IF NOT EXISTS jurnal_app CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE jurnal_app;

-- Hapus tabel yang ada (urutan sesuai foreign key)
DROP TABLE IF EXISTS tbl_request_jurnal_mundur;
DROP TABLE IF EXISTS tbl_presensi_siswa;
DROP TABLE IF EXISTS tbl_jurnal;
DROP TABLE IF EXISTS tbl_mengajar;
DROP TABLE IF EXISTS tbl_siswa;
DROP TABLE IF EXISTS tbl_kelas;
DROP TABLE IF EXISTS tbl_guru;
DROP TABLE IF EXISTS tbl_mapel;
DROP TABLE IF EXISTS tbl_users;

-- =====================================================
-- TABEL: tbl_users
-- Menyimpan data autentikasi pengguna
-- =====================================================
CREATE TABLE tbl_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'guru', 'walikelas', 'siswa', 'kepsek') NOT NULL DEFAULT 'guru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_mapel
-- Menyimpan data mata pelajaran
-- =====================================================
CREATE TABLE tbl_mapel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_mapel VARCHAR(20) DEFAULT NULL,
    nama_mapel VARCHAR(100) NOT NULL,
    INDEX idx_kode_mapel (kode_mapel),
    INDEX idx_nama_mapel (nama_mapel)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_guru
-- Menyimpan data profil guru
-- =====================================================
CREATE TABLE tbl_guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    nip VARCHAR(30) DEFAULT NULL,
    nama_guru VARCHAR(100) NOT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL,
    INDEX idx_nip (nip),
    INDEX idx_nama_guru (nama_guru),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_kelas
-- Menyimpan data kelas
-- =====================================================
CREATE TABLE tbl_kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(20) NOT NULL UNIQUE,
    id_wali_kelas INT DEFAULT NULL,
    FOREIGN KEY (id_wali_kelas) REFERENCES tbl_guru(id) ON DELETE SET NULL,
    INDEX idx_nama_kelas (nama_kelas),
    INDEX idx_wali_kelas (id_wali_kelas)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_siswa
-- Menyimpan data siswa
-- =====================================================
CREATE TABLE tbl_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    id_kelas INT NOT NULL,
    nis VARCHAR(20) NOT NULL UNIQUE,
    nama_siswa VARCHAR(100) NOT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id) ON DELETE CASCADE,
    INDEX idx_nis (nis),
    INDEX idx_nama_siswa (nama_siswa),
    INDEX idx_id_kelas (id_kelas),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_mengajar
-- Menyimpan jadwal mengajar guru
-- =====================================================
CREATE TABLE tbl_mengajar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    id_mapel INT NOT NULL,
    id_kelas INT NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL COMMENT 'Hari jadwal mengajar',
    jam_ke VARCHAR(10) NOT NULL COMMENT 'Format: 1-2, 3-4, 5 (rentang jam pelajaran)',
    jumlah_jam_mingguan INT DEFAULT 0 COMMENT 'Jumlah jam mengajar per minggu',
    FOREIGN KEY (id_guru) REFERENCES tbl_guru(id) ON DELETE CASCADE,
    FOREIGN KEY (id_mapel) REFERENCES tbl_mapel(id) ON DELETE CASCADE,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_jadwal (id_kelas, hari, jam_ke),
    INDEX idx_guru (id_guru),
    INDEX idx_mapel (id_mapel),
    INDEX idx_kelas (id_kelas),
    INDEX idx_hari (hari),
    INDEX idx_jadwal_lengkap (id_kelas, hari, jam_ke)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_jurnal
-- Menyimpan data jurnal pembelajaran harian
-- =====================================================
CREATE TABLE tbl_jurnal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_mengajar INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_ke VARCHAR(10) NOT NULL COMMENT 'Jam pelajaran yang diisi jurnal',
    topik_materi TEXT NOT NULL COMMENT 'Materi yang diajarkan',
    catatan_guru TEXT DEFAULT NULL COMMENT 'Catatan tambahan dari guru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mengajar) REFERENCES tbl_mengajar(id) ON DELETE CASCADE,
    UNIQUE KEY uk_jurnal (id_mengajar, tanggal),
    INDEX idx_tanggal (tanggal),
    INDEX idx_mengajar (id_mengajar)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_presensi_siswa
-- Menyimpan data kehadiran siswa per jurnal
-- =====================================================
CREATE TABLE tbl_presensi_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_jurnal INT NOT NULL,
    id_siswa INT NOT NULL,
    status_kehadiran ENUM('H', 'S', 'I', 'A') NOT NULL DEFAULT 'H' COMMENT 'H=Hadir, S=Sakit, I=Izin, A=Alpa',
    FOREIGN KEY (id_jurnal) REFERENCES tbl_jurnal(id) ON DELETE CASCADE,
    FOREIGN KEY (id_siswa) REFERENCES tbl_siswa(id) ON DELETE CASCADE,
    UNIQUE KEY uk_presensi (id_jurnal, id_siswa),
    INDEX idx_jurnal (id_jurnal),
    INDEX idx_siswa (id_siswa),
    INDEX idx_status (status_kehadiran)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_request_jurnal_mundur
-- Menyimpan permintaan pengisian jurnal mundur dari guru
-- =====================================================
CREATE TABLE tbl_request_jurnal_mundur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    id_mengajar INT NOT NULL,
    tanggal_jurnal DATE NOT NULL COMMENT 'Tanggal jurnal yang diminta mundur',
    alasan TEXT NOT NULL COMMENT 'Alasan permintaan jurnal mundur',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    catatan_admin TEXT DEFAULT NULL COMMENT 'Catatan dari admin',
    notified_guru TINYINT(1) DEFAULT 0 COMMENT '0=belum dilihat guru, 1=sudah dilihat',
    approved_by INT DEFAULT NULL COMMENT 'ID admin yang memproses',
    approved_at DATETIME DEFAULT NULL COMMENT 'Waktu diproses',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_guru) REFERENCES tbl_guru(id) ON DELETE CASCADE,
    FOREIGN KEY (id_mengajar) REFERENCES tbl_mengajar(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES tbl_users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_request (id_guru, id_mengajar, tanggal_jurnal, status),
    INDEX idx_guru (id_guru),
    INDEX idx_mengajar (id_mengajar),
    INDEX idx_status (status),
    INDEX idx_tanggal (tanggal_jurnal),
    INDEX idx_approved_by (approved_by)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_hari_libur
-- Menyimpan data hari libur nasional dan sekolah
-- =====================================================
CREATE TABLE tbl_hari_libur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    nama_libur VARCHAR(100) NOT NULL,
    jenis ENUM('nasional', 'sekolah', 'cuti_bersama') NOT NULL DEFAULT 'sekolah',
    id_kelas INT DEFAULT NULL COMMENT 'NULL = berlaku semua kelas',
    keterangan TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tanggal_kelas (tanggal, id_kelas),
    INDEX idx_tanggal (tanggal),
    INDEX idx_jenis (jenis),
    INDEX idx_kelas (id_kelas)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: tbl_jam_khusus
-- Menyimpan pengurangan jam (pulang cepat, dll)
-- =====================================================
CREATE TABLE tbl_jam_khusus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    max_jam INT NOT NULL DEFAULT 10 COMMENT 'Maksimal jam pelajaran hari itu',
    alasan VARCHAR(200) NOT NULL COMMENT 'Contoh: Pulang cepat, Ujian, dll',
    id_kelas INT DEFAULT NULL COMMENT 'NULL = berlaku semua kelas',
    keterangan TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id) ON DELETE CASCADE,
    INDEX idx_tanggal (tanggal),
    INDEX idx_kelas (id_kelas)
) ENGINE=InnoDB;

-- =====================================================
-- DATA AWAL: Admin & Kepsek default
-- Username: admin | Password: password
-- Username: kepsek | Password: kepsek123
-- =====================================================
INSERT INTO tbl_users (username, password_hash, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('kepsek', '$2y$10$nbmZa6UbB9/PldYpdtLnSOH89.sFjI4UHRNPY0O4z/w0iPvVxNKe6', 'kepsek');

-- =====================================================
-- VIEW: v_jadwal_belum_isi_jurnal
-- Menampilkan jadwal yang belum diisi jurnal hari ini
-- =====================================================
CREATE OR REPLACE VIEW v_jadwal_belum_isi_jurnal AS
SELECT 
    m.id as id_mengajar,
    m.hari,
    m.jam_ke as jam_jadwal,
    g.id as id_guru,
    g.nama_guru,
    g.nip,
    k.id as id_kelas,
    k.nama_kelas,
    mp.id as id_mapel,
    mp.nama_mapel,
    CURDATE() as tanggal
FROM tbl_mengajar m
JOIN tbl_guru g ON m.id_guru = g.id
JOIN tbl_kelas k ON m.id_kelas = k.id
JOIN tbl_mapel mp ON m.id_mapel = mp.id
WHERE m.hari = CASE DAYOFWEEK(CURDATE())
    WHEN 2 THEN 'Senin'
    WHEN 3 THEN 'Selasa'
    WHEN 4 THEN 'Rabu'
    WHEN 5 THEN 'Kamis'
    WHEN 6 THEN 'Jumat'
    WHEN 7 THEN 'Sabtu'
    ELSE NULL
END
AND m.id NOT IN (
    SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = CURDATE()
);

-- =====================================================
-- VIEW: v_rekap_jurnal_guru
-- Rekap jurnal guru per bulan dengan total jam
-- =====================================================
CREATE OR REPLACE VIEW v_rekap_jurnal_guru AS
SELECT 
    g.id as id_guru,
    g.nama_guru,
    g.nip,
    YEAR(j.tanggal) as tahun,
    MONTH(j.tanggal) as bulan,
    COUNT(j.id) as total_jurnal,
    SUM(
        CASE 
            WHEN j.jam_ke LIKE '%-%' THEN 
                CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
            ELSE 1
        END
    ) as total_jam
FROM tbl_guru g
LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
LEFT JOIN tbl_jurnal j ON m.id = j.id_mengajar
WHERE j.id IS NOT NULL
GROUP BY g.id, g.nama_guru, g.nip, YEAR(j.tanggal), MONTH(j.tanggal);

-- =====================================================
-- STORED PROCEDURE: sp_cek_guru_tidak_masuk
-- Mengecek guru yang belum mengisi jurnal pada tanggal tertentu
-- =====================================================
DELIMITER //

CREATE PROCEDURE sp_cek_guru_tidak_masuk(IN p_tanggal DATE)
BEGIN
    DECLARE v_nama_hari VARCHAR(10);
    
    SET v_nama_hari = CASE DAYOFWEEK(p_tanggal)
        WHEN 2 THEN 'Senin'
        WHEN 3 THEN 'Selasa'
        WHEN 4 THEN 'Rabu'
        WHEN 5 THEN 'Kamis'
        WHEN 6 THEN 'Jumat'
        WHEN 7 THEN 'Sabtu'
        ELSE NULL
    END;
    
    IF v_nama_hari IS NULL THEN
        SELECT 'Hari Minggu - Libur' as pesan;
    ELSE
        SELECT 
            m.id as id_mengajar,
            m.hari,
            m.jam_ke as jam_jadwal,
            g.id as id_guru,
            g.nama_guru,
            g.nip,
            k.id as id_kelas,
            k.nama_kelas,
            mp.nama_mapel,
            p_tanggal as tanggal,
            'Belum Mengisi Jurnal' as status
        FROM tbl_mengajar m
        JOIN tbl_guru g ON m.id_guru = g.id
        JOIN tbl_kelas k ON m.id_kelas = k.id
        JOIN tbl_mapel mp ON m.id_mapel = mp.id
        WHERE m.hari = v_nama_hari
        AND m.id NOT IN (
            SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = p_tanggal
        )
        ORDER BY k.nama_kelas, m.jam_ke;
    END IF;
END //

DELIMITER ;

-- =====================================================
-- Selesai! Database siap digunakan
-- =====================================================
SELECT 'Database jurnal_app berhasil dibuat!' as status;
