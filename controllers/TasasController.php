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
        // La tasa manual tiene prioridad sobre la caché y la API externa.
        $manual = jv_tasa_bcv_manual();
        if ($manual['usd'] !== null) {
            $this->json([
                'ok'          => true,
                'usd_oficial' => $manual['usd'],
                'eur_oficial' => null,
                'fecha'       => $manual['fecha'],
                'fuente'      => 'Manual',
                'cache'       => false,
                'manual'      => true,
            ]);
            return;
        }

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

        // Consultar API externa: USD y EUR en paralelo con tope de 6 s por
        // petición, para que el peor caso cueste ~6 s en vez de ~20 s.
        $tasas = $this->consultarApiParalela();
        $usd = $tasas[0];
        $eur = $tasas[1];

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
     * Consulta en paralelo las tasas USD y EUR.
     *
     * El USD se toma de la página oficial del BCV (con la cadena Sectigo
     * incluida en el proyecto); si esa consulta falla, se usa el respaldo de
     * ve.dolarapi.com. El EUR sigue saliendo de ve.dolarapi.com. Las tres
     * peticiones corren a la vez con tope de 6 s.
     *
     * @return array{0:?float,1:?float} Promedio USD y EUR, o null si falla.
     */
    private function consultarApiParalela(): array
    {
        $manejador_multiple = curl_multi_init();
        $peticiones = [];

        // 0: página oficial del BCV (HTML con la tasa USD).
        $canal = curl_init('https://www.bcv.org.ve/');
        $opciones = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'JV3000/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        ];
        $certificado = __DIR__ . '/../includes/certificados/bcv_sectigo.pem';
        if (is_file($certificado)) {
            $opciones[CURLOPT_CAINFO] = $certificado;
        }
        curl_setopt_array($canal, $opciones);
        curl_multi_add_handle($manejador_multiple, $canal);
        $peticiones['bcv'] = $canal;

        // 1 y 2: dolarapi (USD de respaldo y EUR).
        foreach (['usd_respaldo' => '/dolares/oficial', 'eur' => '/euros/oficial'] as $clave => $endpoint) {
            $canal = curl_init(self::API_BASE . $endpoint);
            curl_setopt_array($canal, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT      => 'JV3000/1.0',
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            curl_multi_add_handle($manejador_multiple, $canal);
            $peticiones[$clave] = $canal;
        }

        // Ejecutar hasta que todas terminen (éxito, error o tope de tiempo).
        do {
            $estado = curl_multi_exec($manejador_multiple, $activo);
            if ($activo) {
                curl_multi_select($manejador_multiple, 0.2);
            }
        } while ($activo && $estado === CURLM_OK);

        $respuestas = [];
        foreach ($peticiones as $clave => $canal) {
            $respuestas[$clave] = curl_multi_getcontent($canal);
            curl_multi_remove_handle($manejador_multiple, $canal);
            curl_close($canal);
        }
        curl_multi_close($manejador_multiple);

        $usd = $this->extraerDolarBcv($respuestas['bcv'] ?? null);
        if ($usd === null) {
            $usd = $this->extraerPromedio($respuestas['usd_respaldo'] ?? null);
        }
        $eur = $this->extraerPromedio($respuestas['eur'] ?? null);

        return [$usd, $eur];
    }

    /**
     * Extrae el USD del HTML de la página oficial del BCV.
     *
     * @param string|false|null $respuesta Cuerpo crudo de la respuesta.
     * @return float|null Tasa redondeada a 4 decimales, o null.
     */
    private function extraerDolarBcv($respuesta): ?float
    {
        if (!is_string($respuesta) || trim($respuesta) === '') {
            return null;
        }
        if (!preg_match('/id="dolar".*?<strong class="strong-tb">\s*([0-9.,]+)\s*<\/strong>/s', $respuesta, $coincidencia)) {
            return null;
        }
        $valor = str_replace(',', '.', trim($coincidencia[1]));
        if (!is_numeric($valor) || (float)$valor <= 0 || (float)$valor > 100000) {
            return null;
        }

        return round((float)$valor, 4);
    }

    /**
     * Extrae el promedio de la respuesta JSON de la API de tasas.
     *
     * La API devuelve un objeto { promedio, ... } o un array de monedas del
     * cual se toma la primera entrada (la oficial).
     *
     * @param string|false|null $respuesta Cuerpo crudo de la respuesta.
     * @return float|null Promedio redondeado a 4 decimales, o null.
     */
    private function extraerPromedio($respuesta): ?float
    {
        if (!is_string($respuesta) || trim($respuesta) === '') {
            return null;
        }
        $data = json_decode($respuesta, true);
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['promedio'])) {
            return round((float)$data['promedio'], 4);
        }
        if (isset($data[0]['promedio'])) {
            return round((float)$data[0]['promedio'], 4);
        }
        return null;
    }
}
