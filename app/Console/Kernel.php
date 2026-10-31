<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Application;
use RuntimeException;
use Throwable;

/**
 * Dispatcher CLI: registri command eksplisit + parsing argumen + exit code.
 *
 * Command yang belum dibangun TIDAK didaftarkan — `list` hanya menampilkan
 * yang benar-benar berfungsi (aturan §5 DOC: tidak ada command palsu).
 */
final class Kernel
{
    /**
     * Command aktif per fase implementasi.
     *
     * @var array<string, class-string<BaseCommand>>
     */
    private array $commands = [
        // Fase 1 — introspeksi dasar
        'list' => Commands\ListCommand::class,
        'help' => Commands\HelpCommand::class,

        // Fase 2 — introspeksi aplikasi
        'route:list' => Commands\RouteListCommand::class,
        'cache:clear' => Commands\CacheClearCommand::class,
        'test' => Commands\TestCommand::class,

        // Fase 2 — migrasi & seed
        'migrate' => Commands\MigrateCommand::class,
        'migrate:status' => Commands\MigrateStatusCommand::class,
        'migrate:rollback' => Commands\MigrateRollbackCommand::class,
        'db:seed' => Commands\DbSeedCommand::class,

        // Fase 2 — generator
        'make:controller' => Commands\MakeControllerCommand::class,
        'make:model' => Commands\MakeModelCommand::class,
        'make:service' => Commands\MakeServiceCommand::class,
        'make:middleware' => Commands\MakeMiddlewareCommand::class,
        'make:validator' => Commands\MakeValidatorCommand::class,
        'make:command' => Commands\MakeCommandCommand::class,
        'make:migration' => Commands\MakeMigrationCommand::class,
        'make:seeder' => Commands\MakeSeederCommand::class,
    ];

    private ?Application $app = null;

    public function __construct(private readonly string $basePath)
    {
    }

    public function basePath(string $append = ''): string
    {
        return $this->basePath . ($append !== '' ? '/' . ltrim($append, '/') : '');
    }

    /** @return array<string, class-string<BaseCommand>> */
    public function commands(): array
    {
        $sorted = $this->commands;
        ksort($sorted);
        return $sorted;
    }

    /**
     * Boot aplikasi HTTP sekali (untuk route:list & akses Database).
     * Session TIDAK started di jalur CLI, jadi ini aman.
     */
    public function app(): Application
    {
        if ($this->app === null) {
            $app = require $this->basePath('/bootstrap/app.php');
            if (!$app instanceof Application) {
                throw new RuntimeException('bootstrap/app.php tidak mengembalikan Application.');
            }
            $this->app = $app;
        }
        return $this->app;
    }

    /**
     * @param list<string> $argv argv penuh (index 0 = script)
     */
    public function run(array $argv): int
    {
        $io = new ConsoleIO();
        array_shift($argv);
        $name = $argv[0] ?? 'list';
        $args = array_slice($argv, 1);

        if ($name === '-v' || $name === '--version') {
            $io->line('JahitFlow console — PHP ' . PHP_VERSION);
            return 0;
        }

        if ($name === '-h' || $name === '--help') {
            $name = 'list';
        }

        if (!isset($this->commands[$name])) {
            $io->error("Command [{$name}] tidak dikenal. Jalankan `php bin/console list`.");
            return 1;
        }

        $class = $this->commands[$name];
        $command = new $class($this);

        try {
            return $command->handle($args, $io);
        } catch (RuntimeException $e) {
            // Error yang diharapkan (argumen salah, dsb.) → pesan bersih.
            $io->error($e->getMessage());
            return 1;
        } catch (Throwable $e) {
            $io->error('Tidak terduga: ' . $e->getMessage());
            return 255;
        }
    }
}
