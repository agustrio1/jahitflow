<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Entity produk katalog (§8.C). Varian & foto dimuat terpisah oleh
 * ProductRepository — model hanya memegang relasi ringkas.
 */
final class Product
{
    /** @param list<ProductVariant> $variants @param list<array<string, mixed>> $photos */
    public function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly string $categoryName,
        public readonly string $name,
        public readonly ?string $garmentType,
        public readonly ?string $description,
        public readonly ?string $materialSpec,
        public readonly ?string $customSpec,
        public readonly bool $isActive,
        public readonly array $variants = [],
        public readonly array $photos = [],
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            categoryId: (int) $row['category_id'],
            categoryName: (string) ($row['category_name'] ?? ''),
            name: (string) $row['name'],
            garmentType: $row['garment_type'] ?? null,
            description: $row['description'] ?? null,
            materialSpec: $row['material_spec'] ?? null,
            customSpec: $row['custom_spec'] ?? null,
            isActive: (bool) ($row['is_active'] ?? true),
        );
    }

    /** @param list<ProductVariant> $variants */
    public function withVariants(array $variants): self
    {
        return new self(
            $this->id, $this->categoryId, $this->categoryName, $this->name,
            $this->garmentType, $this->description, $this->materialSpec, $this->customSpec,
            $this->isActive, $variants, $this->photos,
        );
    }

    /** @param list<array<string, mixed>> $photos */
    public function withPhotos(array $photos): self
    {
        return new self(
            $this->id, $this->categoryId, $this->categoryName, $this->name,
            $this->garmentType, $this->description, $this->materialSpec, $this->customSpec,
            $this->isActive, $this->variants, $photos,
        );
    }

    /** Rentang harga varian aktif — "Rp75.000 - Rp125.000" atau "-" bila kosong. */
    public function priceRange(): string
    {
        $prices = array_map(static fn(ProductVariant $v): float => $v->unitPrice, array_filter(
            $this->variants,
            static fn(ProductVariant $v): bool => $v->isActive,
        ));

        if ($prices === []) {
            return '-';
        }

        $min = min($prices);
        $max = max($prices);

        return $min === $max ? money($min) : money($min) . ' - ' . money($max);
    }
}
