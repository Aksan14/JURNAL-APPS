<?php
/**
 * Migration: Create tbl_guru
 * Tabel untuk menyimpan data profil guru
 */

require_once __DIR__ . '/Migration.php';

class CreateTblGuru extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_guru (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            nip VARCHAR(30) DEFAULT NULL,
            nama_guru VARCHAR(100) NOT NULL,
            foto VARCHAR(255) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL,
            INDEX idx_nip (nip),
            INDEX idx_nama_guru (nama_guru),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_guru berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_guru";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_guru berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
