<?php

namespace App\Controllers\Api;

use App\Models\CategoriaModel;
use CodeIgniter\HTTP\ResponseInterface;

class CategoriaController extends ApiController
{
    public function index(): ResponseInterface
    {
        $soloActivas = (bool) $this->request->getGet('activas');
        $builder     = \Config\Database::connect()->table('categorias c')
            ->select('c.*, COUNT(p.id) as total_productos')
            ->join('productos p', 'p.categoria_id = c.id', 'left')
            ->groupBy('c.id')
            ->orderBy('c.nombre', 'ASC');

        if ($soloActivas) {
            $builder->where('c.activo', 1);
        }

        $rows = $builder->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['id']              = (int) $r['id'];
            $r['activo']          = (int) $r['activo'];
            $r['total_productos'] = (int) $r['total_productos'];
        }

        return $this->apiResponse($rows, 'Categorías obtenidas.');
    }

    public function create(): ResponseInterface
    {
        $payload     = $this->getRequestPayload();
        $nombre      = trim((string) ($payload['nombre'] ?? ''));
        $descripcion = trim((string) ($payload['descripcion'] ?? ''));
        $activo      = isset($payload['activo']) ? (int) (bool) $payload['activo'] : 1;

        if ($nombre === '') {
            return $this->apiError('El nombre de la categoría es obligatorio.', 422, 'VALIDATION_ERROR');
        }

        $model = new CategoriaModel();
        if ($model->where('nombre', $nombre)->first()) {
            return $this->apiError('Ya existe una categoría con ese nombre.', 422, 'DUPLICATE_CATEGORY');
        }

        $id = $model->insert([
            'nombre'      => $nombre,
            'descripcion' => $descripcion ?: null,
            'activo'      => $activo,
        ], true);

        return $this->apiResponse($model->find($id), 'Categoría creada exitosamente.', 201);
    }

    public function update($id = null): ResponseInterface
    {
        $model = new CategoriaModel();
        $cat   = $model->find($id);
        if (!$cat) {
            return $this->apiError('Categoría no encontrada.', 404, 'NOT_FOUND');
        }

        $payload     = $this->getRequestPayload();
        $nombre      = trim((string) ($payload['nombre'] ?? $cat['nombre']));
        $descripcion = isset($payload['descripcion']) ? trim((string) $payload['descripcion']) : $cat['descripcion'];
        $activo      = isset($payload['activo']) ? (int) (bool) $payload['activo'] : (int) $cat['activo'];

        if ($nombre === '') {
            return $this->apiError('El nombre de la categoría es obligatorio.', 422, 'VALIDATION_ERROR');
        }

        $dup = $model->where('nombre', $nombre)->where('id !=', $id)->first();
        if ($dup) {
            return $this->apiError('Otra categoría ya utiliza ese nombre.', 422, 'DUPLICATE_CATEGORY');
        }

        $model->update($id, [
            'nombre'      => $nombre,
            'descripcion' => $descripcion ?: null,
            'activo'      => $activo,
        ]);

        return $this->apiResponse($model->find($id), 'Categoría actualizada exitosamente.');
    }

    public function delete($id = null): ResponseInterface
    {
        $model = new CategoriaModel();
        $cat   = $model->find($id);
        if (!$cat) {
            return $this->apiError('Categoría no encontrada.', 404, 'NOT_FOUND');
        }

        $db    = \Config\Database::connect();
        $enUso = $db->table('productos')->where('categoria_id', $id)->countAllResults();
        if ($enUso > 0) {
            return $this->apiError('No se puede eliminar la categoría porque tiene productos asociados.', 409, 'CATEGORY_IN_USE');
        }

        $model->delete($id);
        return $this->apiResponse(['id' => (int) $id], 'Categoría eliminada exitosamente.');
    }
}
