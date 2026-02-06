<?php
/**
 * Migration: Create tbl_kehadiran_guru
 * Tabel untuk menyimpan data kehadiran/blokir guru
 */

require_once __DIR__ . '/Migration.php';

class CreateTblKehadiranGuru extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_kehadiran_guru (
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
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_kehadiran_guru berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_kehadiran_guru";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_kehadiran_guru berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
