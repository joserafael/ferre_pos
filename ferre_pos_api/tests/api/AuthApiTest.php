<?php

namespace Tests\Api;

use App\Database\Seeds\InitialFerrePosSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AuthApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialFerrePosSeeder::class;

    public function testLoginSuccessAdmin(): void
    {
        $result = $this->withBody(json_encode([
            'username' => 'admin',
            'password' => 'admin123',
        ]))->withHeaders([
            'Content-Type' => 'application/json',
        ])->post('api/auth/login');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertNotNull($json);
        $this->assertSame(200, $json['status']);
        $this->assertArrayHasKey('token', $json['data']);
        $this->assertSame('ADMIN', $json['data']['usuario']['rol']);
        $this->assertSame('admin', $json['data']['usuario']['username']);
    }

    public function testLoginSuccessCajero(): void
    {
        $result = $this->withBody(json_encode([
            'username' => 'cajero',
            'password' => 'cajero123',
        ]))->withHeaders([
            'Content-Type' => 'application/json',
        ])->post('api/auth/login');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame(200, $json['status']);
        $this->assertSame('CAJERO', $json['data']['usuario']['rol']);
    }

    public function testLoginInvalidPassword(): void
    {
        $result = $this->withBody(json_encode([
            'username' => 'admin',
            'password' => 'wrongpassword',
        ]))->withHeaders([
            'Content-Type' => 'application/json',
        ])->post('api/auth/login');

        $result->assertStatus(401);
    }

    public function testMeEndpointRequiresToken(): void
    {
        $result = $this->get('api/auth/me');
        $result->assertStatus(401);
    }

    public function testMeEndpointWithValidToken(): void
    {
        // First login
        $loginResult = $this->withBody(json_encode([
            'username' => 'admin',
            'password' => 'admin123',
        ]))->withHeaders([
            'Content-Type' => 'application/json',
        ])->post('api/auth/login');

        $token = json_decode($loginResult->getJSON(), true)['data']['token'];

        // Access me
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('api/auth/me');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('admin', $json['data']['usuario']['username']);
    }
}
