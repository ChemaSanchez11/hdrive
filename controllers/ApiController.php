<?php
/**
 * Class ApiController
 *
 * Controlador principal de la API de HDrive.
 * Maneja carpetas, archivos, subidas, generación de links compartidos y listado de contenido.
 *
 * @global DB $DB Objeto de base de datos global
 * @global ActivityLogger $LOGGER Objeto global de logging de actividad
 */
class ApiController
{
    /** @var string Ruta física de la raíz del drive */
    private string $driveRoot;

    /** @var string URL base del proyecto */
    private string $baseUrl;

    /**
     * ApiController constructor.
     * Inicializa la ruta de archivos y la URL base desde la configuración global $CFG
     */
    public function __construct()
    {
        global $CFG;

        $this->driveRoot = realpath($CFG->drive_root);
        $this->baseUrl   = rtrim($CFG->base_url, '/') . '/';
    }

    /**
     * LISTAR CONTENIDO DE UNA CARPETA
     *
     * Endpoint: /api/listFolder?path=NombreDeCarpeta
     *
     * @param string $path Ruta relativa de la carpeta dentro del drive
     * @return void
     */
    public function listFolder(string $path = '/'): void
    {
        global $DB, $LOGGER;

        header('Content-Type: application/json');
        $path = '/' . trim($path, '/');

        $folder = $DB->fetch('SELECT * FROM folders WHERE path = :path', ['path' => $path]);

        if (!$folder) {
            http_response_code(404);
            echo json_encode(['error' => 'Carpeta no encontrada']);
            return;
        }

        $absPath = realpath($this->driveRoot . $folder->path);
        if (!$absPath || strpos($absPath, $this->driveRoot) !== 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Ruta inválida']);
            return;
        }

        $items = [];

        // Subcarpetas
        $folders = $DB->query('SELECT id, name, path FROM folders WHERE parent_id = :pid', ['pid' => $folder->id]);
        foreach ($folders as $f) {
            $items[] = [
                'type' => 'folder',
                'id'   => $f->id,
                'name' => $f->name,
                'path' => $f->path,
            ];
        }

        // Archivos
        $files = $DB->query('SELECT id, name, extension, size, path FROM files WHERE folder_id = :fid', ['fid' => $folder->id]);
        foreach ($files as $file) {
            $items[] = [
                'type' => 'file',
                'id'   => $file->id,
                'name' => $file->name,
                'ext'  => $file->extension,
                'size' => $file->size,
                'path' => $file->path,
                'url'  => $this->baseUrl . 'assets/drive' . $file->path
            ];
        }

        echo json_encode([
            'folder' => $folder->path,
            'items'  => $items
        ]);

        $LOGGER->log('list_folder', $folder->path);
    }

