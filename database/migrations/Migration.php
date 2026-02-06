<?php
/**
 * Base Migration Class
 * Kelas dasar untuk semua migration
 */

require_once __DIR__ . '/../../config.php';

abstract class Migration
{
    protected $pdo;
    protected $table = 'migrations';

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->createMigrationsTable();
    }

    /**
     * Buat tabel migrations jika belum ada
     */
    protected function createMigrationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";
        $this->pdo->exec($sql);
    }

    /**
     * Jalankan query SQL
     */
    protected function execute($sql)
    {
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Method yang harus diimplementasikan untuk menjalankan migration
     */
    abstract public function up();

    /**
     * Method yang harus diimplementasikan untuk rollback migration
     */
    abstract public function down();

    /**
     * Mendapatkan nama migration
     */
    public function getName()
    {
        return get_class($this);
    }
}
