<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Delivery;
use App\Support\DeliveryFlow;
use PHPUnit\Framework\TestCase;

/**
 * Mesin status pengiriman (§13 DOC: status pengiriman teruji). Murni — tanpa DB.
 * Menutup matriks transisi (§9): Disiapkan→Dikirim→Diterima, cabang Batal dari
 * state non-akhir, serta status akhir yang terkunci.
 */
final class DeliveryFlowTest extends TestCase
{
    public function testDisiapkanCanGoToDikirim(): void
    {
        self::assertTrue(DeliveryFlow::canTransition(Delivery::STATUS_DISIAPKAN, Delivery::STATUS_DIKIRIM));
    }

    public function testDisiapkanCanGoToBatal(): void
    {
        self::assertTrue(DeliveryFlow::canTransition(Delivery::STATUS_DISIAPKAN, Delivery::STATUS_BATAL));
    }

    public function testDisiapkanCannotSkipToDiterima(): void
    {
        self::assertFalse(DeliveryFlow::canTransition(Delivery::STATUS_DISIAPKAN, Delivery::STATUS_DITERIMA));
    }

    public function testDikirimCanGoToDiterima(): void
    {
        self::assertTrue(DeliveryFlow::canTransition(Delivery::STATUS_DIKIRIM, Delivery::STATUS_DITERIMA));
    }

    public function testDikirimCanGoToBatal(): void
    {
        self::assertTrue(DeliveryFlow::canTransition(Delivery::STATUS_DIKIRIM, Delivery::STATUS_BATAL));
    }

    public function testDikirimCannotGoBackToDisiapkan(): void
    {
        self::assertFalse(DeliveryFlow::canTransition(Delivery::STATUS_DIKIRIM, Delivery::STATUS_DISIAPKAN));
    }

    public function testDiterimaIsFinal(): void
    {
        self::assertSame([], DeliveryFlow::nextStates(Delivery::STATUS_DITERIMA));
        self::assertTrue(DeliveryFlow::isFinal(Delivery::STATUS_DITERIMA));
    }

    public function testBatalIsFinal(): void
    {
        self::assertSame([], DeliveryFlow::nextStates(Delivery::STATUS_BATAL));
        self::assertTrue(DeliveryFlow::isFinal(Delivery::STATUS_BATAL));
    }

    public function testBatalCannotRevive(): void
    {
        self::assertFalse(DeliveryFlow::canTransition(Delivery::STATUS_BATAL, Delivery::STATUS_DISIAPKAN));
    }

    public function testNonFinalAreNotFinal(): void
    {
        self::assertFalse(DeliveryFlow::isFinal(Delivery::STATUS_DISIAPKAN));
        self::assertFalse(DeliveryFlow::isFinal(Delivery::STATUS_DIKIRIM));
    }

    public function testNextStatesFromDisiapkan(): void
    {
        $next = DeliveryFlow::nextStates(Delivery::STATUS_DISIAPKAN);
        self::assertContains(Delivery::STATUS_DIKIRIM, $next);
        self::assertContains(Delivery::STATUS_BATAL, $next);
        self::assertNotContains(Delivery::STATUS_DITERIMA, $next);
    }

    public function testRejectionMessageContainsLabels(): void
    {
        $msg = DeliveryFlow::rejectionMessage(Delivery::STATUS_DIKIRIM, Delivery::STATUS_DISIAPKAN);
        self::assertStringContainsString('Dikirim', $msg);
        self::assertStringContainsString('Disiapkan', $msg);
    }
}
