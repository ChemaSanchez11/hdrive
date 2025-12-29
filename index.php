<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/controllers/ApiController.php';

$api = new ApiController($config);

// Obtener la ruta de la URL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$baseUrl = rtrim($config['base_url'], '/');

// Normalizar la ruta eliminando base_url
if ($baseUrl !== '' && strpos($requestUri, $baseUrl) === 0) {
    $requestUri = substr($requestUri, strlen($baseUrl));
}
$requestUri = rtrim($requestUri, '/');

// -----------------------------
// Ruta raíz: /
if ($requestUri === '' || $requestUri === '/home') {
    echo "Bienvenido a HDrive. API disponible en /api/{metodo}";
    exit;
}

// -----------------------------
// Rutas API: /api/{metodo}?param1=...
if (strpos($requestUri, '/api') === 0) {
    $segments = explode('/', trim($requestUri, '/'));
    $method = isset($segments[1]) ? $segments[1] : null;

    if (!$method || !method_exists($api, $method)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Método API no encontrado']);
        exit;
    }

    // Pasar parámetros GET automáticamente
    if (!empty($_GET)) {
        call_user_func_array([$api, $method], $_GET);
    } else {
        call_user_func([$api, $method]);
    }
    exit;
}

// -----------------------------
// 404
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Ruta no encontrada']);