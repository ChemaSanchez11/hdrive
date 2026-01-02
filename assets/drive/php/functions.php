<?php

/**
 * Cerrar sesion
 */
function logout(): void
{
    unset($_SESSION['user']);
    redirect();
}

/**
 * Redirige hacia la ruta especificada
 * @param string $route Ruta
 */
function redirect(string $route = 'home'): void
{

    global $CFG;

    // Verificar si es HTTPS
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";

    // Obtener el host (dominio o IP)
    $host = $_SERVER['HTTP_HOST'];

    // Obtener el URI actual
    $url_home = $host . $CFG->wwwroot . '/' . $route;
    ob_start();
    header("Location: $protocol://$url_home");
    exit();
}

/**
 * Limpiar y obtener URI
 *
 * @param $basePath
 * @return mixed
 */
function get_uri($basePath)
{
    $url = trim(filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL), '/');
    return preg_replace("/^$basePath\//", '', $url);
}

/**
 * Cargar controlador correspondiente
 *
 * @param $controller_path
 * @param $controller_class
 * @return mixed|void
 */
function load_controller($controller_path, $controller_class) {
    try {
        require_once __DIR__ . '/../controllers/' . $controller_path;
        if (class_exists($controller_class)) {
            return new $controller_class();
        }
        throw new Exception("Clase del controlador no encontrada");
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error al cargar el controlador: " . $e->getMessage();
        exit();
    }
}

function is_allow_ip($ip) {
    global $CFG;

    if (array_key_exists($ip, $CFG->ips)) return true;

    $local_ips = [
        '192.168.1.0|192.168.1.255',
        '127.0.0.0|127.0.0.1',
        '87.221.41.248|87.221.41.248' // Casa
    ];

    foreach ($local_ips as $local_ip) {
        list($start, $end) = explode('|', $local_ip);
        if (ip2long($ip) >= ip2long($start) && ip2long($ip) <= ip2long($end)) {
            return true;
        }
    }
    return false;
}

