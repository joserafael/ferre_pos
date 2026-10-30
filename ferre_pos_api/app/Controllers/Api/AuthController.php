<?php

namespace App\Controllers\Api;

use App\Libraries\JwtLib;
use App\Models\TasaCambioModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends ApiController
{
    public function login(): ResponseInterface
    {
        $payload  = $this->getRequestPayload();
        $identity = trim((string) ($payload['username'] ?? $payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identity === '' || $password === '') {
            return $this->apiError('Debe ingresar usuario y contraseña.', 422, 'VALIDATION_ERROR');
        }

        $userModel = new UsuarioModel();
        $user      = $userModel
            ->groupStart()
                ->where('username', $identity)
                ->orWhere('email', $identity)
            ->groupEnd()
            ->first();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->apiError('Credenciales inválidas.', 401, 'INVALID_CREDENTIALS');
        }

        if ((int) $user['activo'] !== 1) {
            return $this->apiError('El usuario se encuentra inactivo.', 403, 'USER_INACTIVE');
        }

        $jwt   = new JwtLib();
        $token = $jwt->encode([
            'uid'      => (int) $user['id'],
            'username' => $user['username'],
            'nombre'   => $user['nombre'],
            'rol'      => $user['rol'],
        ]);

        $tasaActiva = (new TasaCambioModel())->getActiva();

        return $this->apiResponse([
            'token'       => $token,
            'usuario'     => [
                'id'       => (int) $user['id'],
                'nombre'   => $user['nombre'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'rol'      => $user['rol'],
            ],
            'tasa_activa' => $tasaActiva,
        ], 'Inicio de sesión exitoso.');
    }

    public function me(): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $userModel  = new UsuarioModel();
        $user       = $userModel->find($auth['uid'] ?? 1);
        $tasaActiva = (new TasaCambioModel())->getActiva();

        if (!$user) {
            return $this->apiError('Usuario no encontrado.', 404, 'NOT_FOUND');
        }

        return $this->apiResponse([
            'usuario' => [
                'id'       => (int) $user['id'],
                'nombre'   => $user['nombre'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'rol'      => $user['rol'],
            ],
            'tasa_activa' => $tasaActiva,
        ], 'Sesión válida.');
    }
}
