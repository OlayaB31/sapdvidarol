<?php
/**
 * records.php
 * GET /api/records.php
 *
 * Devuelve los expedientes más recientes para mostrarlos en el portal.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

usuarioActual(true); // exige sesión

$pdo = getDB();
$stmt = $pdo->query(
    'SELECT e.id, e.sospechoso, e.cargo, e.estado, e.creado_en,
            u.nombre_completo AS oficial, u.rango
     FROM expedientes e
     LEFT JOIN usuarios u ON u.id = e.creado_por
     ORDER BY e.creado_en DESC
     LIMIT 25'
);

jsonResponse(['ok' => true, 'expedientes' => $stmt->fetchAll()]);
