<?php
/**
 * Migration: Create tbl_request_jurnal_mundur
 * Tabel untuk menyimpan permintaan pengisian jurnal mundur dari guru
 */

require_once __DIR__ . '/Migration.php';

class CreateTblRequestJurnalMundur extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_request_jurnal_mundur (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_guru INT NOT NULL,
            id_mengajar INT NOT NULL,
            tanggal_jurnal DATE NOT NULL COMMENT 'Tanggal jurnal yang diminta mundur',
            alasan TEXT NOT NULL COMMENT 'Alasan permintaan jurnal mundur',
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            catatan_admin TEXT DEFAULT NULL COMMENT 'Catatan dari admin',
            notified_guru TINYINT(1) DEFAULT 0 COMMENT '0=belum dilihat guru, 1=sudah dilihat',
            approved_by INT DEFAULT NULL COMMENT 'ID admin yang memproses',
            approved_at DATETIME DEFAULT NULL COMMENT 'Waktu diproses',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_guru) REFERENCES tbl_guru(id) ON DELETE CASCADE,
            FOREIGN KEY (id_mengajar) REFERENCES tbl_mengajar(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES tbl_users(id) ON DELETE SET NULL,
            UNIQUE KEY uk_request (id_guru, id_mengajar, tanggal_jurnal, status),
            INDEX idx_guru (id_guru),
            INDEX idx_mengajar (id_mengajar),
            INDEX idx_status (status),
            INDEX idx_tanggal (tanggal_jurnal),
            INDEX idx_approved_by (approved_by)
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_request_jurnal_mundur berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_request_jurnal_mundur";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_request_jurnal_mundur berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
