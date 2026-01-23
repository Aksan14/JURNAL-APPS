-- =====================================================
-- TABEL: tbl_kehadiran_guru
-- Menyimpan data kehadiran/blokir guru (tidak hadir, sakit, izin, cuti)
-- Digunakan admin untuk memblokir guru agar tidak bisa mengisi jurnal
-- =====================================================
CREATE TABLE IF NOT EXISTS tbl_kehadiran_guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    tanggal DATE NOT NULL,
    status_kehadiran ENUM('tidak_hadir', 'sakit', 'izin', 'cuti') NOT NULL,
    keterangan TEXT DEFAULT NULL COMMENT 'Alasan tidak hadir',
    created_by INT DEFAULT NULL COMMENT 'ID admin yang membuat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_guru) REFERENCES tbl_guru(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES tbl_users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_guru_tanggal (id_guru, tanggal),
    INDEX idx_guru (id_guru),
    INDEX idx_tanggal (tanggal),
    INDEX idx_status (status_kehadiran)
) ENGINE=InnoDB;

-- =====================================================
-- Cara menjalankan:
-- 1. Buka phpMyAdmin atau MySQL CLI
-- 2. Pilih database jurnal_app
-- 3. Jalankan SQL di atas
-- =====================================================
