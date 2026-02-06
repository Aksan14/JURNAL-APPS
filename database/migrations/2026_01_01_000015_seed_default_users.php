<?php
/**
 * Migration: Seed Default Users
 * Menambahkan data admin dan kepsek default
 */

require_once __DIR__ . '/Migration.php';

class SeedDefaultUsers extends Migration
{
    public function up()
    {

        $sql = "INSERT INTO tbl_users (username, password_hash, role) VALUES 
            ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
            ('kepsek', '\$2y\$10\$nbmZa6UbB9/PldYpdtLnSOH89.sFjI4UHRNPY0O4z/w0iPvVxNKe6', 'kepsek')
            ON DUPLICATE KEY UPDATE username = username";

        if ($this->execute($sql)) {
            echo "✓ Data default users berhasil ditambahkan\n";
            echo "  - Username: admin | Password: password\n";
            echo "  - Username: kepsek | Password: kepsek123\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DELETE FROM tbl_users WHERE username IN ('admin', 'kepsek')";
        if ($this->execute($sql)) {
            echo "✓ Data default users berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
