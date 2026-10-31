<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ProductionStageMachine;
use PHPUnit\Framework\TestCase;

/**
 * Mesin transisi tahap produksi (§13 DOC: unit test perubahan status produksi).
 * Murni — tanpa DB. Memastikan pipeline berurutan: tahap hanya maju bila tahap
 * sebelumnya selesai, dan status perintah diturunkan dari gabungan tahap.
 */
final class ProductionStageMachineTest extends TestCase
{
    public function testFirstPendingStageCanStart(): void
    {
        $statuses = [ProductionStageMachine::PENDING, ProductionStageMachine::PENDING];

        self::assertTrue(ProductionStageMachine::canStart($statuses, 0));
        self::assertNull(ProductionStageMachine::startReason($statuses, 0));
    }

    public function testCannotStartBeforePreviousStageDone(): void
    {
        $statuses = [
            ProductionStageMachine::IN_PROGRESS,
            ProductionStageMachine::PENDING,
        ];

        self::assertFalse(ProductionStageMachine::canStart($statuses, 1));
        self::assertSame(
            'Selesaikan tahap sebelumnya sebelum memulai tahap ini.',
            ProductionStageMachine::startReason($statuses, 1),
        );
    }

    public function testCannotStartAlreadyWorkedStage(): void
    {
        $statuses = [ProductionStageMachine::IN_PROGRESS];

        self::assertFalse(ProductionStageMachine::canStart($statuses, 0));
        self::assertSame('Tahap ini sudah dimulai atau selesai.', ProductionStageMachine::startReason($statuses, 0));
    }

    public function testStartWithUnknownIndexIsRejected(): void
    {
        self::assertSame('Tahap tidak ditemukan.', ProductionStageMachine::startReason([], 0));
    }

    public function testCompleteRequiresInProgress(): void
    {
        $statuses = [
            ProductionStageMachine::PENDING,
            ProductionStageMachine::IN_PROGRESS,
            ProductionStageMachine::DONE,
        ];

        self::assertFalse(ProductionStageMachine::canComplete($statuses, 0));
        self::assertSame('Mulai tahap ini sebelum menandai selesai.', ProductionStageMachine::completeReason($statuses, 0));

        self::assertTrue(ProductionStageMachine::canComplete($statuses, 1));

        self::assertFalse(ProductionStageMachine::canComplete($statuses, 2));
        self::assertSame('Tahap ini sudah selesai.', ProductionStageMachine::completeReason($statuses, 2));
    }

    public function testReopenAllowedOnlyWhenNoLaterStageWorked(): void
    {
        $statuses = [
            ProductionStageMachine::DONE,
            ProductionStageMachine::PENDING,
        ];
        self::assertTrue(ProductionStageMachine::canReopen($statuses, 0));

        // Tahap berikutnya sudah dikerjakan → tidak boleh membuka lagi.
        $blocked = [
            ProductionStageMachine::DONE,
            ProductionStageMachine::IN_PROGRESS,
        ];
        self::assertFalse(ProductionStageMachine::canReopen($blocked, 0));
        self::assertSame(
            'Tidak dapat membuka tahap yang sudah selesai bila tahap berikutnya telah dikerjakan.',
            ProductionStageMachine::reopenReason($blocked, 0),
        );
    }

    public function testReopenRejectsNonDoneStage(): void
    {
        $statuses = [ProductionStageMachine::IN_PROGRESS];

        self::assertFalse(ProductionStageMachine::canReopen($statuses, 0));
        self::assertSame('Hanya tahap selesai yang dapat dibuka kembali.', ProductionStageMachine::reopenReason($statuses, 0));
    }

    public function testDeriveOrderStatus(): void
    {
        self::assertSame(
            'queued',
            ProductionStageMachine::deriveOrderStatus([
                ProductionStageMachine::PENDING,
                ProductionStageMachine::PENDING,
            ]),
        );

        self::assertSame(
            'running',
            ProductionStageMachine::deriveOrderStatus([
                ProductionStageMachine::DONE,
                ProductionStageMachine::IN_PROGRESS,
                ProductionStageMachine::PENDING,
            ]),
        );

        self::assertSame(
            'completed',
            ProductionStageMachine::deriveOrderStatus([
                ProductionStageMachine::DONE,
                ProductionStageMachine::DONE,
            ]),
        );
    }

    public function testIsCompleteRequiresEveryStageDone(): void
    {
        self::assertTrue(ProductionStageMachine::isComplete([
            ProductionStageMachine::DONE,
            ProductionStageMachine::DONE,
        ]));

        self::assertFalse(ProductionStageMachine::isComplete([
            ProductionStageMachine::DONE,
            ProductionStageMachine::PENDING,
        ]));

        self::assertFalse(ProductionStageMachine::isComplete([]));
    }
}
