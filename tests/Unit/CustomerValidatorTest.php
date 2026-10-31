<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\CustomerValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi pelanggan — aturan domain tanpa DB (§14 DOC: validasi teruji).
 */
final class CustomerValidatorTest extends TestCase
{
    private CustomerValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CustomerValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'CV Maju Jaya',
            'type' => 'perusahaan',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'orders@majujaya.test',
            'address' => 'Jl. Melati 1',
            'city' => 'Bandung',
            'notes' => '',
        ], $overrides);
    }

    public function testValidDataProducesNoErrors(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid()));
    }

    public function testEmptyNameIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['name' => '   ']));
        $this->assertArrayHasKey('name', $errors);
    }

    public function testInvalidTypeIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['type' => 'toko', 'contact_person' => '']));
        $this->assertArrayHasKey('type', $errors);
    }

    public function testPerusahaanRequiresContactPerson(): void
    {
        $errors = $this->validator->validate($this->valid(['type' => 'perusahaan', 'contact_person' => '']));
        $this->assertArrayHasKey('contact_person', $errors);
    }

    public function testPeroranganDoesNotRequireContactPerson(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid(['type' => 'perorangan', 'contact_person' => ''])));
    }

    public function testPhoneMustBeNumericish(): void
    {
        $errors = $this->validator->validate($this->valid(['type' => 'perorangan', 'phone' => 'abc']));
        $this->assertArrayHasKey('phone', $errors);
    }

    public function testBadEmailIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['email' => 'bukan-email']));
        $this->assertArrayHasKey('email', $errors);
    }

    public function testEmptyEmailIsAllowed(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid(['type' => 'perorangan', 'email' => ''])));
    }
}
