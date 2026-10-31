<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\AuthorizationException;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductionOrderPolicy;
use App\Policies\QcPolicy;
use App\Policies\RawMaterialPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

/**
 * MATRIKS OTORISASI (§11, §12, §13). Membuktikan tiap gerbang Policy melempar
 * AuthorizationException (→ 403) untuk peran yang tidak diizinkan & meloloskan
 * peran yang berhak — termasuk guest (null). Penegakan nyata lewat gate(), bukan
 * sekadar penyembunyian tombol di UI. Murni — tanpa DB.
 */
final class PolicyAuthorizationTest extends TestCase
{
    /** Semua peran yang dikenal sistem. */
    private const ALL_ROLES = [
        User::ROLE_ADMIN,
        User::ROLE_MANAGER,
        User::ROLE_OPERATOR,
        User::ROLE_KEUANGAN,
    ];

    private function user(string $role): User
    {
        return new User(1, 'Pengguna Uji', 'uji@example.test', $role);
    }

    /**
     * Jalankan satu gerbang terhadap semua peran + guest.
     *
     * @param callable(?User): void $gate   pemanggilan method policy
     * @param list<string>          $allowed peran yang HARUS lolos
     */
    private function assertMatrix(callable $gate, array $allowed): void
    {
        foreach (self::ALL_ROLES as $role) {
            $expectAllowed = in_array($role, $allowed, true);
            $user = $this->user($role);

            if ($expectAllowed) {
                try {
                    $gate($user);
                } catch (AuthorizationException $e) {
                    self::fail("Peran [{$role}] seharusnya diizinkan, tapi ditolak: " . $e->getMessage());
                }
            } else {
                try {
                    $gate($user);
                    self::fail("Peran [{$role}] seharusnya ditolak (403).");
                } catch (AuthorizationException $e) {
                    self::assertNotSame('', $e->getMessage());
                }
            }
        }

        // Guest (belum login) selalu ditolak oleh gerbang mana pun.
        try {
            $gate(null);
            self::fail('Guest (null) seharusnya ditolak (403).');
        } catch (AuthorizationException) {
            // sesuai harapan
        }
    }

    // ---------------------------------------------------------------- Sales Order

