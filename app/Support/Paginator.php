<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pagination sederhana limit/offset untuk tabel operasional.
 * Pure value object — query tetap di repository, kelas ini hanya
 * menghitung jendela halaman agar view tidak berisi logika.
 */
final class Paginator
{
    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public static function for(int $total, int $page, int $perPage = 15): self
    {
        $page = max(1, min($page, max(1, (int) ceil($total / max(1, $perPage)))));
        return new self($total, $page, max(1, $perPage));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->lastPage();
    }

    /** Rentang baris yang ditampilkan, untuk label "Menampilkan 1-15 dari 87". */
    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->offset() + $this->perPage, $this->total);
    }
}
