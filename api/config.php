<?php
/**
 * config.php
 * -----------------------------------------------------------
 * Configuración de conexión a MySQL y constantes del sistema.
 *
 * EDITA estos 4 valores con los datos que te dio tu hosting
 * (los encuentras en cPanel > Bases de datos MySQL).
 * -----------------------------------------------------------
 */

define('DB_HOST', 'localhost');           // casi siempre es 'localhost' en hosting compartido
define('DB_NAME', 'sapdvida_rol2026');     // ej: cpaneluser_sapd
define('DB_USER', 'sapdvida_olaya');    // ej: cpaneluser_admin
define('DB_PASS', 'Z32MCr=W;g(a');

// --- Discord OAuth2 (lo obtienes en https://discord.com/developers/applications) ---
define('DISCORD_CLIENT_ID', '1371727629547339797');
define('DISCORD_CLIENT_SECRET', 'p7HPM6_MKEuWohvqfqNwZ5nMjtVPus2-');
// Debe coincidir EXACTAMENTE (incluyendo https:// y la ruta) con lo que registres
// en el portal de desarrolladores de Discord, en "Redirects".
define('DISCORD_REDIRECT_URI', 'https://sapdvidarol.vercel.app/api/discord-callback.php');

// --- Seguridad de sesión ---
// Genera un valor aleatorio largo y único, distinto en cada instalación.
// Puedes generarlo en https://randomkeygen.com/ (usa el de "CodeIgniter Encryption Keys")
define('APP_SECRET', '7c02fa137832cfccfb47b9ab38eae28993a8b91e2d0a0a5ff10e42ec114bed85');

define('SESSION_LIFETIME_HOURS', 12);

/**
 * Devuelve una conexión PDO a MySQL.
 * Se usa PDO con prepared statements en todo el proyecto para evitar
 * inyección SQL — nunca se concatenan strings directamente en queries.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'No se pudo conectar a la base de datos.']));
        }
    }
    return $pdo;
}

/**
 * Responde en JSON y termina la ejecución.
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Lee el cuerpo JSON de la petición entrante.
 */
function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
