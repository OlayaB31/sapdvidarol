<?php
/**
 * login.php
 * POST /api/login.php
 * Body JSON: { username, password }
 *
 * Verifica usuario/contraseña contra MySQL y crea una sesión.
 * Incluye límite de intentos fallidos por IP para frenar fuerza bruta.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

// --- Límite simple de intentos (archivo temporal por IP) ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
$lockFile = sys_get_temp_dir() . '/sapd_login_' . md5($ip) . '.json';
$intentos = file_exists($lockFile) ? json_decode(file_get_contents($lockFile), true) : ['count' => 0, 'ts' => time()];

if (time() - $intentos['ts'] > 600) {
    $intentos = ['count' => 0, 'ts' => time()]; // resetea cada 10 minutos
}
if ($intentos['count'] >= 8) {
    jsonResponse(['ok' => false, 'error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'], 429);
}

$body = readJsonBody();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '' || $password === '') {
    jsonResponse(['ok' => false, 'error' => 'Usuario y contraseña son obligatorios.'], 422);
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE username = :username AND activo = 1');
$stmt->execute(['username' => $username]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
    $intentos['count']++;
    file_put_contents($lockFile, json_encode($intentos));
    // Mensaje genérico a propósito: no revelar si fue el usuario o la contraseña lo incorrecto.
    jsonResponse(['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'], 401);
}

// Login correcto: limpiar contador de intentos
if (file_exists($lockFile)) unlink($lockFile);

$pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id')
    ->execute(['id' => $usuario['id']]);

crearSesion((int) $usuario['id']);

unset($usuario['password_hash']);
jsonResponse(['ok' => true, 'usuario' => $usuario]);
