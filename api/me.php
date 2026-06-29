<?php
/**
 * me.php
 * GET /api/me.php
 *
 * Devuelve los datos del usuario con sesión activa.
 * El portal llama esto al cargar para decidir si muestra el login
 * o el dashboard directamente.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$usuario = usuarioActual(false);

if (!$usuario) {
    jsonResponse(['ok' => false, 'logueado' => false]);
}

jsonResponse(['ok' => true, 'logueado' => true, 'usuario' => $usuario]);
