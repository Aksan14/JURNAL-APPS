<?php
/**
 * Migration: Create tbl_hari_libur
 * Tabel untuk menyimpan data hari libur nasional dan sekolah
 */

require_once __DIR__ . '/Migration.php';

class CreateTblHariLibur extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_hari_libur (
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
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_hari_libur berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_hari_libur";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_hari_libur berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