    /**
     * OBTENER INFORMACIÓN DE UN ARCHIVO
     *
     * Endpoint: /api/fileInfo?path=/Ruta/Archivo.ext
     *
     * @param string $path Ruta del archivo dentro del drive
     * @return void
     */
    public function fileInfo(string $path): void
    {
        global $DB, $LOGGER;

        header('Content-Type: application/json');
        $path = '/' . trim($path, '/');

        $file = $DB->fetch('SELECT * FROM files WHERE path = :path', ['path' => $path]);
        if (!$file) {
            http_response_code(404);
            echo json_encode(['error' => 'Archivo no encontrado']);
            return;
        }

        $absPath = $this->driveRoot . $file->path;

        if (!is_file($absPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Archivo físico no existe']);
            return;
        }

        // Detectar si es un archivo de texto para mostrarlo
        $textExtensions = ['txt', 'md', 'js', 'css', 'html', 'json'];
        $content = in_array(strtolower($file->extension), $textExtensions)
            ? file_get_contents($absPath)
            : null; // null si es binario o no mostrar

        echo json_encode([
            'id'            => $file->id,
            'name'          => $file->name,
            'extension'     => $file->extension,
            'size'          => $file->size,
            'created_at'    => $file->created_at,
            'last_modified' => date('Y-m-d H:i:s', filemtime($absPath)),
            'url'           => $this->baseUrl . 'assets/drive' . $file->path,
            'content'       => $content
        ]);

        $LOGGER->log('file_info', $path);
    }

    /**
     * CREAR NUEVA CARPETA
     *
     * Endpoint: /api/createFolder?name=Nombre&parent=/CarpetaPadre
     *
     * @param string $name Nombre de la nueva carpeta
     * @param string $parent Ruta de la carpeta padre
     * @return void
     */
    public function createFolder(string $name, string $parent = '/'): void
    {
        global $DB, $LOGGER;

        header('Content-Type: application/json');

        $parent = '/' . trim($parent, '/');
        $name   = trim($name);

        if ($name === '') {
            throw new Exception('Nombre de carpeta vacío', 400);
        }

        $parentFolder = $DB->fetch('SELECT * FROM folders WHERE path = :path', ['path' => $parent]);
        if (!$parentFolder) {
            throw new Exception('Carpeta padre no existe', 404);
        }

        $newPath = rtrim($parentFolder->path, '/') . '/' . $name;

        $DB->execute('INSERT INTO folders (parent_id, name, path) VALUES (:pid, :name, :path)', [
            'pid'  => $parentFolder->id,
            'name' => $name,
            'path' => $newPath
        ]);

        $absPath = $this->driveRoot . $newPath;
        if (!mkdir($absPath, 0775, true)) {
            throw new Exception('No se pudo crear carpeta física', 500);
        }

        $LOGGER->log('create_folder', $newPath);

        echo json_encode(['success' => true, 'path' => $newPath]);
    }

    /**
     * SUBIR ARCHIVO A UNA CARPETA
     *
     * Endpoint: /api/uploadFile?path=/CarpetaDestino
     * FormData: file=Archivo
     *
     * @param string $path Ruta de la carpeta destino
     * @return void
     */
    public function uploadFile(string $path = '/'): void
    {
        global $DB, $LOGGER;

        header('Content-Type: application/json');

        if (empty($_FILES['file'])) {
            throw new Exception('No se ha enviado ningún archivo', 400);
        }

        $path = '/' . trim($path, '/');

        $folder = $DB->fetch('SELECT * FROM folders WHERE path = :path', ['path' => $path]);
        if (!$folder) {
            throw new Exception('Carpeta destino no existe', 404);
        }

        $file = $_FILES['file'];
        $filename = basename($file['name']);
        $ext      = pathinfo($filename, PATHINFO_EXTENSION);
        $size     = filesize($file['tmp_name']);

        $filePath = rtrim($path, '/') . '/' . $filename;
        $absPath  = $this->driveRoot . $filePath;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new Exception('Error guardando archivo', 500);
        }

        $DB->execute(
            'INSERT INTO files (folder_id, name, extension, size, path)
             VALUES (:fid, :name, :ext, :size, :path)',
            ['fid' => $folder->id, 'name' => $filename, 'ext' => $ext, 'size' => $size, 'path' => $filePath]
        );

        $LOGGER->log('upload_file', $filePath);

        echo json_encode(['success' => true, 'file' => $filename]);
    }

    /**
     * GENERAR LINK COMPARTIDO DE UN ARCHIVO
     *
     * Endpoint: /api/generateSharedLink?path=/Archivo&expires=60
     *
     * @param string $path Ruta del archivo
     * @param int|null $expires Tiempo en minutos antes de expirar el link (opcional)
     * @return void
     */
    public function generateSharedLink(string $path, int $expires = null): void
    {
        global $DB, $LOGGER;

        header('Content-Type: application/json');

        $path = '/' . trim($path, '/');

        $file = $DB->fetch('SELECT * FROM files WHERE path = :path', ['path' => $path]);
        if (!$file) {
            throw new Exception('Archivo no encontrado', 404);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $expires ? date('Y-m-d H:i:s', time() + ($expires * 60)) : null;

        $DB->execute(
            'INSERT INTO shared_links (file_id, token, expires_at)
             VALUES (:fid, :token, :exp)',
            ['fid' => $file->id, 'token' => $token, 'exp' => $expiresAt]
        );

        $LOGGER->log('share_file', $path);

        echo json_encode(['url' => $this->baseUrl . 'shared/' . $token, 'expires_at' => $expiresAt]);
    }
}
