<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\QcInspection;
use App\Validators\QcInspectionValidator;
use PHPUnit\Framework\TestCase;

/**
 * Aturan jumlah & konsistensi hasil pemeriksaan QC (§13 DOC: validasi teruji).
 * Murni — tanpa DB.
 */
final class QcInspectionValidatorTest extends TestCase
{
    private QcInspectionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new QcInspectionValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'production_order_id' => '1',
            'inspected_qty' => '50',
            'passed_qty' => '50',
            'failed_qty' => '0',
            'result' => QcInspection::RESULT_LOLOS,
            'defect_note' => '',
            'rework_action' => '',
        ], $overrides);
    }

    public function testValidLolosProducesNoErrors(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid()));
    }

    public function testValidPerbaikanProducesNoErrors(): void
    {
        $errors = $this->validator->validate($this->valid([
            'inspected_qty' => '10',
            'passed_qty' => '8',
            'failed_qty' => '2',
            'result' => QcInspection::RESULT_PERBAIKAN,
            'defect_note' => 'Jahitan lepas',
            'rework_action' => 'Jahit ulang',
        ]));
        $this->assertSame([], $errors);
    }

    public function testMissingProductionOrderIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['production_order_id' => '0']));
        $this->assertArrayHasKey('production_order_id', $errors);
    }

    public function testZeroInspectedIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['inspected_qty' => '0', 'passed_qty' => '0']));
        $this->assertArrayHasKey('inspected_qty', $errors);
    }

    public function testNonIntegerInspectedIsRejected(): void
    {
        foreach (['abc', '12,5', '-5'] as $bad) {
            $errors = $this->validator->validate($this->valid(['inspected_qty' => $bad]));
            $this->assertArrayHasKey('inspected_qty', $errors, "Input '{$bad}' seharusnya ditolak.");
        }
    }

    public function testPassedPlusFailedOverInspectedIsRejected(): void
    {
        // Diperiksa 10, lolos 7 + gagal 5 = 12 > 10.
        $errors = $this->validator->validate($this->valid([
            'inspected_qty' => '10',
            'passed_qty' => '7',
            'failed_qty' => '5',
            'result' => QcInspection::RESULT_PERBAIKAN,
            'defect_note' => 'cacat',
            'rework_action' => 'perbaiki',
        ]));
        $this->assertArrayHasKey('failed_qty', $errors);
    }

    public function testLolosWithFailedIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid([
            'failed_qty' => '3',
            'passed_qty' => '47',
            'result' => QcInspection::RESULT_LOLOS,
            'defect_note' => 'cacat',
        ]));
        $this->assertArrayHasKey('result', $errors);
    }

    public function testPerbaikanWithoutFailedIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid([
            'failed_qty' => '0',
            'result' => QcInspection::RESULT_PERBAIKAN,
            'rework_action' => 'perbaiki',
        ]));
        $this->assertArrayHasKey('result', $errors);
    }

    public function testFailedRequiresDefectNote(): void
    {
        $errors = $this->validator->validate($this->valid([
            'passed_qty' => '48',
            'failed_qty' => '2',
            'result' => QcInspection::RESULT_GAGAL,
            'defect_note' => '',
        ]));
        $this->assertArrayHasKey('defect_note', $errors);
    }

    public function testPerbaikanRequiresReworkAction(): void
    {
        $errors = $this->validator->validate($this->valid([
            'passed_qty' => '48',
            'failed_qty' => '2',
            'result' => QcInspection::RESULT_PERBAIKAN,
            'defect_note' => 'cacat',
            'rework_action' => '',
        ]));
        $this->assertArrayHasKey('rework_action', $errors);
    }

    public function testUnknownResultIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['result' => 'cacat_sedikit']));
        $this->assertArrayHasKey('result', $errors);
    }
}
