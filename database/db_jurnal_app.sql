-- =====================================================
-- DATABASE: jurnal_app
-- Script Database Lengkap - Aplikasi Jurnal Guru
-- Versi: 2.0 (dengan Jadwal Hari & Jam)
-- Tanggal: 2026-01-12
-- =====================================================

-- Buat database
CREATE DATABASE IF NOT EXISTS jurnal_app CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE jurnal_app;

-- =====================================================
-- DROP TABLES (urutan terbalik karena foreign key)
-- =====================================================
DROP TABLE IF EXISTS tbl_log_aktivitas;
DROP TABLE IF EXISTS tbl_presensi_siswa;
DROP TABLE IF EXISTS tbl_jurnal;
DROP TABLE IF EXISTS tbl_mengajar;
DROP TABLE IF EXISTS tbl_siswa;
DROP TABLE IF EXISTS tbl_kelas;
DROP TABLE IF EXISTS tbl_guru;
DROP TABLE IF EXISTS tbl_mapel;
DROP TABLE IF EXISTS tbl_pengaturan;
DROP TABLE IF EXISTS tbl_users;

-- =====================================================
-- TABEL 1: tbl_users (Akun Login)
-- =====================================================
CREATE TABLE tbl_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'guru', 'walikelas', 'siswa') NOT NULL DEFAULT 'guru',
    foto_profil VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 2: tbl_mapel (Mata Pelajaran)
-- =====================================================
CREATE TABLE tbl_mapel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_mapel VARCHAR(20) DEFAULT NULL,
    nama_mapel VARCHAR(100) NOT NULL,
    kelompok_mapel ENUM('A', 'B', 'C') DEFAULT NULL COMMENT 'A=Wajib, B=Peminatan, C=Lintas Minat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nama_mapel (nama_mapel)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 3: tbl_guru (Data Guru)
-- =====================================================
CREATE TABLE tbl_guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    nip VARCHAR(30) DEFAULT NULL,
    nama_guru VARCHAR(100) NOT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    jenis_kelamin ENUM('L', 'P') DEFAULT NULL,
    tempat_lahir VARCHAR(50) DEFAULT NULL,
    tanggal_lahir DATE DEFAULT NULL,
    alamat TEXT DEFAULT NULL,
    no_hp VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    jabatan VARCHAR(50) DEFAULT NULL,
    status_kepegawaian ENUM('PNS', 'PPPK', 'GTT', 'Honorer') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL,
    INDEX idx_nip (nip),
    INDEX idx_nama_guru (nama_guru)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 4: tbl_kelas (Data Kelas)
-- =====================================================
CREATE TABLE tbl_kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(20) NOT NULL UNIQUE,
    tingkat ENUM('X', 'XI', 'XII') DEFAULT NULL,
    jurusan VARCHAR(50) DEFAULT NULL,
    id_wali_kelas INT DEFAULT NULL,
    tahun_ajaran VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_wali_kelas) REFERENCES tbl_guru(id) ON DELETE SET NULL,
    INDEX idx_nama_kelas (nama_kelas)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 5: tbl_siswa (Data Siswa)
-- =====================================================
CREATE TABLE tbl_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    id_kelas INT NOT NULL,
    nis VARCHAR(20) NOT NULL UNIQUE,
    nisn VARCHAR(20) DEFAULT NULL,
    nama_siswa VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('L', 'P') DEFAULT NULL,
    tempat_lahir VARCHAR(50) DEFAULT NULL,
    tanggal_lahir DATE DEFAULT NULL,
    alamat TEXT DEFAULT NULL,
    no_hp VARCHAR(20) DEFAULT NULL,
    nama_orangtua VARCHAR(100) DEFAULT NULL,
    no_hp_orangtua VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL,
    FOREIGN KEY (id_kelas) REFERENCES tbl_kelas(id) ON DELETE CASCADE,
    INDEX idx_nis (nis),
    INDEX idx_nama_siswa (nama_siswa),
    INDEX idx_id_kelas (id_kelas)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 6: tbl_mengajar (Jadwal Mengajar)
-- PENTING: Kolom 'hari' dan 'jam_ke' untuk tracking
-- =====================================================
CREATE TABLE tbl_mengajar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    id_mapel INT NOT NULL,
    id_kelas INT NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL COMMENT 'Hari jadwal',
    jam_ke VARCHAR(10) NOT NULL COMMENT 'Format: 1-2, 3-4, 5',
    jumlah_jam_mingguan INT DEFAULT 0,
    tahun_ajaran VARCHAR(10) DEFAULT NULL,
    semester ENUM('Ganjil', 'Genap') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
