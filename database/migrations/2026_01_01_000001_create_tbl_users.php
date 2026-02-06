<?php
/**
 * Migration: Create tbl_users
 * Tabel untuk menyimpan data autentikasi pengguna
 */

require_once __DIR__ . '/Migration.php';

class CreateTblUsers extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'guru', 'walikelas', 'siswa', 'kepsek') NOT NULL DEFAULT 'guru',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role)
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_users berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_users";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_users berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
