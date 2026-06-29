<?php
/**
 * auth.php
 * -----------------------------------------------------------
 * Funciones de sesión compartidas por todos los endpoints.
 * Usa un token de sesión propio guardado en la tabla `sesiones`,
 * enviado al navegador como cookie httpOnly (no accesible por JS,
 * lo que reduce el riesgo de robo de sesión vía XSS).
 * -----------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

const COOKIE_NAME = 'sapd_session';

function crearSesion(int $usuarioId): string {
    $pdo = getDB();
    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', time() + SESSION_LIFETIME_HOURS * 3600);

    $stmt = $pdo->prepare(
        'INSERT INTO sesiones (usuario_id, token, expira_en) VALUES (:uid, :token, :exp)'
    );
    $stmt->execute(['uid' => $usuarioId, 'token' => $token, 'exp' => $expira]);

    setcookie(COOKIE_NAME, $token, [
        'expires' => time() + SESSION_LIFETIME_HOURS * 3600,
        'path' => '/',
        'secure' => true,      // solo se envía sobre HTTPS
        'httponly' => true,    // JavaScript no puede leer esta cookie
        'samesite' => 'Lax',
    ]);

    return $token;
}

/**
 * Devuelve el usuario autenticado actual o null si no hay sesión válida.
 * Corta la ejecución con 401 si $required es true y no hay sesión.
 */
function usuarioActual(bool $required = true): ?array {
    $token = $_COOKIE[COOKIE_NAME] ?? null;
    if (!$token) {
        if ($required) jsonResponse(['ok' => false, 'error' => 'No has iniciado sesión.'], 401);
        return null;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT u.* FROM sesiones s
         JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.token = :token AND s.expira_en > NOW() AND u.activo = 1'
    );
    $stmt->execute(['token' => $token]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        if ($required) jsonResponse(['ok' => false, 'error' => 'Sesión inválida o expirada.'], 401);
        return null;
    }

    unset($usuario['password_hash']); // nunca devolver el hash al frontend
    return $usuario;
}

function cerrarSesion(): void {
    $token = $_COOKIE[COOKIE_NAME] ?? null;
    if ($token) {
        $pdo = getDB();
        $stmt = $pdo->prepare('DELETE FROM sesiones WHERE token = :token');
        $stmt->execute(['token' => $token]);
    }
    setcookie(COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
}
