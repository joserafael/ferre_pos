<?php

namespace Tests\Unit;

use App\Libraries\JwtLib;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class JwtLibTest extends CIUnitTestCase
{
    private JwtLib $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtLib();
    }

    public function testEncodeAndDecodeSuccess(): void
    {
        $payload = [
            'uid'      => 42,
            'username' => 'testuser',
            'rol'      => 'ADMIN',
        ];

        $token = $this->jwt->encode($payload);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $decoded = $this->jwt->decode($token);
        $this->assertSame(42, $decoded['uid']);
        $this->assertSame('testuser', $decoded['username']);
        $this->assertSame('ADMIN', $decoded['rol']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testDecodeThrowsExceptionOnExpiredToken(): void
    {
        $payload = [
            'uid' => 1,
            'exp' => time() - 3600, // Expired 1 hour ago
        ];

        $token = $this->jwt->encode($payload);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El token JWT ha expirado.');
        $this->jwt->decode($token);
    }

    public function testDecodeThrowsExceptionOnTamperedSignature(): void
    {
        $payload = ['uid' => 1, 'rol' => 'CAJERO'];
        $token   = $this->jwt->encode($payload);

        // Alter payload segment
        $parts = explode('.', $token);
        $parts[1] = base64_encode(json_encode(['uid' => 1, 'rol' => 'ADMIN']));
        $tamperedToken = implode('.', $parts);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Firma de token JWT inválida.');
        $this->jwt->decode($tamperedToken);
    }

    public function testDecodeThrowsExceptionOnInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Formato de token JWT inválido.');
        $this->jwt->decode('invalid.token');
    }
}

