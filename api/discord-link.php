<?php
/**
 * discord-link.php
 * GET /api/discord-link.php
 *
 * El usuario YA inició sesión con usuario/contraseña. Este endpoint lo
 * redirige a Discord para autorizar la vinculación de su cuenta.
 * Discord, al terminar, regresa a discord-callback.php.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$usuario = usuarioActual(true); // exige sesión ya iniciada

$pdo = getDB();
$state = bin2hex(random_bytes(20));

$stmt = $pdo->prepare('INSERT INTO discord_oauth_estados (usuario_id, state) VALUES (:uid, :state)');
$stmt->execute(['uid' => $usuario['id'], 'state' => $state]);

$params = http_build_query([
    'client_id' => DISCORD_CLIENT_ID,
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify',
    'state' => $state,
]);

header('Location: https://discord.com/api/oauth2/authorize?' . $params);
exit;
