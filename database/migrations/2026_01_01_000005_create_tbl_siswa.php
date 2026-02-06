<?php
/**
 * Migration: Create tbl_siswa
 * Tabel untuk menyimpan data siswa
 */

require_once __DIR__ . '/Migration.php';

class CreateTblSiswa extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_siswa (
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
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_siswa berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_siswa";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_siswa berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
