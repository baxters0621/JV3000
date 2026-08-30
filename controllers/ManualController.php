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
            'css_extra'    => ['modules/manual/manual.css?v=1'],
            'csrf'         => Security::generateToken(),
            'manual_html'  => self::mdToHtml($manual_md),
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
                $html[] = '<' . $tag . ' class="manual-' . $tag . '">' . self::inlineMd($m[1]) . '</' . $tag . '>';
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