<?php
/**
 * Migration: Create Stored Procedures
 * Stored procedures untuk operasi database
 */

require_once __DIR__ . '/Migration.php';

class CreateStoredProcedures extends Migration
{
    public function up()
    {
        // Drop procedure jika sudah ada
        $this->execute("DROP PROCEDURE IF EXISTS sp_cek_guru_tidak_masuk");

        // Stored Procedure: sp_cek_guru_tidak_masuk
        $sql = "CREATE PROCEDURE sp_cek_guru_tidak_masuk(IN p_tanggal DATE)
            BEGIN
                DECLARE v_nama_hari VARCHAR(10);
                
                SET v_nama_hari = CASE DAYOFWEEK(p_tanggal)
                    WHEN 2 THEN 'Senin'
                    WHEN 3 THEN 'Selasa'
                    WHEN 4 THEN 'Rabu'
                    WHEN 5 THEN 'Kamis'
                    WHEN 6 THEN 'Jumat'
                    WHEN 7 THEN 'Sabtu'
                    ELSE NULL
                END;
                
                IF v_nama_hari IS NULL THEN
                    SELECT 'Hari Minggu - Libur' as pesan;
                ELSE
                    SELECT 
                        m.id as id_mengajar,
                        m.hari,
                        m.jam_ke as jam_jadwal,
                        g.id as id_guru,
                        g.nama_guru,
                        g.nip,
                        k.id as id_kelas,
                        k.nama_kelas,
                        mp.nama_mapel,
                        p_tanggal as tanggal,
                        'Belum Mengisi Jurnal' as status
                    FROM tbl_mengajar m
                    JOIN tbl_guru g ON m.id_guru = g.id
                    JOIN tbl_kelas k ON m.id_kelas = k.id
                    JOIN tbl_mapel mp ON m.id_mapel = mp.id
                    WHERE m.hari = v_nama_hari
                    AND m.id NOT IN (
                        SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = p_tanggal
                    )
                    ORDER BY k.nama_kelas, m.jam_ke;
                END IF;
            END";

        if ($this->execute($sql)) {
            echo "✓ Stored Procedure sp_cek_guru_tidak_masuk berhasil dibuat\n";
            return true;
        }
        return false;
    }

    public function down()
    {
        $sql = "DROP PROCEDURE IF EXISTS sp_cek_guru_tidak_masuk";
        if ($this->execute($sql)) {
            echo "✓ Stored Procedure sp_cek_guru_tidak_masuk berhasil dihapus\n";
            return true;
        }
        return false;
    }
}
