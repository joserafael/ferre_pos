<?php

namespace App\Controllers\Api;

use App\Libraries\RedisService;
use App\Models\ProductoModel;
use App\Models\UnidadMedidaModel;
use CodeIgniter\HTTP\ResponseInterface;

class PosController extends ApiController
{
    /**
     * Reserva temporalmente stock para los ítems de un carrito en POS.
     * Evita sobreventa en entornos multi-caja.
     */
    public function reservarStock(): ResponseInterface
    {
        $payload   = $this->getRequestPayload();
        $carritoId = trim((string) ($payload['carrito_id'] ?? ''));
        $ttl       = max(60, min(3600, (int) ($payload['ttl'] ?? 600))); // entre 1 min y 60 min, defecto 10 min
        $items     = $payload['items'] ?? [];

        if ($carritoId === '') {
            return $this->apiError('El identificador del carrito (carrito_id) es obligatorio.', 422, 'CART_ID_REQUIRED');
        }

        if (!is_array($items) || empty($items)) {
            return $this->apiError('La lista de ítems a reservar no puede estar vacía.', 422, 'EMPTY_ITEMS');
        }

        $productoModel = new ProductoModel();
        $unidadModel   = new UnidadMedidaModel();

        // Validar que cada producto exista, esté activo y tenga stock suficiente considerando reservas de otras cajas
        foreach ($items as $idx => $it) {
            $pId = (int) ($it['producto_id'] ?? 0);
            $cant = round((float) ($it['cantidad'] ?? 0), 3);

            if ($pId <= 0 || $cant <= 0) {
                return $this->apiError("Ítem #" . ($idx + 1) . ": producto y cantidad deben ser válidos y mayores a cero.", 422, 'INVALID_ITEM');
            }

            $prod = $productoModel->find($pId);
            if (!$prod || (int) $prod['activo'] !== 1) {
                return $this->apiError("El producto ID {$pId} no existe o está inactivo.", 404, 'PRODUCT_NOT_FOUND');
            }

            $unidadVenta = $unidadModel->find((int) $prod['unidad_venta_id']);
            if ($unidadVenta && (int) $unidadVenta['permite_decimales'] === 0 && fmod($cant, 1.0) !== 0.0) {
                return $this->apiError("El producto '{$prod['nombre']}' no permite cantidades fraccionadas.", 422, 'DECIMALS_NOT_ALLOWED');
            }

            $stockActual         = (float) $prod['stock_actual'];
            $stockReservadoOtros = RedisService::getReservedStockExcludingCart($pId, $carritoId);
            $stockDisponible     = max(0.000, round($stockActual - $stockReservadoOtros, 3));

            if ($cant > $stockDisponible) {
                return $this->apiError(
                    "Stock insuficiente para reservar '{$prod['nombre']}'. Disponible: {$stockDisponible} (Stock físico: {$stockActual}, Reservado en otras cajas: {$stockReservadoOtros}), Solicitado: {$cant}.",
                    422,
                    'INSUFFICIENT_STOCK'
                );
            }
        }

        $resultado = RedisService::reserveCartStock($carritoId, $items, $ttl);
        if (empty($resultado['success'])) {
            return $this->apiError('Error al procesar la reserva temporal en Redis: ' . ($resultado['error'] ?? 'desconocido'), 500, 'REDIS_ERROR');
        }

        return $this->apiResponse($resultado, 'Stock reservado temporalmente para el carrito.', 200);
    }

    /**
     * Libera la reserva de stock si el cajero cancela la compra o vacía el carrito.
     */
    public function liberarStock(): ResponseInterface
    {
        $payload   = $this->getRequestPayload();
        $carritoId = trim((string) ($payload['carrito_id'] ?? ''));

        if ($carritoId === '') {
            return $this->apiError('El identificador del carrito (carrito_id) es obligatorio.', 422, 'CART_ID_REQUIRED');
        }

        $ok = RedisService::releaseCartStock($carritoId);

        return $this->apiResponse(['liberado' => $ok, 'carrito_id' => $carritoId], 'Reserva de stock liberada correctamente.');
    }

    /**
     * Consulta el stock en tiempo real (físico, reservado en cajas y disponible para venta).
     */
    public function stockDisponible($productoId = null): ResponseInterface
    {
        $productoModel = new ProductoModel();
        $prod          = $productoModel->find($productoId);

        if (!$prod) {
            return $this->apiError('Producto no encontrado.', 404, 'NOT_FOUND');
        }

        $stockActual    = (float) $prod['stock_actual'];
        $stockReservado = RedisService::getReservedStock((int) $productoId);
        $stockDisp      = max(0.000, round($stockActual - $stockReservado, 3));

        return $this->apiResponse([
            'producto_id'      => (int) $prod['id'],
            'sku'              => $prod['sku'],
            'nombre'           => $prod['nombre'],
            'stock_actual'     => $stockActual,
            'stock_reservado'  => $stockReservado,
            'stock_disponible' => $stockDisp,
        ], 'Stock consultado en tiempo real.');
    }
}
