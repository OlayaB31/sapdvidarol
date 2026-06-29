<?php
/**
 * crear-primer-usuario.php
 * -----------------------------------------------------------
 * ÚSALO UNA SOLA VEZ para crear tu primer oficial/administrador,
 * luego BÓRRALO del servidor. Dejarlo activo es un riesgo de seguridad,
 * porque cualquiera que conozca la URL podría crear cuentas.
 *
 * Cómo usarlo:
 *  1. Sube este archivo junto con la carpeta /api a tu hosting.
 *  2. Edita los 4 valores de abajo (usuario, contraseña, nombre, placa).
 *  3. Abre en el navegador: https://tudominio.com/crear-primer-usuario.php
 *  4. Verás un mensaje de éxito.
 *  5. BORRA este archivo del servidor inmediatamente.
 * -----------------------------------------------------------
 */

require_once __DIR__ . '/api/config.php';

// --- EDITA ESTOS 4 VALORES ---
$username = 'admin';
$password = 'CambiaEstaPassword123!';
$nombreCompleto = 'Oficial J. Reyes';
$numeroPlaca = '4471';
// -------------------------------

$pdo = getDB();

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = :u');
$stmt->execute(['u' => $username]);

if ($stmt->fetch()) {
    die('Ya existe un usuario con ese nombre. Si quieres crear otro, cambia el valor de $username arriba.');
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (username, password_hash, nombre_completo, rango, numero_placa)
     VALUES (:username, :hash, :nombre, "Sargento", :placa)'
);
$stmt->execute([
    'username' => $username,
    'hash' => $hash,
    'nombre' => $nombreCompleto,
    'placa' => $numeroPlaca,
]);

echo '<h2>✅ Usuario creado correctamente</h2>';
echo '<p>Usuario: <b>' . htmlspecialchars($username) . '</b></p>';
echo '<p><strong style="color:red;">Borra este archivo (crear-primer-usuario.php) del servidor ahora mismo.</strong></p>';
