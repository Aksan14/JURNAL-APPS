<?php
/**
 * Migration: Create tbl_kelas
 * Tabel untuk menyimpan data kelas
 */

require_once __DIR__ . '/Migration.php';

class CreateTblKelas extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_kelas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_kelas VARCHAR(20) NOT NULL UNIQUE,
            id_wali_kelas INT DEFAULT NULL,
            FOREIGN KEY (id_wali_kelas) REFERENCES tbl_guru(id) ON DELETE SET NULL,
            INDEX idx_nama_kelas (nama_kelas),
            INDEX idx_wali_kelas (id_wali_kelas)
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_kelas berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_kelas";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_kelas berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
