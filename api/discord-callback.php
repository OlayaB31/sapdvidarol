<?php
/**
 * discord-callback.php
 * GET /api/discord-callback.php?code=...&state=...
 *
 * Discord redirige aquí después de que el oficial autoriza la app.
 * Este archivo:
 *  1. Valida el "state" (anti-CSRF) contra el que guardamos en discord-link.php
 *  2. Intercambia el "code" por un access_token con Discord
 *  3. Pide /users/@me a Discord con ese token
 *  4. Guarda discord_id / discord_username / discord_avatar en el usuario
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$code || !$state) {
    jsonResponse(['ok' => false, 'error' => 'Falta el código de autorización de Discord.'], 400);
}

$pdo = getDB();

// 1. Validar state y recuperar a qué usuario pertenece
$stmt = $pdo->prepare('SELECT * FROM discord_oauth_estados WHERE state = :state AND creado_en > (NOW() - INTERVAL 10 MINUTE)');
$stmt->execute(['state' => $state]);
$estado = $stmt->fetch();

if (!$estado) {
    jsonResponse(['ok' => false, 'error' => 'Estado de verificación inválido o expirado. Intenta vincular de nuevo.'], 400);
}

// Invalidar el state inmediatamente (de un solo uso)
$pdo->prepare('DELETE FROM discord_oauth_estados WHERE id = :id')->execute(['id' => $estado['id']]);

// 2. Intercambiar code -> access_token
$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => DISCORD_REDIRECT_URI,
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$tokenResponse = curl_exec($ch);
$tokenStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenStatus !== 200) {
    jsonResponse(['ok' => false, 'error' => 'Discord rechazó la autorización.'], 400);
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    jsonResponse(['ok' => false, 'error' => 'No se recibió token de acceso de Discord.'], 400);
}

// 3. Pedir el perfil del usuario de Discord
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
]);
$profileResponse = curl_exec($ch);
curl_close($ch);

$profile = json_decode($profileResponse, true);
if (!isset($profile['id'])) {
    jsonResponse(['ok' => false, 'error' => 'No se pudo leer el perfil de Discord.'], 400);
}

$discordId = $profile['id'];
$discordUsername = $profile['username'] . (isset($profile['discriminator']) && $profile['discriminator'] !== '0' ? '#' . $profile['discriminator'] : '');
$discordAvatar = isset($profile['avatar'])
    ? "https://cdn.discordapp.com/avatars/{$discordId}/{$profile['avatar']}.png"
    : null;

// Evitar que la misma cuenta de Discord se vincule a dos oficiales distintos
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE discord_id = :did AND id != :uid');
$stmt->execute(['did' => $discordId, 'uid' => $estado['usuario_id']]);
if ($stmt->fetch()) {
    jsonResponse(['ok' => false, 'error' => 'Esa cuenta de Discord ya está vinculada a otro oficial.'], 409);
}

$stmt = $pdo->prepare(
    'UPDATE usuarios SET discord_id = :did, discord_username = :duser, discord_avatar = :davatar WHERE id = :uid'
);
$stmt->execute([
    'did' => $discordId,
    'duser' => $discordUsername,
    'davatar' => $discordAvatar,
    'uid' => $estado['usuario_id'],
]);

// Redirige de vuelta al portal con un indicador de éxito en la URL
header('Location: /?discord_vinculado=1');
exit;
