<?php

namespace App\Controllers\Api;

use App\Models\ClienteModel;
use CodeIgniter\HTTP\ResponseInterface;

class ClienteController extends ApiController
{
    public function index(): ResponseInterface
    {
        $q     = trim((string) $this->request->getGet('q'));
        $model = new ClienteModel();

        if ($q !== '') {
            $model->groupStart()
                ->like('documento', $q)
                ->orLike('nombre', $q)
                ->orLike('telefono', $q)
                ->groupEnd();
        }

        $clientes = $model->orderBy('id', 'DESC')->findAll(50);

        foreach ($clientes as &$c) {
            $c['id'] = (int) $c['id'];
        }

        return $this->apiResponse($clientes, 'Listado de clientes obtenido.');
    }

    public function buscar(): ResponseInterface
    {
        $documento = trim((string) $this->request->getGet('documento'));
        if ($documento === '') {
            return $this->apiError('Debe indicar la Cédula o RIF para buscar al cliente.', 422, 'DOCUMENT_REQUIRED');
        }

        $model   = new ClienteModel();
        $cliente = $model->buscarPorDocumento($documento);

        if (!$cliente) {
            return $this->apiResponse(null, 'Cliente no registrado aún.');
        }

        $cliente['id'] = (int) $cliente['id'];

        return $this->apiResponse($cliente, 'Cliente encontrado.');
    }

    public function create(): ResponseInterface
    {
        $payload   = $this->getRequestPayload();
        $documento = ClienteModel::normalizarDocumento((string) ($payload['documento'] ?? ''));

        if ($documento === '') {
            return $this->apiError('La Cédula o RIF es el único campo obligatorio para registrar al cliente.', 422, 'DOCUMENT_REQUIRED');
        }

        $model   = new ClienteModel();
        $cliente = $model->guardarPorDocumento([
            'documento' => $documento,
            'nombre'    => $payload['nombre'] ?? null,
            'telefono'  => $payload['telefono'] ?? null,
            'email'     => $payload['email'] ?? null,
            'direccion' => $payload['direccion'] ?? null,
        ]);

        $cliente['id'] = (int) $cliente['id'];

        return $this->apiResponse($cliente, 'Cliente guardado correctamente.', 201);
    }

    public function update($id = null): ResponseInterface
    {
        $model     = new ClienteModel();
        $existente = $model->find((int) $id);

        if (!$existente) {
            return $this->apiError('Cliente no encontrado.', 404, 'NOT_FOUND');
        }

        $payload   = $this->getRequestPayload();
        $documento = isset($payload['documento'])
            ? ClienteModel::normalizarDocumento((string) $payload['documento'])
            : $existente['documento'];

        if ($documento === '') {
            return $this->apiError('La Cédula o RIF es obligatoria.', 422, 'DOCUMENT_REQUIRED');
        }

        $duplicado = $model->where('documento', $documento)->where('id !=', (int) $id)->first();
        if ($duplicado) {
            return $this->apiError("Ya existe otro cliente con la Cédula/RIF {$documento}.", 422, 'DUPLICATE_DOCUMENT');
        }

        $model->update((int) $id, [
            'documento' => $documento,
            'nombre'    => isset($payload['nombre']) && trim((string) $payload['nombre']) !== '' ? trim((string) $payload['nombre']) : null,
            'telefono'  => isset($payload['telefono']) && trim((string) $payload['telefono']) !== '' ? trim((string) $payload['telefono']) : null,
            'email'     => isset($payload['email']) && trim((string) $payload['email']) !== '' ? trim((string) $payload['email']) : null,
            'direccion' => isset($payload['direccion']) && trim((string) $payload['direccion']) !== '' ? trim((string) $payload['direccion']) : null,
        ]);

        $actualizado       = $model->find((int) $id);
        $actualizado['id'] = (int) $actualizado['id'];

        return $this->apiResponse($actualizado, 'Datos del cliente actualizados.');
    }
}