    public function testSalesOrderViewOpenToAllLoggedIn(): void
    {
        $policy = new SalesOrderPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->viewAny($u), self::ALL_ROLES);
    }

    public function testSalesOrderCreateIsManagerial(): void
    {
        $policy = new SalesOrderPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->create($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        self::assertTrue($policy->allowsManage($this->user(User::ROLE_ADMIN)));
        self::assertTrue($policy->allowsManage($this->user(User::ROLE_MANAGER)));
        self::assertFalse($policy->allowsManage($this->user(User::ROLE_OPERATOR)));
        self::assertFalse($policy->allowsManage($this->user(User::ROLE_KEUANGAN)));
        self::assertFalse($policy->allowsManage(null));
    }

    // ---------------------------------------------------------------- Customer

    public function testCustomerViewIsManagerial(): void
    {
        $policy = new CustomerPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->viewAny($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        $this->assertMatrix(static fn(?User $u) => $policy->create($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
    }

    public function testCustomerSetStatusAdminOnly(): void
    {
        $policy = new CustomerPolicy();
        self::assertTrue($policy->allowsSetStatus($this->user(User::ROLE_ADMIN)));
        self::assertFalse($policy->allowsSetStatus($this->user(User::ROLE_MANAGER)));
        self::assertFalse($policy->allowsSetStatus($this->user(User::ROLE_OPERATOR)));
        self::assertFalse($policy->allowsSetStatus(null));
    }

    // ---------------------------------------------------------------- Product

    public function testProductViewOpenToAllLoggedIn(): void
    {
        $policy = new ProductPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->viewAny($u), self::ALL_ROLES);
    }

    public function testProductManageIsManagerialAndSetStatusAdminOnly(): void
    {
        $policy = new ProductPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->manage($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        self::assertFalse($policy->allowsSetStatus($this->user(User::ROLE_MANAGER)));
        self::assertTrue($policy->allowsSetStatus($this->user(User::ROLE_ADMIN)));
    }

    // ---------------------------------------------------------------- Production

    public function testProductionViewIncludesOperator(): void
    {
        $policy = new ProductionOrderPolicy();
        $this->assertMatrix(
            static fn(?User $u) => $policy->viewAny($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR],
        );
    }

    public function testProductionCreateIsManagerial(): void
    {
        $policy = new ProductionOrderPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->create($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        self::assertTrue($policy->allowsProgress($this->user(User::ROLE_OPERATOR)));
        self::assertFalse($policy->allowsManage($this->user(User::ROLE_OPERATOR)));
    }

    // ---------------------------------------------------------------- QC

    public function testQcRecordAllowedForFieldRoles(): void
    {
        $policy = new QcPolicy();
        $this->assertMatrix(
            static fn(?User $u) => $policy->create($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR],
        );
        self::assertTrue($policy->allowsRecord($this->user(User::ROLE_OPERATOR)));
        self::assertFalse($policy->allowsRecord($this->user(User::ROLE_KEUANGAN)));
    }

    // ---------------------------------------------------------------- Raw Material

    public function testRawMaterialViewIncludesOperatorButCreateIsManagerial(): void
    {
        $policy = new RawMaterialPolicy();
        $this->assertMatrix(
            static fn(?User $u) => $policy->viewAny($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR],
        );
        $this->assertMatrix(
            static fn(?User $u) => $policy->create($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER],
        );
    }

    public function testRawMaterialRecordOpenToFieldRoles(): void
    {
        $policy = new RawMaterialPolicy();
        $this->assertMatrix(
            static fn(?User $u) => $policy->record($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR],
        );
        self::assertTrue($policy->allowsRecord($this->user(User::ROLE_OPERATOR)));
        self::assertFalse($policy->allowsManage($this->user(User::ROLE_OPERATOR)));
    }

    // ---------------------------------------------------------------- Delivery

    public function testDeliveryIsManagerialDomain(): void
    {
        $policy = new DeliveryPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->viewAny($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        $this->assertMatrix(static fn(?User $u) => $policy->create($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        $this->assertMatrix(static fn(?User $u) => $policy->updateStatus($u), [User::ROLE_ADMIN, User::ROLE_MANAGER]);
        self::assertFalse($policy->allowsManage($this->user(User::ROLE_OPERATOR)));
    }

    // ---------------------------------------------------------------- Payment

    public function testPaymentIsFinanceDomain(): void
    {
        $policy = new PaymentPolicy();
        // Hanya admin & keuangan — bahkan Manajer tidak boleh mencatat uang.
        $this->assertMatrix(static fn(?User $u) => $policy->viewAny($u), [User::ROLE_ADMIN, User::ROLE_KEUANGAN]);
        $this->assertMatrix(static fn(?User $u) => $policy->create($u), [User::ROLE_ADMIN, User::ROLE_KEUANGAN]);
        self::assertTrue($policy->allowsRecord($this->user(User::ROLE_KEUANGAN)));
        self::assertFalse($policy->allowsRecord($this->user(User::ROLE_MANAGER)));
        self::assertFalse($policy->allowsRecord($this->user(User::ROLE_OPERATOR)));
    }

    // ---------------------------------------------------------------- Report

    public function testReportViewExcludesOperator(): void
    {
        $policy = new ReportPolicy();
        $this->assertMatrix(
            static fn(?User $u) => $policy->viewAny($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_KEUANGAN],
        );
        // Ekspor CSV memakai gerbang yang sama.
        $this->assertMatrix(
            static fn(?User $u) => $policy->export($u),
            [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_KEUANGAN],
        );
        self::assertFalse($policy->allowsView($this->user(User::ROLE_OPERATOR)));
        self::assertTrue($policy->allowsView($this->user(User::ROLE_KEUANGAN)));
    }

    // ---------------------------------------------------------------- User admin

    public function testUserManagementAdminOnly(): void
    {
        $policy = new UserPolicy();
        $this->assertMatrix(static fn(?User $u) => $policy->viewAny($u), [User::ROLE_ADMIN]);
        $this->assertMatrix(static fn(?User $u) => $policy->create($u), [User::ROLE_ADMIN]);
        $this->assertMatrix(static fn(?User $u) => $policy->delete($u), [User::ROLE_ADMIN]);
    }
}
