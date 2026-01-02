<?php

require_once(__DIR__ . '/database/DB.php');
use database\DB;

// Inicia la sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($CFG, $DB);

global $CFG, $DB, $USER;

$CFG = new stdClass();
$CFG->main = 'clockup';
$CFG->wwwroot = '/clockup';
$CFG->key = md5('clockup');
$CFG->routes = [
    'home' => [
        'name' => 'Inicio',
        'controller' => 'HomeController.php',
        'class' => HomeController::class,
        'actual' => false,
        'visible' => false
    ],
    'tasks' => ['name' => 'Tareas',
        'controller' => 'TasksController.php',
        'class' => TasksController::class,
        'actual' => false,
        'visible' => true
    ]
];

$DB = new DB('localhost', 'root', '', '3306', 'clockup');