<?php
/**
 * upload-record.php
 * POST /api/upload-record.php  (multipart/form-data)
 * Campos: sospechoso, cargo, notas, archivo (opcional)
 *
 * Guarda el expediente en MySQL y, si viene archivo, lo mueve a /uploads
 * con un nombre aleatorio para evitar choques o sobrescritura.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$usuario = usuarioActual(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$sospechoso = trim($_POST['sospechoso'] ?? '');
$cargo = trim($_POST['cargo'] ?? '');
$notas = trim($_POST['notas'] ?? '');

if ($sospechoso === '') {
    jsonResponse(['ok' => false, 'error' => 'El nombre del sospechoso es obligatorio.'], 422);
}
if ($cargo === '') {
    jsonResponse(['ok' => false, 'error' => 'El cargo es obligatorio.'], 422);
}

$archivoPath = null;
$archivoNombreOriginal = null;

// --- Manejo de archivo adjunto (opcional) ---
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $archivo = $_FILES['archivo'];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => 'Error al subir el archivo.'], 400);
    }

    // Límite de tamaño: 10 MB
    if ($archivo['size'] > 10 * 1024 * 1024) {
        jsonResponse(['ok' => false, 'error' => 'El archivo supera el límite de 10MB.'], 413);
    }

    // Solo permitir tipos de archivo esperados, verificando el contenido real
    // (no solo la extensión, que el usuario puede falsificar).
    $permitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!isset($permitidos[$mimeReal])) {
        jsonResponse(['ok' => false, 'error' => 'Solo se permiten archivos PDF, JPG o PNG.'], 415);
    }

    $extension = $permitidos[$mimeReal];
    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    $rutaDestino = __DIR__ . '/../uploads/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor.'], 500);
    }

    $archivoPath = 'uploads/' . $nombreArchivo;
    $archivoNombreOriginal = basename($archivo['name']);
}

$pdo = getDB();
$stmt = $pdo->prepare(
    'INSERT INTO expedientes (sospechoso, cargo, notas, archivo_path, archivo_nombre_original, creado_por)
     VALUES (:sospechoso, :cargo, :notas, :archivo_path, :archivo_nombre, :creado_por)'
);
$stmt->execute([
    'sospechoso' => $sospechoso,
    'cargo' => $cargo,
    'notas' => $notas,
    'archivo_path' => $archivoPath,
    'archivo_nombre' => $archivoNombreOriginal,
    'creado_por' => $usuario['id'],
]);

jsonResponse(['ok' => true, 'message' => 'Expediente registrado correctamente.', 'id' => (int) $pdo->lastInsertId()]);