-- TABEL 7: tbl_jurnal (Jurnal Pembelajaran)
-- =====================================================
CREATE TABLE tbl_jurnal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_mengajar INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_ke VARCHAR(10) NOT NULL,
    topik_materi TEXT NOT NULL,
    kegiatan_pembelajaran TEXT DEFAULT NULL,
    catatan_guru TEXT DEFAULT NULL,
    metode_pembelajaran VARCHAR(100) DEFAULT NULL,
    media_pembelajaran VARCHAR(255) DEFAULT NULL,
    hambatan TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mengajar) REFERENCES tbl_mengajar(id) ON DELETE CASCADE,
    UNIQUE KEY uk_jurnal (id_mengajar, tanggal),
    INDEX idx_tanggal (tanggal),
    INDEX idx_mengajar (id_mengajar)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 8: tbl_presensi_siswa (Absensi Siswa)
-- =====================================================
CREATE TABLE tbl_presensi_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_jurnal INT NOT NULL,
    id_siswa INT NOT NULL,
    status_kehadiran ENUM('H', 'S', 'I', 'A') NOT NULL DEFAULT 'H' COMMENT 'H=Hadir, S=Sakit, I=Izin, A=Alpa',
    keterangan VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_jurnal) REFERENCES tbl_jurnal(id) ON DELETE CASCADE,
    FOREIGN KEY (id_siswa) REFERENCES tbl_siswa(id) ON DELETE CASCADE,
    UNIQUE KEY uk_presensi (id_jurnal, id_siswa),
    INDEX idx_jurnal (id_jurnal),
    INDEX idx_siswa (id_siswa),
    INDEX idx_status (status_kehadiran)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 9: tbl_pengaturan (Setting Aplikasi)
-- =====================================================
CREATE TABLE tbl_pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_setting VARCHAR(100) NOT NULL UNIQUE,
    nilai_setting TEXT DEFAULT NULL,
    deskripsi VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABEL 10: tbl_log_aktivitas (Log Audit)
-- =====================================================
CREATE TABLE tbl_log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    aktivitas VARCHAR(255) NOT NULL,
    detail TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- INSERT DATA DEFAULT
-- =====================================================

-- Admin default (password: password)
INSERT INTO tbl_users (username, password_hash, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Pengaturan default
INSERT INTO tbl_pengaturan (nama_setting, nilai_setting, deskripsi) VALUES 
('nama_sekolah', 'SMA Negeri 1 Contoh', 'Nama Sekolah'),
('alamat_sekolah', 'Jl. Pendidikan No. 1', 'Alamat Sekolah'),
('tahun_ajaran_aktif', '2025/2026', 'Tahun Ajaran Aktif'),
('semester_aktif', 'Genap', 'Semester Aktif'),
('max_jam_per_hari', '10', 'Maksimal jam pelajaran per hari per kelas');

-- Mata pelajaran contoh
INSERT INTO tbl_mapel (kode_mapel, nama_mapel, kelompok_mapel) VALUES 
('MAT', 'Matematika', 'A'),
('BIN', 'Bahasa Indonesia', 'A'),
('BIG', 'Bahasa Inggris', 'A'),
('FIS', 'Fisika', 'B'),
('KIM', 'Kimia', 'B'),
('BIO', 'Biologi', 'B'),
('SEJ', 'Sejarah', 'A'),
('PKN', 'Pendidikan Kewarganegaraan', 'A'),
('PAI', 'Pendidikan Agama Islam', 'A'),
('PJK', 'Pendidikan Jasmani', 'A');

-- Kelas contoh
INSERT INTO tbl_kelas (nama_kelas, tingkat, jurusan, tahun_ajaran) VALUES 
('X IPA 1', 'X', 'IPA', '2025/2026'),
('X IPA 2', 'X', 'IPA', '2025/2026'),
('X IPS 1', 'X', 'IPS', '2025/2026'),
('XI IPA 1', 'XI', 'IPA', '2025/2026'),
('XI IPA 2', 'XI', 'IPA', '2025/2026'),
('XI IPS 1', 'XI', 'IPS', '2025/2026'),
('XII IPA 1', 'XII', 'IPA', '2025/2026'),
('XII IPA 2', 'XII', 'IPA', '2025/2026'),
('XII IPS 1', 'XII', 'IPS', '2025/2026');

-- =====================================================
-- VIEW: Jadwal belum diisi jurnal hari ini
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
-- VIEW: Rekap jurnal guru per bulan
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
-- STORED PROCEDURE: Cek guru tidak masuk
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
-- SELESAI
-- =====================================================
SELECT 'Database jurnal_app berhasil dibuat!' as status;
