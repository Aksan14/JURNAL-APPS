<?php
/**
 * Migration: Create tbl_jurnal
 * Tabel untuk menyimpan data jurnal pembelajaran harian
 */

require_once __DIR__ . '/Migration.php';

class CreateTblJurnal extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_jurnal (
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
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_jurnal berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_jurnal";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_jurnal berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
