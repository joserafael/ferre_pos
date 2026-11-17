<?php

namespace App\Libraries;

use Redis;
use Throwable;

class RedisService
{
    private static ?Redis $client = null;
    private static bool $connectionFailed = false;

    private const PREFIX = 'ferrepos:';

    private static function log(string $level, string $message): void
    {
        if (function_exists('log_message')) {
            self::log($level, $message);
        }
    }

    /**
     * Obtiene una instancia singleton conectada de Redis.
     * Si no se puede conectar, retorna null de forma segura sin abortar la app.
     */
    public static function getClient(): ?Redis
    {
        if (self::$connectionFailed) {
            return null;
        }

        if (self::$client !== null) {
            try {
                // Verificamos conexión viva
                if (self::$client->ping()) {
                    return self::$client;
                }
            } catch (Throwable $e) {
                self::$client = null;
            }
        }

        try {
            $redisConfig = function_exists('config') ? (config('Cache')->redis ?? []) : [];
            $host        = $redisConfig['host'] ?? (function_exists('env') ? env('REDIS_HOST', 'redis') : (getenv('REDIS_HOST') ?: 'redis'));
            $port        = (int) ($redisConfig['port'] ?? (function_exists('env') ? env('REDIS_PORT', 6379) : (getenv('REDIS_PORT') ?: 6379)));
            $password    = $redisConfig['password'] ?? (function_exists('env') ? env('REDIS_PASSWORD', null) : (getenv('REDIS_PASSWORD') ?: null));
            $database    = (int) ($redisConfig['database'] ?? 0);
            $timeout     = (float) ($redisConfig['timeout'] ?? 1.5);

            $redis = new Redis();
            $connected = $redis->connect($host, $port, $timeout);

            if (!$connected) {
                self::$connectionFailed = true;
                self::log('error', "RedisService: No se pudo conectar a {$host}:{$port}");
                return null;
            }

            if (!empty($password)) {
                $redis->auth($password);
            }

            if ($database > 0) {
                $redis->select($database);
            }

            self::$client = $redis;
            return self::$client;
        } catch (Throwable $e) {
            self::$connectionFailed = true;
            self::log('error', 'RedisService connection error: ' . $e->getMessage());
            return null;
        }
    }

    public static function isAvailable(): bool
    {
        return self::getClient() !== null;
    }

    // =========================================================================
    // CASO A: Tasa de Cambio Oficial (BCV / Tienda)
    // =========================================================================

    public static function getTasaActiva(): ?array
    {
        $redis = self::getClient();
        if (!$redis) {
            return null;
        }

        try {
            $data = $redis->get(self::PREFIX . 'tasa:activa');
            if ($data !== false && $data !== null) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Throwable $e) {
            self::log('warning', 'Redis getTasaActiva error: ' . $e->getMessage());
        }

        return null;
    }

    public static function setTasaActiva(array $tasa, int $ttl = 3600): bool
    {
        $redis = self::getClient();
        if (!$redis) {
            return false;
        }

        try {
            return (bool) $redis->setex(self::PREFIX . 'tasa:activa', $ttl, json_encode($tasa));
        } catch (Throwable $e) {
            self::log('warning', 'Redis setTasaActiva error: ' . $e->getMessage());
            return false;
        }
    }

