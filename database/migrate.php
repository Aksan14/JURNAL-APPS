<?php
/**
 * Migration Runner
 * Script untuk menjalankan migration database
 * 
 * Usage:
 *   php migrate.php              - Jalankan semua migration
 *   php migrate.php rollback     - Rollback batch terakhir
 *   php migrate.php rollback all - Rollback semua migration
 *   php migrate.php fresh        - Drop semua & jalankan ulang
 *   php migrate.php status       - Lihat status migration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

// Daftar migration dalam urutan eksekusi
$migrations = [
    '2026_01_01_000001_create_tbl_users.php' => 'CreateTblUsers',
    '2026_01_01_000002_create_tbl_mapel.php' => 'CreateTblMapel',
    '2026_01_01_000003_create_tbl_guru.php' => 'CreateTblGuru',
    '2026_01_01_000004_create_tbl_kelas.php' => 'CreateTblKelas',
    '2026_01_01_000005_create_tbl_siswa.php' => 'CreateTblSiswa',
    '2026_01_01_000006_create_tbl_mengajar.php' => 'CreateTblMengajar',
    '2026_01_01_000007_create_tbl_jurnal.php' => 'CreateTblJurnal',
    '2026_01_01_000008_create_tbl_presensi_siswa.php' => 'CreateTblPresensiSiswa',
    '2026_01_01_000009_create_tbl_request_jurnal_mundur.php' => 'CreateTblRequestJurnalMundur',
    '2026_01_01_000010_create_tbl_hari_libur.php' => 'CreateTblHariLibur',
    '2026_01_01_000011_create_tbl_jam_khusus.php' => 'CreateTblJamKhusus',
    '2026_01_01_000012_create_tbl_kehadiran_guru.php' => 'CreateTblKehadiranGuru',
    '2026_01_01_000013_create_views.php' => 'CreateViews',
    '2026_01_01_000014_create_stored_procedures.php' => 'CreateStoredProcedures',
    '2026_01_01_000015_seed_default_users.php' => 'SeedDefaultUsers',
];

/**
 * Buat tabel migrations jika belum ada
 */
function createMigrationsTable($pdo)
{
    $sql = "CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        batch INT NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    $pdo->exec($sql);
}

/**
 * Ambil migration yang sudah dijalankan
 */
function getExecutedMigrations($pdo)
{
    try {
        $stmt = $pdo->query("SELECT migration, batch FROM migrations ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Ambil batch terakhir
 */
function getLastBatch($pdo)
{
    try {
        $stmt = $pdo->query("SELECT MAX(batch) FROM migrations");
        return (int) $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Catat migration yang sudah dijalankan
 */
function recordMigration($pdo, $migration, $batch)
{
    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
    $stmt->execute([$migration, $batch]);
}

/**
 * Hapus record migration
 */
function removeMigration($pdo, $migration)
{
    $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration = ?");
    $stmt->execute([$migration]);
}

/**
 * Jalankan migration
 */
function runMigrations($pdo, $migrations)
{
    createMigrationsTable($pdo);
    $executed = getExecutedMigrations($pdo);
    $batch = getLastBatch($pdo) + 1;
    $count = 0;

    echo "\n========================================\n";
    echo "  MENJALANKAN MIGRATION\n";
    echo "========================================\n\n";

    foreach ($migrations as $file => $class) {
        if (isset($executed[$file])) {
            continue;
        }

        require_once __DIR__ . '/migrations/' . $file;
        $migration = new $class();
        
        echo "Migrating: $file\n";
        if ($migration->up()) {
            recordMigration($pdo, $file, $batch);
            $count++;
        } else {
            echo "✗ Gagal menjalankan: $file\n";
            break;
        }
    }

    echo "\n========================================\n";
    if ($count > 0) {
        echo "  ✓ $count migration berhasil dijalankan\n";
    } else {
        echo "  Tidak ada migration baru\n";
    }
    echo "========================================\n\n";
}

/**
 * Rollback migration
 */
function rollbackMigrations($pdo, $migrations, $all = false)
{
    createMigrationsTable($pdo);
    $executed = getExecutedMigrations($pdo);
    $lastBatch = getLastBatch($pdo);
    
    if ($lastBatch === 0) {
        echo "\nTidak ada migration untuk di-rollback\n";
        return;
    }

    echo "\n========================================\n";
    echo "  ROLLBACK MIGRATION\n";
    echo "========================================\n\n";

    // Balik urutan untuk rollback
    $reversedMigrations = array_reverse($migrations, true);
    $count = 0;

    foreach ($reversedMigrations as $file => $class) {
        if (!isset($executed[$file])) {
            continue;
        }

        // Jika tidak rollback semua, hanya rollback batch terakhir
        if (!$all && $executed[$file] != $lastBatch) {
            continue;
        }

        require_once __DIR__ . '/migrations/' . $file;
        $migration = new $class();
        
        echo "Rolling back: $file\n";
        if ($migration->down()) {
            removeMigration($pdo, $file);
            $count++;
        }
    }

    echo "\n========================================\n";
    echo "  ✓ $count migration berhasil di-rollback\n";
    echo "========================================\n\n";
}

/**
 * Fresh migration (drop semua & jalankan ulang)
 */
function freshMigrations($pdo, $migrations)
{
    echo "\n========================================\n";
    echo "  FRESH MIGRATION (Reset Database)\n";
    echo "========================================\n";
    echo "\n⚠ PERINGATAN: Semua data akan dihapus!\n";

    // Rollback semua
    rollbackMigrations($pdo, $migrations, true);
    
    // Drop tabel migrations
    $pdo->exec("DROP TABLE IF EXISTS migrations");
    
    // Jalankan ulang semua migration
    runMigrations($pdo, $migrations);
}

/**
 * Tampilkan status migration
 */
function showStatus($pdo, $migrations)
{
    createMigrationsTable($pdo);
    $executed = getExecutedMigrations($pdo);

    echo "\n========================================\n";
    echo "  STATUS MIGRATION\n";
    echo "========================================\n\n";

    printf("%-50s %-10s %-10s\n", "Migration", "Status", "Batch");
    echo str_repeat("-", 72) . "\n";

    foreach ($migrations as $file => $class) {
        if (isset($executed[$file])) {
            printf("%-50s %-10s %-10s\n", $file, "✓ Ran", $executed[$file]);
        } else {
            printf("%-50s %-10s %-10s\n", $file, "○ Pending", "-");
        }
    }

    echo "\n";
}

// Main execution
$command = $argv[1] ?? 'migrate';
$option = $argv[2] ?? '';

try {
    switch ($command) {
        case 'migrate':
            runMigrations($pdo, $migrations);
            break;
            
        case 'rollback':
            rollbackMigrations($pdo, $migrations, $option === 'all');
            break;
            
        case 'fresh':
            freshMigrations($pdo, $migrations);
            break;
            
        case 'status':
            showStatus($pdo, $migrations);
            break;
            
        default:
            echo "\nUsage:\n";
            echo "  php migrate.php              - Jalankan semua migration\n";
            echo "  php migrate.php rollback     - Rollback batch terakhir\n";
            echo "  php migrate.php rollback all - Rollback semua migration\n";
            echo "  php migrate.php fresh        - Drop semua & jalankan ulang\n";
            echo "  php migrate.php status       - Lihat status migration\n\n";
    }
} catch (PDOException $e) {
    echo "\n✗ Database Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
