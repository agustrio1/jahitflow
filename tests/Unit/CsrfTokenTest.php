<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Keamanan CSRF (§12). Menguji pembuatan & verifikasi token pada Session —
 * hanya memakai superglobal $_SESSION (tanpa session_start/DB), sehingga
 * murni unit. Membuktikan: token stabil & acak, verifikasi timing-safe menerima
 * token benar dan MENOLAK token salah/kosong/null/sesi tanpa token.
 */
final class CsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function session(): Session
    {
        return new Session([]);
    }

    public function testTokenIsGeneratedStoredAndPersistent(): void
    {
        $session = $this->session();

        $token = $session->token();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token, 'token harus 32 byte hex');
        self::assertSame($token, $_SESSION[Session::TOKEN_KEY], 'token harus tersimpan di sesi');
        self::assertSame($token, $session->token(), 'token stabil antar-panggilan (tidak digenerate ulang)');
    }

    public function testValidTokenIsAccepted(): void
    {
        $session = $this->session();
        $token = $session->token();

        self::assertTrue($session->validateToken($token));
    }

    public function testWrongTokenIsRejected(): void
    {
        $session = $this->session();
        $session->token();

        self::assertFalse($session->validateToken('token-palsu-yang-salah'));
    }

    public function testSameLengthButDifferentTokenIsRejected(): void
    {
        $session = $this->session();
        $real = $session->token();
        // Panjang sama, isi beda — memastikan perbandingan benar-benar isi (timing-safe).
        $forged = str_repeat('0', strlen($real));

        self::assertNotSame($real, $forged);
        self::assertFalse($session->validateToken($forged));
    }

    public function testEmptyAndNullAreRejected(): void
    {
        $session = $this->session();
        $session->token();

        self::assertFalse($session->validateToken(''));
        self::assertFalse($session->validateToken(null));
    }

    public function testNoSessionTokenRejectsAnything(): void
    {
        $session = $this->session();
        // Sesi belum punya token (belum memanggil token()) → verifikasi apa pun ditolak.
        self::assertFalse($session->validateToken('apa-akun'));
        self::assertFalse($session->validateToken(str_repeat('0', 64)));
        self::assertFalse($session->validateToken(null));
    }

    public function testTokenDiffersAcrossFreshSessions(): void
    {
        $first = $this->session()->token();

        $_SESSION = []; // sesi baru
        $second = $this->session()->token();

        self::assertNotSame($first, $second, 'token tidak boleh berulang antar sesi');
    }
}