function get_users() {
    global $DB;

    $users = $DB->get_records('SELECT
        `id`,
        `username`,
        `email`,
        `firstname`,
        `lastname`,
        `image`,
        `lastlogin`,
        `active`
    FROM
        `users`;');

    foreach ($users as &$user) {
        $user->status = !empty($user->active) ? 'Activo' : 'Suspendido';
        $user->lastlogin = !empty($user->lastlogin) ? date('d/m/y H:i', $user->lastlogin) : '-';
    }

    return $users;
}

function format_data($data) {
    // Si el dato es un array o un objeto, lo convertimos a JSON con formato
    if (is_array($data) || is_object($data)) {
        echo '<pre>';
        // Usamos json_encode con el flag JSON_PRETTY_PRINT para un formato legible
        echo json_encode($data, JSON_PRETTY_PRINT);
        echo '</pre>';
    } else {
        // Si no es un objeto o array, lo mostramos tal cual
        echo htmlspecialchars($data);
    }
}

function get_visible_routes($use_in_navbar = false): array
{
    global $CFG;

    $routes = [];
    foreach ($CFG->routes as $route => $details) {
        if (!empty($details['visible'])) {
            $details['route'] = $route === 'home' ? '' : $route;
            $details['url'] = $CFG->wwwroot . $route;
            $routes[$route] = $details;
        }
    }

    if ($use_in_navbar) {
        return array_values($routes);
    }

    return $routes;
}

function get_enrols($filter_idnumber = null, $filter_platform = null): array
{
    global $DB;

    $WHERE = '1 = 1';

    if (!empty($filter_idnumber)) {
        $WHERE .= ' AND u.idnumber LIKE "%'. $filter_idnumber .'%"';
    }

    if (!empty($filter_platform)) {
        $WHERE .= ' AND e.platform_id = '. $filter_platform;
    }

    $enrols = $DB->get_records("
            SELECT
                e.id,
                e.status,
                e.timestart,
                e.timeend,
                e.user_id,
                u.firstname,
                u.lastname,
                u.idnumber,
                u.email,
                u.image,
                e.course_id,
                c.idnumber AS `course_idnumber`,
                c.`name`,
                e.platform_id,
                p.`name` AS `platform`,
                p.url
            FROM
                `enrols` e
                INNER JOIN courses c ON c.id = e.course_id
                INNER JOIN users u ON u.id = e.user_id
                INNER JOIN platforms p ON p.id = e.platform_id
            WHERE $WHERE");
    foreach ($enrols as &$enrol) {

        $enrol->timestart_format = '-';
        if (!empty($enrol->timestart)) {
            $enrol->timestart_format = date('d/m/y H:i', $enrol->timestart);
        }

        $enrol->timeend_format = '-';
        if (!empty($enrol->timeend)) {
            $enrol->timeend_format = date('d/m/y H:i', $enrol->timeend);
        }

        //'ACTIVE','PENDING','SUSPENDED','FINISH'
        $enrol->status = strtolower($enrol->status);
    }


    return $enrols;
}

function get_enrols_user($filter_idnumber = null): array
{
    global $DB;

    $params = [];

    $wheres = [];
    if (!empty($filter_idnumber)) {
        $wheres[] = 'u.idnumber = ?';
        $params[] = $filter_idnumber;
    }

    $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

    $sql = "
        SELECT
            e.id,
            e.status,
            e.timestart,
            e.timeend,
            e.user_id,
            e.course_id,
            c.idnumber AS course_idnumber,
            c.name,
            c.image,
            c.modality,
            e.platform_id,
            p.name AS platform,
            p.url,
            (SELECT CONCAT(firstname, ' ', lastname) FROM users WHERE id = c.teacher_id) AS teacher
        FROM
            enrols e
        INNER JOIN courses c ON c.id = e.course_id
        INNER JOIN users u ON u.id = e.user_id
        INNER JOIN platforms p ON p.id = e.platform_id
        $where
    ";

    $enrols = $DB->get_records($sql, $params);

    foreach ($enrols as &$enrol) {
        $enrol->timestart_format = !empty($enrol->timestart)
            ? date('d/m/y H:i', $enrol->timestart)
            : '-';

        $enrol->timeend_format = !empty($enrol->timeend)
            ? date('d/m/y', $enrol->timeend)
            : false;

        $enrol->status = strtolower($enrol->status);

        // Modalidades en array de objetos: short + label
        $modalitymap = [
            'onsite' => 'Presencial',
            'online' => 'Online',
        ];

        if ($enrol->modality === 'mixed') {
            $enrol->modalities = [];
            foreach ($modalitymap as $short => $label) {
                $enrol->modalities[] = ['short' => $short, 'label' => $label];
            }
        } elseif (isset($modalitymap[$enrol->modality])) {
            $enrol->modalities = [
                ['short' => $enrol->modality, 'label' => $modalitymap[$enrol->modality]]
            ];
        } else {
            $enrol->modalities = [
                ['short' => $enrol->modality, 'label' => ucfirst($enrol->modality)]
            ];
        }
    }

    return $enrols;
}

function get_enrol($id): array
{
    global $DB;

    $enrol = $DB->get_record("
            SELECT
                e.id,
                e.status,
                e.timestart,
                e.timeend,
                e.user_id,
                u.username,
                u.firstname,
                u.lastname,
                u.idnumber,
                u.email,
                u.phone,
                u.image,
                e.course_id,
                c.idnumber AS `course_idnumber`,
                c.`name`,
                c.teacher_id,
                e.platform_id,
                (SELECT url FROM platforms WHERE id = e.platform_id) AS `platform_url`
            FROM
                `enrols` e
                INNER JOIN courses c ON c.id = e.course_id
                INNER JOIN users u ON u.id = e.user_id
            WHERE e.id = ?
            LIMIT 1", [$id]);

    if (empty($enrol)) return [];

    $enrol->timestart_format = '-';
    if (!empty($enrol->timestart)) {
        $enrol->timestart_input = date('Y-m-d\TH:i', $enrol->timestart);
        $enrol->timestart_format = date('d/m/y H:i', $enrol->timestart);
    }

    $enrol->timeend_format = '-';
    if (!empty($enrol->timeend)) {
        $enrol->timeend_input = date('Y-m-d\TH:i', $enrol->timeend);
        $enrol->timeend_format = date('d/m/y H:i', $enrol->timeend);
    }

    $enrol->active = false;
    switch ($enrol->status) {
        case 'ACTIVE':
            $enrol->active = true;
            $enrol->status_text = 'Activa';
            break;

        case 'PENDING':
            $enrol->status_text = 'Pendiente';
            break;

        case 'SUSPENDED':
            $enrol->status_text = 'Suspendida';
            break;

        case 'FINISH':
            $enrol->status_text = 'Finalizada';
            break;
    }

    $sql = "
        SELECT 
            id, 
            `name` AS `platform`,
            CASE WHEN id = ? THEN true ELSE false END AS actual
        FROM platforms
        WHERE visible = 1
    ";


    $enrol->platforms = $DB->get_records($sql, [$enrol->platform_id]);

    return (array) $enrol;
}

function get_list_status($selected = null) {
    $all_status = [
        'ACTIVE' => ['status' => 'ACTIVE', 'text' => 'Activa'],
        'PENDING' => ['status' => 'PENDING', 'text' => 'Pendiente'],
        'SUSPENDED' => ['status' => 'SUSPENDED', 'text' => 'Suspendida'],
        'FINISH' => ['status' => 'FINISH', 'text' => 'Finalizada'],
    ];

    if(!empty($all_status[$selected])) $all_status[$selected]['actual'] = true;
    return array_values($all_status);
}

function get_list_courses($selected = null) {
    global $DB;
    $sql = "
        SELECT 
            id, 
            `name` ,
            `idnumber` ,
            CASE WHEN id = ? THEN true ELSE false END AS actual
        FROM courses
    ";


    $courses = $DB->get_records($sql, [$selected]);
    return array_values($courses);
}

function get_teachers_for_enrolment($teacher_id): array
{
    global $DB;

    $teachers = $DB->get_records("
    SELECT
        u.id,
        CONCAT(u.firstname, ' ', u.lastname) AS `name`,
        CASE WHEN u.id = ? THEN true ELSE false END AS actual
    FROM
        users u
        INNER JOIN group_members gm ON gm.user_id = u.id
        INNER JOIN `groups` g ON gm.group_id = g.id
    WHERE
        g.`name` = 'Profesores';", [$teacher_id]);

    return (array) $teachers;
}

function get_teachers(): array
{
    global $DB;

    $teachers = $DB->get_records("
    SELECT
        u.id,
        CONCAT(u.firstname, ' ', u.lastname) AS `name`
    FROM
        users u
        INNER JOIN group_members gm ON gm.user_id = u.id
        INNER JOIN `groups` g ON gm.group_id = g.id
    WHERE
        g.`name` = 'Profesores';");

    return (array) $teachers;
}

function get_platforms(): array
{
    global $DB;

    $sql = "
        SELECT 
            id, 
            `name` AS `platform`
        FROM platforms
        WHERE visible = 1
    ";

    return $DB->get_records($sql);
}

function get_teacher($teacher_id): array
{
    global $DB;

    $teacher = $DB->get_record("
    SELECT
        u.*
    FROM
        users u
    WHERE
       u.id = ?;", [$teacher_id]);

    return (array) $teacher;
}

function get_student($id): array
{
    global $DB;

    $students = $DB->get_record("
    SELECT
        u.*,
        CASE WHEN u.active = 1 THEN 'active' ELSE 'inactive' END AS status
    FROM
        users u
    WHERE
       u.id = ?;", [$id]);

    return (array) $students;
}

function get_students($filter_idnumber = null, $filter_email = null, $only_active = false): array
{
    global $DB;

    $WHERE = 'u.type = ?';

    if (!empty($filter_idnumber)) {
        $WHERE .= ' AND u.idnumber LIKE "%'. $filter_idnumber .'%"';
    }

    if (!empty($filter_email)) {
        $WHERE .= ' AND u.email LIKE "%'. $filter_email .'%"';
    }

    if (!empty($only_active)) {
        $WHERE .= ' AND u.active = 1';
    }

    $students = $DB->get_records("
    SELECT
        u.*,
        CASE WHEN u.active = 1 THEN 'active' ELSE 'inactive' END AS status
    FROM
        users u
    WHERE
       $WHERE;", ['STUDENT']);

    return (array) $students;
}