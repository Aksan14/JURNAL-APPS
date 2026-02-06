<?php
/**
 * Migration: Create Views
 * Views untuk rekap dan laporan
 */

require_once __DIR__ . '/Migration.php';

class CreateViews extends Migration
{
    public function up()
    {
        // View: v_jadwal_belum_isi_jurnal
        $sql1 = "CREATE OR REPLACE VIEW v_jadwal_belum_isi_jurnal AS
            SELECT 
                m.id as id_mengajar,
                m.hari,
                m.jam_ke as jam_jadwal,
                g.id as id_guru,
                g.nama_guru,
                g.nip,
                k.id as id_kelas,
                k.nama_kelas,
                mp.id as id_mapel,
                mp.nama_mapel,
                CURDATE() as tanggal
            FROM tbl_mengajar m
            JOIN tbl_guru g ON m.id_guru = g.id
            JOIN tbl_kelas k ON m.id_kelas = k.id
            JOIN tbl_mapel mp ON m.id_mapel = mp.id
            WHERE m.hari = CASE DAYOFWEEK(CURDATE())
                WHEN 2 THEN 'Senin'
                WHEN 3 THEN 'Selasa'
                WHEN 4 THEN 'Rabu'
                WHEN 5 THEN 'Kamis'
                WHEN 6 THEN 'Jumat'
                WHEN 7 THEN 'Sabtu'
                ELSE NULL
            END
            AND m.id NOT IN (
                SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = CURDATE()
            )";

        // View: v_rekap_jurnal_guru
        $sql2 = "CREATE OR REPLACE VIEW v_rekap_jurnal_guru AS
            SELECT 
                g.id as id_guru,
                g.nama_guru,
                g.nip,
                YEAR(j.tanggal) as tahun,
                MONTH(j.tanggal) as bulan,
                COUNT(j.id) as total_jurnal,
                SUM(
                    CASE 
                        WHEN j.jam_ke LIKE '%-%' THEN 
                            CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                            CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
                        ELSE 1
                    END
                ) as total_jam
            FROM tbl_guru g
            LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
            LEFT JOIN tbl_jurnal j ON m.id = j.id_mengajar
            WHERE j.id IS NOT NULL
            GROUP BY g.id, g.nama_guru, g.nip, YEAR(j.tanggal), MONTH(j.tanggal)";

        $success = true;
        
        if ($this->execute($sql1)) {
            echo "✓ View v_jadwal_belum_isi_jurnal berhasil dibuat\n";
        } else {
            $success = false;
        }

        if ($this->execute($sql2)) {
            echo "✓ View v_rekap_jurnal_guru berhasil dibuat\n";
        } else {
            $success = false;
        }

        return $success;
    }

    public function down()
    {
        $sql1 = "DROP VIEW IF EXISTS v_jadwal_belum_isi_jurnal";
        $sql2 = "DROP VIEW IF EXISTS v_rekap_jurnal_guru";
        
        $this->execute($sql1);
        $this->execute($sql2);
        
        echo "✓ Views berhasil dihapus\n";
        return true;
    }
}
