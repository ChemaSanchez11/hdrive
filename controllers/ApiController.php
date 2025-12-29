<?php
class ApiController {

    private $driveRoot;
    private $baseUrl;

    public function __construct($config) {
        $this->driveRoot = realpath($config['drive_root']);
        $this->baseUrl = rtrim($config['base_url'], '/') . '/';
    }

    // Listar una carpeta
    public function listFolder($path = '/') {
        $path = trim($path, '/');
        $absPath = $path === '' ? $this->driveRoot : realpath($this->driveRoot . '/' . $path);

        // Seguridad: no salir de la raíz
        if (!$absPath || strpos($absPath, $this->driveRoot) !== 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Ruta inválida']);
            return;
        }

        $items = [];
        foreach (scandir($absPath) as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $absPath . '/' . $f;
            $items[] = [
                'name' => $f,
                'type' => is_dir($full) ? 'folder' : 'file',
                'path' => ($path === '' ? '' : $path . '/') . $f,
                'url'  => $this->baseUrl . 'assets/drive/' . (($path === '' ? '' : $path . '/') . $f)
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($items);
    }

    // Otro ejemplo: info de un archivo
    public function fileInfo($path) {
        $absPath = realpath($this->driveRoot . '/' . trim($path, '/'));
        if (!$absPath || strpos($absPath, $this->driveRoot) !== 0 || !is_file($absPath)) {
            http_response_code(400);
            echo json_encode(['error' => 'Archivo no encontrado']);
            return;
        }

        $info = [
            'name' => basename($absPath),
            'size' => filesize($absPath),
            'last_modified' => date('Y-m-d H:i:s', filemtime($absPath)),
            'url' => $this->baseUrl . 'assets/drive/' . trim($path, '/')
        ];

        header('Content-Type: application/json');
        echo json_encode($info);
    }
}