    public static function invalidateTasa(): void
    {
        $redis = self::getClient();
        if (!$redis) {
            return;
        }

        try {
            $redis->del(self::PREFIX . 'tasa:activa');
        } catch (Throwable $e) {
            self::log('warning', 'Redis invalidateTasa error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CASO B: Reservas Temporales y Bloqueo Atómico de Stock (Multi-caja)
    // =========================================================================

    /**
     * Adquiere un cerrojo distribuido (Mutex) para un recurso.
     * Retorna el token de adquisición o null si ya estaba bloqueado.
     */
    public static function acquireLock(string $resource, int $ttlSeconds = 5): ?string
    {
        $redis = self::getClient();
        if (!$redis) {
            return 'fallback_no_redis_token';
        }

        $token = bin2hex(random_bytes(16));
        $key   = self::PREFIX . 'lock:' . $resource;

        try {
            $acquired = $redis->set($key, $token, ['NX', 'EX' => $ttlSeconds]);
            return $acquired ? $token : null;
        } catch (Throwable $e) {
            self::log('warning', "Redis acquireLock error for {$resource}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Libera un cerrojo distribuido de forma atómica usando script Lua,
     * garantizando que sólo el poseedor del token pueda liberarlo.
     */
    public static function releaseLock(string $resource, string $token): bool
    {
        $redis = self::getClient();
        if (!$redis || $token === 'fallback_no_redis_token') {
            return true;
        }

        $key    = self::PREFIX . 'lock:' . $resource;
        $script = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end';

        try {
            return (bool) $redis->eval($script, [$key, $token], 1);
        } catch (Throwable $e) {
            self::log('warning', "Redis releaseLock error for {$resource}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reserva temporalmente stock para los ítems de un carrito en POS.
     * $items = [ ['producto_id' => int, 'cantidad' => float], ... ]
     */
    public static function reserveCartStock(string $cartId, array $items, int $ttlSeconds = 600): array
    {
        $redis = self::getClient();
        if (!$redis) {
            return ['success' => true, 'cart_id' => $cartId, 'items' => $items, 'expires_in' => $ttlSeconds];
        }

        // Si ya existía una reserva para este carrito, la liberamos primero
        self::releaseCartStock($cartId);

        $cartKey       = self::PREFIX . 'reserve:cart:' . $cartId;
        $reservedItems = [];

        try {
            foreach ($items as $item) {
                $productoId = (int) ($item['producto_id'] ?? 0);
                $cantidad   = round((float) ($item['cantidad'] ?? 0), 3);

                if ($productoId <= 0 || $cantidad <= 0) {
                    continue;
                }

                $prodKey = self::PREFIX . 'reserve:prod:' . $productoId;
                $redis->incrByFloat($prodKey, $cantidad);

                $reservedItems[] = [
                    'producto_id' => $productoId,
                    'cantidad'    => $cantidad,
                ];
            }

            if (!empty($reservedItems)) {
                $payload = json_encode([
                    'cart_id'    => $cartId,
                    'items'      => $reservedItems,
                    'created_at' => time(),
                ]);
                $redis->setex($cartKey, $ttlSeconds, $payload);
            }

            return [
                'success'    => true,
                'cart_id'    => $cartId,
                'items'      => $reservedItems,
                'expires_in' => $ttlSeconds,
            ];
        } catch (Throwable $e) {
            self::log('error', "Redis reserveCartStock error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Libera la reserva de stock de un carrito (cancelación o abandono).
     */
    public static function releaseCartStock(string $cartId): bool
    {
        $redis = self::getClient();
        if (!$redis) {
            return true;
        }

        $cartKey = self::PREFIX . 'reserve:cart:' . $cartId;

        try {
            $data = $redis->get($cartKey);
            if ($data) {
                $cart = json_decode($data, true);
                if (!empty($cart['items']) && is_array($cart['items'])) {
                    foreach ($cart['items'] as $item) {
                        $productoId = (int) ($item['producto_id'] ?? 0);
                        $cantidad   = (float) ($item['cantidad'] ?? 0);
                        if ($productoId > 0 && $cantidad > 0) {
                            $prodKey = self::PREFIX . 'reserve:prod:' . $productoId;
                            $actual  = (float) $redis->incrByFloat($prodKey, -$cantidad);
                            if ($actual <= 0.0001) {
                                $redis->del($prodKey);
                            }
                        }
                    }
                }
                $redis->del($cartKey);
            }
            return true;
        } catch (Throwable $e) {
            self::log('error', "Redis releaseCartStock error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirma la reserva de stock al concretar la venta en base de datos.
     */
    public static function confirmCartStock(string $cartId): bool
    {
        return self::releaseCartStock($cartId);
    }

    /**
     * Obtiene la cantidad de stock actualmente reservada para un producto.
     */
    public static function getReservedStock(int $productoId): float
    {
        $redis = self::getClient();
        if (!$redis) {
            return 0.000;
        }

        try {
            $val = $redis->get(self::PREFIX . 'reserve:prod:' . $productoId);
            return $val !== false ? max(0.000, round((float) $val, 3)) : 0.000;
        } catch (Throwable $e) {
            return 0.000;
        }
    }

    /**
     * Obtiene la cantidad reservada para un producto excluyendo la reserva propia de un carrito.
     */
    public static function getReservedStockExcludingCart(int $productoId, ?string $cartId): float
    {
        $total = self::getReservedStock($productoId);
        if (empty($cartId) || $total <= 0) {
            return $total;
        }

        $redis = self::getClient();
        if (!$redis) {
            return $total;
        }

        try {
            $cartData = $redis->get(self::PREFIX . 'reserve:cart:' . $cartId);
            if ($cartData) {
                $cart = json_decode($cartData, true);
                if (!empty($cart['items']) && is_array($cart['items'])) {
                    foreach ($cart['items'] as $it) {
                        if ((int) ($it['producto_id'] ?? 0) === $productoId) {
                            $propia = (float) ($it['cantidad'] ?? 0);
                            $total  = max(0.000, $total - $propia);
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // En caso de error retornamos el total seguro
        }

        return round($total, 3);
    }

    // =========================================================================
    // CASO C: Catálogo Rápido y Búsquedas Frecuentes
    // =========================================================================

    public static function getCached(string $key)
    {
        $redis = self::getClient();
        if (!$redis) {
            return null;
        }

        try {
            $val = $redis->get(self::PREFIX . 'catalogo:' . $key);
            if ($val !== false && $val !== null) {
                return json_decode($val, true);
            }
        } catch (Throwable $e) {
            self::log('warning', "Redis getCached error for {$key}: " . $e->getMessage());
        }

        return null;
    }

    public static function setCached(string $key, $data, int $ttl = 1800): bool
    {
        $redis = self::getClient();
        if (!$redis) {
            return false;
        }

        try {
            return (bool) $redis->setex(self::PREFIX . 'catalogo:' . $key, $ttl, json_encode($data));
        } catch (Throwable $e) {
            self::log('warning', "Redis setCached error for {$key}: " . $e->getMessage());
            return false;
        }
    }

    public static function invalidateCatalog(): void
    {
        $redis = self::getClient();
        if (!$redis) {
            return;
        }

        try {
            $keys = $redis->keys(self::PREFIX . 'catalogo:*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (Throwable $e) {
            self::log('warning', 'Redis invalidateCatalog error: ' . $e->getMessage());
        }
    }

    public static function invalidateProducts(): void
    {
        $redis = self::getClient();
        if (!$redis) {
            return;
        }

        try {
            $keys = $redis->keys(self::PREFIX . 'catalogo:productos:*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (Throwable $e) {
            self::log('warning', 'Redis invalidateProducts error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CASO E: Rate Limiting y Revocación JWT (Blacklist)
    // =========================================================================

    /**
     * Aplica Rate Limiting con ventana de tiempo fija en Redis.
     * Retorna array con: allowed, limit, remaining, retry_after.
     */
    public static function checkRateLimit(string $identifier, int $maxRequests = 60, int $windowSeconds = 60): array
    {
        $redis = self::getClient();
        if (!$redis) {
            return [
                'allowed'     => true,
                'limit'       => $maxRequests,
                'remaining'   => $maxRequests - 1,
                'retry_after' => 0,
            ];
        }

        $key = self::PREFIX . 'ratelimit:' . $identifier;

        try {
            $current = $redis->incr($key);
            if ($current === 1) {
                $redis->expire($key, $windowSeconds);
            }

            $ttl = $redis->ttl($key);
            if ($ttl < 0) {
                $redis->expire($key, $windowSeconds);
                $ttl = $windowSeconds;
            }

            $allowed   = $current <= $maxRequests;
            $remaining = max(0, $maxRequests - $current);

            return [
                'allowed'     => $allowed,
                'limit'       => $maxRequests,
                'remaining'   => $remaining,
                'retry_after' => $allowed ? 0 : max(1, $ttl),
            ];
        } catch (Throwable $e) {
            self::log('warning', 'Redis checkRateLimit error: ' . $e->getMessage());
            return [
                'allowed'     => true,
                'limit'       => $maxRequests,
                'remaining'   => $maxRequests,
                'retry_after' => 0,
            ];
        }
    }

    /**
     * Añade un token JWT a la lista negra con el TTL restante de su validez.
     */
    public static function blacklistToken(string $token, int $ttlSeconds = 86400): bool
    {
        $redis = self::getClient();
        if (!$redis) {
            return false;
        }

        if ($ttlSeconds <= 0) {
            return true;
        }

        $tokenHash = hash('sha256', $token);
        $key       = self::PREFIX . 'jwt:blacklist:' . $tokenHash;

        try {
            return (bool) $redis->setex($key, $ttlSeconds, time());
        } catch (Throwable $e) {
            self::log('warning', 'Redis blacklistToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un token JWT está en la lista negra (revocado).
     */
    public static function isTokenBlacklisted(string $token): bool
    {
        $redis = self::getClient();
        if (!$redis) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $key       = self::PREFIX . 'jwt:blacklist:' . $tokenHash;

        try {
            return (bool) $redis->exists($key);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Invalida todas las sesiones de un usuario revocando tokens anteriores a este instante.
     */
    public static function revokeUserSessions(int $userId): bool
    {
        $redis = self::getClient();
        if (!$redis) {
            return false;
        }

        $key = self::PREFIX . 'jwt:user_revoked:' . $userId;

        try {
            // Guardamos timestamp de revocación válido por 7 días
            return (bool) $redis->setex($key, 604800, time());
        } catch (Throwable $e) {
            self::log('warning', 'Redis revokeUserSessions error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Comprueba si las sesiones de un usuario fueron revocadas después de la emisión del token.
     */
    public static function isUserSessionRevoked(int $userId, ?int $tokenIat): bool
    {
        $redis = self::getClient();
        if (!$redis || empty($tokenIat)) {
            return false;
        }

        $key = self::PREFIX . 'jwt:user_revoked:' . $userId;

        try {
            $revokedAt = $redis->get($key);
            if ($revokedAt !== false && (int) $revokedAt > $tokenIat) {
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}
