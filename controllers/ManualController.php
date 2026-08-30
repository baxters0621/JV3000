<?php

// ==========================================
// CONTROLADOR: Guía de Uso (Manual del sistema)
// ==========================================
// Lee docs/MANUAL_USUARIO.md, lo convierte a HTML
// con un parser Markdown ligero propio y lo entrega
// a la vista. Accesible para los tres roles.

/**
 * ManualController: muestra la guía de uso dentro del sistema.
 *
 * Lee el archivo docs/MANUAL_USUARIO.md, lo convierte de Markdown a
 * HTML (parser ligero sin dependencias externas) y se lo entrega a la
 * vista renderizada dentro del layout principal.
 */
class ManualController extends Controller
{
    /**
     * Renderiza la guía de uso del sistema.
     *
     * Valida la sesión (cualquier rol autenticado), lee el manual en
     * Markdown, lo transforma a HTML y pasa el resultado a la vista.
     *
     * @return void
     */
    public function index(): void
    {
        Security::validateSession();

        $manual_path = APP_ROOT . '/docs/MANUAL_USUARIO.md';
        $manual_md = file_exists($manual_path) ? file_get_contents($manual_path) : '# Manual no encontrado';

        $this->view('manual/index', [
            'titulo'       => 'Guía de Uso | JV3000 C.A.',
            'wrapper_class'=> 'pagina-manual',
            'css_extra'    => ['modules/manual/manual.css?v=2'],
            'js_extra'     => ['modules/manual/manual.js?v=1'],
            'csrf'         => Security::generateToken(),
            'manual_html'  => self::mdToHtml($manual_md),
            'manual_toc'   => self::extraerToc($manual_md),
        ]);
    }

    /**
     * Convierte Markdown a HTML con un parser ligero propio.
     *
     * Soporta: encabezados, párrafos, listas, listas numeradas,
     * blockquotes, bloques de código, líneas horizontales y tablas.
     * Las reglas de conteo por bloque evitan depender de librerías.
     *
     * @param string $md Contenido Markdown de origen.
     * @return string HTML generado.
     */
    private static function mdToHtml(string $md): string
    {
        $lines = explode("\n", $md);
        $html = [];
        $in_code = false;
        $in_table = false;
        $in_list = false;
        $list_ol = false;

        foreach ($lines as $line) {
            // Bloques de código
            if (preg_match('/^```/', $line)) {
                if ($in_code) {
                    $html[] = '</code></pre>';
                    $in_code = false;
                } else {
                    $html[] = '<pre class="manual-code"><code>';
                    $in_code = true;
                }
                continue;
            }
            if ($in_code) {
                $html[] = htmlspecialchars($line);
                continue;
            }

            $trimmed = trim($line);

            // Línea horizontal
            if ($trimmed === '---') {
                self::cerrarBloques($html, $in_table, $in_list, $list_ol);
                $in_table = false;
                $in_list = false;
                $html[] = '<hr class="manual-hr">';
                continue;
            }

            // Tabla Markdown
            if (preg_match('/^\|(.+)\|$/', $trimmed)) {
                $cells = array_values(array_filter(array_map('trim', explode('|', $trimmed)), fn($c) => $c !== ''));

                // Separador de cabecera (|---|---|)
                if (preg_match('/^[\|:\-\s]+$/', $trimmed)) {
                    continue;
                }

                if (!$in_table) {
                    $html[] = '<div class="table-responsive"><table class="table table-bordered table-hover manual-table">';
                    $html[] = '<thead><tr>';
                    foreach ($cells as $cell) {
                        $html[] = '<th>' . self::inlineMd($cell) . '</th>';
                    }
                    $html[] = '</tr></thead><tbody>';
                    $in_table = true;
                } else {
                    $html[] = '<tr>';
                    foreach ($cells as $cell) {
                        $html[] = '<td>' . self::inlineMd($cell) . '</td>';
                    }
                    $html[] = '</tr>';
                }
                continue;
            }
            if ($in_table) {
                $html[] = '</tbody></table></div>';
                $in_table = false;
            }

            // Encabezados
            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmed, $m)) {
                if ($in_list) {
                    $html[] = $list_ol ? '</ol>' : '</ul>';
                    $in_list = false;
                }
                $level = substr_count($trimmed, '#');
                $tag = 'h' . min($level, 6);
                $id = self::slugify($m[1]);
                $html[] = '<' . $tag . ' id="' . $id . '" class="manual-' . $tag . '">' . self::inlineMd($m[1]) . '</' . $tag . '>';
                continue;
            }

