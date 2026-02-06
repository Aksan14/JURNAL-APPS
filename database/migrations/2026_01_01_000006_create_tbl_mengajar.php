<?php
/**
 * Migration: Create tbl_mengajar
 * Tabel untuk menyimpan jadwal mengajar guru
 */

require_once __DIR__ . '/Migration.php';

class CreateTblMengajar extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_mengajar (
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
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_mengajar berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_mengajar";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_mengajar berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
