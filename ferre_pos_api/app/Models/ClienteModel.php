<?php

namespace App\Models;

use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table            = 'clientes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $allowedFields    = [
        'documento',
        'nombre',
        'telefono',
        'email',
        'direccion',
        'created_at',
        'updated_at',
    ];

    /**
     * Normaliza la cédula o RIF (mayúsculas y sin espacios extremos).
     */
    public static function normalizarDocumento(string $documento): string
    {
        return strtoupper(trim($documento));
    }

    /**
     * Busca un cliente por su documento exacto o sin guiones/prefijo.
     */
    public function buscarPorDocumento(string $documento): ?array
    {
        $doc = self::normalizarDocumento($documento);
        if ($doc === '') {
            return null;
        }

        $exacto = $this->where('documento', $doc)->first();
        if ($exacto) {
            return $exacto;
        }

        // Búsqueda flexible si ingresan solo los dígitos (ej. 12345678 -> V-12345678)
        $soloAlfanumerico = preg_replace('/[^A-Z0-9]/', '', $doc);
        if ($soloAlfanumerico !== '') {
            $candidato = $this->like('documento', $soloAlfanumerico, 'both')->first();
            if ($candidato) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Crea o actualiza un cliente a partir de su documento (Cédula / RIF).
     * Solo el documento es obligatorio; los demás campos son opcionales.
     */
    public function guardarPorDocumento(array $datos): array
    {
        $documento = self::normalizarDocumento((string) ($datos['documento'] ?? ''));
        $nombre    = isset($datos['nombre']) && trim((string) $datos['nombre']) !== '' ? trim((string) $datos['nombre']) : null;
        $telefono  = isset($datos['telefono']) && trim((string) $datos['telefono']) !== '' ? trim((string) $datos['telefono']) : null;
        $email     = isset($datos['email']) && trim((string) $datos['email']) !== '' ? trim((string) $datos['email']) : null;
        $direccion = isset($datos['direccion']) && trim((string) $datos['direccion']) !== '' ? trim((string) $datos['direccion']) : null;

        $existente = $this->where('documento', $documento)->first();

        if ($existente) {
            $updateData = [
                'nombre'    => $nombre ?? $existente['nombre'],
                'telefono'  => $telefono ?? $existente['telefono'],
                'email'     => $email ?? $existente['email'],
                'direccion' => $direccion ?? $existente['direccion'],
            ];
            $this->update((int) $existente['id'], $updateData);
            return $this->find((int) $existente['id']);
        }

        $nuevoId = $this->insert([
            'documento' => $documento,
            'nombre'    => $nombre,
            'telefono'  => $telefono,
            'email'     => $email,
            'direccion' => $direccion,
        ], true);

        return $this->find((int) $nuevoId);
    }
}
