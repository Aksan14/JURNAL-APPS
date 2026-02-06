<?php
/**
 * Migration: Create tbl_mapel
 * Tabel untuk menyimpan data mata pelajaran
 */

require_once __DIR__ . '/Migration.php';

class CreateTblMapel extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_mapel (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kode_mapel VARCHAR(20) DEFAULT NULL,
            nama_mapel VARCHAR(100) NOT NULL,
            INDEX idx_kode_mapel (kode_mapel),
            INDEX idx_nama_mapel (nama_mapel)
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_mapel berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_mapel";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_mapel berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
