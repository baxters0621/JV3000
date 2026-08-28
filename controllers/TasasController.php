<?php

// ==========================================
// CONTROLLER: Tasas de cambio
// ==========================================
// Proxy ligero contra ve.dolarapi.com para obtener tasas BCV
// oficiales (USD y EUR). Incluye cache de 30 minutos para
// no saturar la API externa.

/**
 * TasasController: consulta de tasas de cambio BCV.
 *
 * Proxy contra ve.dolarapi.com que obtiene las tasas oficiales
 * del Banco Central de Venezuela. Cachea resultados 30 minutos
 * en archivo temporal para no depender de internet en cada carga.
 */
class TasasController extends Controller
{
    /** Ruta del directorio de cache (relativa a la raíz del proyecto). */
    private const CACHE_DIR = __DIR__ . '/../cache';

    /** Tiempo de vida del cache en segundos (30 minutos). */
    private const CACHE_TTL = 1800;

    /** Base URL de la API externa (fuente: BCV). */
    private const API_BASE = 'https://ve.dolarapi.com/v1';

    /**
     * Obtiene las tasas oficiales BCV (USD y EUR).
     *
     * Responde con JSON: { usd_oficial, eur_oficial, fecha } o error.
     * Usa cache de 30 minutos para no saturar la API.
     *
     * @return void Responde JSON con las tasas.
     */
    public function obtener(): void
    {
        $cacheFile = self::CACHE_DIR . '/tasas_bcv.json';

        // Intentar leer cache
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && (time() - ($cached['timestamp'] ?? 0)) < self::CACHE_TTL) {
                $this->json([
                    'ok'           => true,
                    'usd_oficial'  => $cached['usd_oficial'],
                    'eur_oficial'  => $cached['eur_oficial'],
                    'fecha'        => $cached['fecha'],
                    'fuente'       => 'BCV (ve.dolarapi.com)',
                    'cache'        => true,
                ]);
                return;
            }
        }

        // Consultar API externa
        $usd = $this->consultarApi('/dolares/oficial');
        $eur = $this->consultarApi('/euros/oficial');

        if ($usd === null && $eur === null) {
            // Si hay cache viejo, devolverlo como fallback
            if (file_exists($cacheFile)) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if ($cached) {
                    $this->json([
                        'ok'           => true,
                        'usd_oficial'  => $cached['usd_oficial'],
                        'eur_oficial'  => $cached['eur_oficial'],
                        'fecha'        => $cached['fecha'],
                        'fuente'       => 'BCV (cache - API temporalmente no disponible)',
                        'cache'        => true,
                    ]);
                    return;
                }
            }
            $this->json([
                'ok'      => false,
                'mensaje' => 'NO SE PUEDEN OBTENER LAS TASAS DE CAMBIO EN ESTE MOMENTO.',
            ], 503);
            return;
        }

        $fecha = date('Y-m-d');

        // Guardar en cache
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
        file_put_contents($cacheFile, json_encode([
            'usd_oficial' => $usd,
            'eur_oficial' => $eur,
            'fecha'       => $fecha,
            'timestamp'   => time(),
        ]));

        $this->json([
            'ok'           => true,
            'usd_oficial'  => $usd,
            'eur_oficial'  => $eur,
            'fecha'        => $fecha,
            'fuente'       => 'BCV (ve.dolarapi.com)',
            'cache'        => false,
        ]);
    }

    /**
     * Consulta un endpoint de la API ve.dolarapi.com.
     *
     * @param string $endpoint Ruta relativa (ej: '/dolares/oficial').
     * @return float|null Promedio de la tasa o null si falla.
     */
    private function consultarApi(string $endpoint): ?float
    {
        $url = self::API_BASE . $endpoint;

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 10,
                'ignore_errors' => true,
                'header'        => "User-Agent: JV3000/1.0\r\nAccept: application/json\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }

        // La API devuelve { promedio, ... } o un array de monedas
        if (isset($data['promedio'])) {
            return round((float)$data['promedio'], 4);
        }

        // Si es array de monedas, tomar la primera (oficial)
        if (isset($data[0]['promedio'])) {
            return round((float)$data[0]['promedio'], 4);
        }

        return null;
    }
}
