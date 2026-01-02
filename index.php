<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/controllers/ApiController.php';

global $CFG, $OUTPUT;

/* =====================
   NORMALIZAR RUTA
   ===================== */
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$baseUrl = rtrim($CFG->base_url, '/');

if ($baseUrl !== '' && strpos($requestUri, $baseUrl) === 0) {
    $requestUri = substr($requestUri, strlen($baseUrl));
}

$requestUri = '/' . trim($requestUri, '/');

/* =====================
   RUTA RAÍZ
   ===================== */
if ($requestUri === '/' || $requestUri === '/home') {
    // Configuramos CSS y JS
    $OUTPUT->setStyles([
        'home'
    ]);

    $OUTPUT->setJS([
        'home'
    ]);

    // Renderizamos head y nav
    $OUTPUT->head('Bienvenido a HDrive')
        ->setFooter('© 2025 HDrive');

    $body = $OUTPUT->render_template('home',  []);

    // Renderizamos todo el HTML
    $html = $OUTPUT->render_html($body);

    // Mostramos la página
    echo $html;

    exit;
}

/* =====================
   API ROUTER
   ===================== */
if (strpos($requestUri, '/api') === 0) {

    $segments = explode('/', trim($requestUri, '/'));

    // /api/{method}
    $method = $segments[1] ?? null;

    if (!$method) {
        http_response_code(400);
        echo json_encode(['error' => 'Método API no especificado']);
        exit;
    }

    $api = new ApiController();

    if (!method_exists($api, $method)) {
        http_response_code(404);
        echo json_encode(['error' => 'Método API no encontrado']);
        exit;
    }

    // Parámetros:
    // 1) query string (?path=...)
    // 2) o path /api/metodo/valor
    $params = [];

    if (!empty($_GET)) {
        $params = array_values($_GET);
    } elseif (isset($segments[2])) {
        $params[] = urldecode($segments[2]);
    }

    call_user_func_array([$api, $method], $params);
    exit;
}

/* =====================
   404 GENERAL
   ===================== */
http_response_code(404);

if (strpos($requestUri, '/api') === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ruta API no encontrada']);
} else {
    echo '<h2>404 - Página no encontrada</h2>';
}