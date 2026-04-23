<?php
// Compatibilidad para PHP < 8 (polyfills)
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

// Detectar host
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$host = preg_replace('/:\d+$/', '', strtolower($host));

// Detectar entorno por dominio
if (str_ends_with($host, '.local') || in_array($host, ['localhost', '127.0.0.1'], true)) {
    $entorno = 'local';
    $dominioBaseLocal = '.tuoperador.local';
    $sub = str_replace($dominioBaseLocal, '', $host);
} elseif (str_contains($host, '.qa.tuoperador.net')) {
    $entorno = 'qa';
    $sub = str_replace('.qa.tuoperador.net', '', $host);
} else {
    $entorno = 'prod';
    $sub = str_replace('.tuoperador.net', '', $host);
}

// Limpieza
$sub = ltrim(str_replace('www.', '', $sub), '.');
if ($sub === '' || $sub === 'tuoperador') {
    die('No se especificó empresa');
}

// Conexión a la BD CMS (usa tus credenciales por entorno)
$local = ($entorno === 'local');
$cms_host = getenv('CMS_DB_HOST') ?: 'localhost';
$cms_user = getenv('CMS_DB_USER') ?: ($local ? 'root' : 'admin');
$cms_pass = getenv('CMS_DB_PASS') ?: ($local ? '' : 'Admin#2025!');
$cms_name = getenv('CMS_DB_NAME') ?: 'cms_admin';

$mysqli = @new mysqli($cms_host, $cms_user, $cms_pass, $cms_name);
if ($mysqli->connect_errno) {
    die('Error de conexión CMS: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// Resolver CMS_PAGE_ID y APP_NAME
$stmt = $mysqli->prepare("
    SELECT p.id_pagina, COALESCE(p.nombre, e.nombre) AS app_name
    FROM pagina_cms p
    JOIN empresas_cms e ON e.id_empresa = p.id_empresa
    WHERE e.subdominio = ? AND p.estatus = 'activo'
    LIMIT 1
");
$stmt->bind_param('s', $sub);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$mysqli->close();

if (!$row) {
    die('Empresa no encontrada');
}

// Definir constantes (evitar redefinir si ya existen)
if (!defined('CMS_PAGE_ID')) define('CMS_PAGE_ID', (int)$row['id_pagina']);
if (!defined('APP_NAME'))    define('APP_NAME', $row['app_name'] ?: $sub);

// BASE_URL por entorno (solo si no está definida aún)
if (!defined('BASE_URL')) {
    if ($entorno === 'local') {
        define('BASE_URL', "http://{$sub}.tuoperador.local/");
    } elseif ($entorno === 'qa') {
        define('BASE_URL', "https://{$sub}.qa.tuoperador.net/");
    } else {
        define('BASE_URL', "https://{$sub}.tuoperador.net/");
    }
}