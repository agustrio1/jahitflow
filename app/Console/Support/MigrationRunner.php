<?php

declare(strict_types=1);

namespace App\Console\Support;

use App\Core\Migration;
use PDO;
use RuntimeException;

/**
 * Engine migration: urutan eksekusi, pencatatan batch, rollback LIFO.
 *
 * Tabel `migrations` menyimpan file yang sudah diterapkan + nomornya.
 * Setiap file migration adalah class bernama (extends App\Core\Migration)
 * yang di-require langsung; class dideteksi lewat selisih get_declared_classes
 * sehingga tidak bergantung pada konvensi pemetaan nama-file.
 */
final class MigrationRunner
{
    private const TABLE = 'migrations';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsPath,
    ) {
    }

    public function ensureRepository(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Semua file migration (tanpa .gitkeep), urut nama.
     *
     * @return list<string> nama file (basename)
     */
    public function files(): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];

        return array_values(array_map(static fn(string $p): string => basename($p), $files));
    }

    /** @return array<string, int> migration name => batch */
    public function applied(): array
    {
        $stmt = $this->pdo->query('SELECT migration, batch FROM `' . self::TABLE . '` ORDER BY id');
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['migration']] = (int) $row['batch'];
        }
        return $map;
    }

    /**
     * Terapkan semua migration yang belum berjalan.
     *
     * @param bool $pretend hanya daftar, tidak mengeksekusi
     * @return list<string> nama yang diterapkan
     */
    public function migrate(bool $pretend = false): array
    {
        $this->ensureRepository();

        $applied = $this->applied();
        $pending = array_values(array_filter($this->files(), static fn(string $f): bool => !isset($applied[$f])));

        if ($pending === [] || $pretend) {
            return $pending;
        }

        $batch = $this->nextBatch();
        $done = [];

        foreach ($pending as $name) {
            // DDL MySQL auto-commit; diproses satu per satu dan berhenti
            // pada kegagalan pertama (lihat catatan di App\Core\Migration).
            $this->resolveMigration($name)->up();

            $ins = $this->pdo->prepare('INSERT INTO `' . self::TABLE . '` (migration, batch) VALUES (?, ?)');
            $ins->execute([$name, $batch]);
            $done[] = $name;
        }

        return $done;
    }

    /**
     * Rollback batch terakhir (atau sejumlah `step` migration terbaru).
     * Urutan terbalik; DROP/destruktif dibiarkan ke migration masing-masing.
     *
     * @return list<string> nama yang di-rollback
     */
    public function rollback(?int $step = null): array
    {
        $this->ensureRepository();

        if ($step === null) {
            $maxBatch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch),0) FROM `' . self::TABLE . '`')->fetchColumn();
            if ($maxBatch === 0) {
                return [];
            }
            $stmt = $this->pdo->prepare('SELECT migration FROM `' . self::TABLE . '` WHERE batch = ? ORDER BY id DESC');
            $stmt->execute([$maxBatch]);
            $targets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $this->pdo->prepare('SELECT migration FROM `' . self::TABLE . '` ORDER BY id DESC LIMIT ' . max(1, $step));
            $targets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $rolled = [];
        foreach ($targets as $name) {
            $this->resolveMigration((string) $name)->down();
            $del = $this->pdo->prepare('DELETE FROM `' . self::TABLE . '` WHERE migration = ?');
            $del->execute([$name]);
            $rolled[] = (string) $name;
        }

        return $rolled;
    }

    /** @return list<array{name: string, applied: bool, batch: ?int}> */
    public function status(): array
    {
        $this->ensureRepository();
        $applied = $this->applied();

        $rows = [];
        foreach ($this->files() as $name) {
            $rows[] = [
                'name' => $name,
                'applied' => isset($applied[$name]),
                'batch' => $applied[$name] ?? null,
            ];
        }
        return $rows;
    }

    private function nextBatch(): int
    {
        $max = (int) $this->pdo->query('SELECT COALESCE(MAX(batch),0) FROM `' . self::TABLE . '`')->fetchColumn();
        return $max + 1;
    }

    /**
     * Require file dan pasang class Migration-nya.
     *
     * Nama class diturunkan dari filename (buang prefix timestamp, snake_case
     * -> StudlyCase), sehingga aman dipanggil berulang dengan require_once.
     */
    private function resolveMigration(string $name): Migration
    {
        $file = $this->migrationsPath . '/' . $name;
        if (!is_file($file)) {
            throw new RuntimeException("File migration [{$name}] tidak ditemukan.");
        }

        require_once $file;

        $class = $this->classNameFromFilename($name);
        if ($class !== null && class_exists($class) && is_subclass_of($class, Migration::class)) {
            return new $class($this->pdo);
        }

        throw new RuntimeException("File [{$name}] tidak memuat class extends App\\Core\\Migration (diharapkan: {$class}).");
    }

    /**
     * "2026_10_28_120000_create_users_table.php" -> "CreateUsersTable".
     */
    private function classNameFromFilename(string $name): ?string
    {
        $base = preg_replace('/\.php$/', '', $name) ?? $name;
        // Buang prefix timestamp YYYY_MM_DD_HHMMSS_ (tiga segmen angka pertama + waktu).
        $stripped = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $base) ?? $base;
        if ($stripped === '') {
            return null;
        }

        $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', $stripped)));
        return $studly;
    }
}
