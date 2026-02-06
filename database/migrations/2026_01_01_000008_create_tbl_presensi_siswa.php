<?php
/**
 * Migration: Create tbl_presensi_siswa
 * Tabel untuk menyimpan data kehadiran siswa per jurnal
 */

require_once __DIR__ . '/Migration.php';

class CreateTblPresensiSiswa extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_presensi_siswa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_jurnal INT NOT NULL,
            id_siswa INT NOT NULL,
            status_kehadiran ENUM('H', 'S', 'I', 'A') NOT NULL DEFAULT 'H' COMMENT 'H=Hadir, S=Sakit, I=Izin, A=Alpa',
            FOREIGN KEY (id_jurnal) REFERENCES tbl_jurnal(id) ON DELETE CASCADE,
            FOREIGN KEY (id_siswa) REFERENCES tbl_siswa(id) ON DELETE CASCADE,
            UNIQUE KEY uk_presensi (id_jurnal, id_siswa),
            INDEX idx_jurnal (id_jurnal),
            INDEX idx_siswa (id_siswa),
            INDEX idx_status (status_kehadiran)
        ) ENGINE=InnoDB";

        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_presensi_siswa berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP TABLE IF EXISTS tbl_presensi_siswa";
        if ($this->execute($sql)) {
            echo "✓ Tabel tbl_presensi_siswa berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
