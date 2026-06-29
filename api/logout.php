<?php
/**
 * logout.php
 * POST /api/logout.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cerrarSesion();
jsonResponse(['ok' => true]);
