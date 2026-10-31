<?php

declare(strict_types=1);

namespace App\Console;

/**
 * Kontrak command CLI internal.
 *
 * Setiap command nyata: tidak boleh hanya mencetak pesan sukses
 * tanpa melakukan fungsi yang dijanjikan (aturan §5 DOC).
 */
interface Command
{
    /** Signature contoh: "make:controller {name}". */
    public function signature(): string;

    public function description(): string;

    /**
     * Jalankan command.
     *
     * @param list<string> $args argumen mentah setelah nama command
     * @return int exit code (0 sukses, !=0 gagal)
     */
    public function handle(array $args, ConsoleIO $io): int;
}