            // Listas con guión
            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
                if (!$in_list) {
                    $html[] = '<ul class="manual-list">';
                    $in_list = true;
                    $list_ol = false;
                }
                $html[] = '<li>' . self::inlineMd($m[1]) . '</li>';
                continue;
            }

            // Listas numeradas
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
                if (!$in_list) {
                    $html[] = '<ol class="manual-list">';
                    $in_list = true;
                    $list_ol = true;
                }
                $html[] = '<li>' . self::inlineMd($m[1]) . '</li>';
                continue;
            }

            // Cierre de lista al llegar a un párrafo vacío
            if ($in_list && $trimmed === '') {
                $html[] = $list_ol ? '</ol>' : '</ul>';
                $in_list = false;
                continue;
            }

            // Blockquote
            if (preg_match('/^>\s*(.+)$/', $trimmed, $m)) {
                $html[] = '<blockquote class="manual-quote">' . self::inlineMd($m[1]) . '</blockquote>';
                continue;
            }

            // Párrafo vacío
            if ($trimmed === '') {
                continue;
            }

            // Párrafo normal
            $html[] = '<p class="manual-p">' . self::inlineMd($trimmed) . '</p>';
        }

        self::cerrarBloques($html, $in_table, $in_list, $list_ol);

        return implode("\n", $html);
    }

    /**
     * Construye el índice de contenidos (TOC) a partir del Markdown.
     *
     * Escanea los encabezados de nivel 2 a 4 y devuelve una lista con su
     * nivel, título y ancla, de modo que la Tabla de Contenidos de la vista
     * siempre quede sincronizada con el documento fuente.
     *
     * @param string $md Contenido Markdown de origen.
     * @return array<int, array{nivel:int, titulo:string, id:string, num:?int}> Ítems del índice.
     */
    private static function extraerToc(string $md): array
    {
        $toc = [];
        $ids_uso = [];

        foreach (explode("\n", $md) as $linea) {
            if (!preg_match('/^(#{2,4})\s+(.+)$/', $linea, $m)) {
                continue;
            }
            $nivel = strlen($m[1]);
            $titulo_raw = trim($m[2]);

            // El número de sección sale de los títulos "N. Título".
            // La ancla se calcula SIEMPRE con el texto completo (con el
            // número) para que coincida con los id que genera mdToHtml().
            $num = null;
            $titulo = $titulo_raw;
            if (preg_match('/^(\d+)\.\s*(.+)$/', $titulo_raw, $partes)) {
                $num = (int)$partes[1];
                $titulo = trim($partes[2]);
            }

            // Ancla única (slug) con sufijo numérico en caso de repetirse.
            $base = self::slugify($titulo_raw);
            $id = $base;
            $i = 2;
            while (isset($ids_uso[$id])) {
                $id = $base . '-' . $i++;
            }
            $ids_uso[$id] = true;

            $toc[] = ['nivel' => $nivel, 'titulo' => $titulo, 'id' => $id, 'num' => $num];
        }

        return $toc;
    }

    /**
     * Convierte un texto en un slug seguro para usarse como ancla HTML.
     *
     * Pasa a minúsculas, normaliza tildes y ñ, y sustituye espacios y
     * caracteres especiales por guiones.
     *
     * @param string $texto Texto de origen.
     * @return string Slug resultante.
     */
    private static function slugify(string $texto): string
    {
        $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES, 'UTF-8');
        $texto = strtolower(trim($texto));
        $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n'];
        $texto = strtr($texto, $mapa);
        $texto = preg_replace('/[^a-z0-9\s\-]/', '', $texto);
        $texto = preg_replace('/[\s_\-]+/', '-', $texto);
        return trim((string)$texto, '-');
    }

    /**
     * Escapa y aplica formato en línea (negrita, itálica, código, enlaces).
     *
     * @param string $text Texto fuente con sintaxis Markdown inline.
     * @return string HTML con el formato aplicado.
     */
    private static function inlineMd(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/`([^`]+)`/', '<code class="manual-inline-code">$1</code>', $text);
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="manual-link">$1</a>', $text);
        return $text ?? '';
    }

    /**
     * Cierra tablas y listas pendientes según su estado actual.
     *
     * @param array  $html    Referencia al acumulador de HTML.
     * @param bool   $in_table Si hay una tabla abierta.
     * @param bool   $in_list  Si hay una lista abierta.
     * @param bool   $list_ol  Si la lista abierta es numerada.
     * @return void
     */
    private static function cerrarBloques(array &$html, bool $in_table, bool $in_list, bool $list_ol): void
    {
        if ($in_table) {
            $html[] = '</tbody></table></div>';
        }
        if ($in_list) {
            $html[] = $list_ol ? '</ol>' : '</ul>';
        }
    }
}