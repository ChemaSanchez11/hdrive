<?php

require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/ActivityLogger.php';
require_once __DIR__ . '/lib/Renderer.php';

global $CFG, $DB, $USER, $LOGGER, $OUTPUT;

$OUTPUT = new \renderer\Renderer();

$CFG = new stdClass();

/**
 * CONFIGURACIÓN GENERAL
 */
$CFG->drive_root = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'drive';
$CFG->base_url   = '/hdrive/';

date_default_timezone_set('Europe/Madrid');

/**
 * CONFIGURACIÓN BD
 */
$CFG->db = [
    'host'    => 'localhost',
    'dbname'  => 'hdrive',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];


set_exception_handler(function ($e) {
    http_response_code(500);

    echo '<div style="
        background:#ffb5b5;
        color:#413a3a;
        padding:20px;
        font-family:Segoe UI, Arial;
        border-radius:8px;
        margin:40px;
        box-shadow:0 0 20px rgba(255,0,0,.3);
    ">';

    echo '<h3 style="margin-top:0;color: #d91503;;">❌ Error del sistema</h3>';

    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';

    echo '</div>';

    echo '<pre style="color: #ec4040; margin:40px;  padding:20px;">';
    print_r($e);
    echo '</pre>';

    exit;
});

try {
    $DB = DB::getInstance($CFG->db);
} catch (Exception $e) {
    http_response_code(500);
    die('Error crítico: no se pudo conectar a la base de datos');
}

$LOGGER = new ActivityLogger($USER->id ?? null);