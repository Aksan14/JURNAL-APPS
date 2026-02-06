<?php
/**
 * Migration: Create tbl_jam_khusus
 * Tabel untuk menyimpan pengurangan jam (pulang cepat, dll)
 */

require_once __DIR__ . '/Migration.php';

class CreateTblJamKhusus extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_jam_khusus (
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
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_jam_khusus berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_jam_khusus";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_jam_khusus berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
