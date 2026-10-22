<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

function reportError($message, $status = 400)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

$latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$description = trim($_POST['descricao'] ?? '');
$type = $_POST['tipo_problema'] ?? '';
$validTypes = ['acesso', 'estado', 'sinalizacao', 'outro'];

if (
    $latitude === false || $latitude === null || $latitude < -90 || $latitude > 90 ||
    $longitude === false || $longitude === null || $longitude < -180 || $longitude > 180 ||
    !in_array($type, $validTypes, true) ||
    $description === '' || strlen($description) > 3000
) {
    reportError('Dados inválidos. Volte ao mapa, escolha uma localização e reveja o formulário.');
}

$files = $_FILES['imagens'] ?? null;
$uploadedFiles = [];
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if ($files !== null) {
    if (!isset($files['name'], $files['tmp_name'], $files['error'], $files['size']) || !is_array($files['name'])) {
        reportError('Não foi possível ler as fotografias. Tente novamente.');
    }

    if (count($files['name']) > 3) {
        reportError('Pode anexar no máximo 3 fotografias.');
    }

    $uploadDirectory = __DIR__ . '/uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        reportError('Não foi possível guardar as fotografias. Tente novamente mais tarde.', 500);
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($files['name'] as $index => $originalName) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            reportError('Uma das fotografias não foi carregada corretamente.');
        }

        $temporaryPath = $files['tmp_name'][$index] ?? '';
        $size = (int) ($files['size'][$index] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            reportError('Cada fotografia deve ter até 5 MB e ser carregada novamente.');
        }

        $mimeType = $fileInfo->file($temporaryPath);
        if (!isset($allowedMimeTypes[$mimeType])) {
            reportError('Formato de fotografia inválido. Use JPG, PNG ou WebP.');
        }

        try {
            $storedName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
        } catch (Exception $exception) {
            reportError('Não foi possível guardar as fotografias. Tente novamente mais tarde.', 500);
        }

        $destination = $uploadDirectory . '/' . $storedName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            reportError('Não foi possível guardar uma das fotografias. Tente novamente mais tarde.', 500);
        }
        $uploadedFiles[] = 'uploads/' . $storedName;
    }
}

$report = [
    'date' => date(DATE_ATOM),
    'latitude' => (float) $latitude,
    'longitude' => (float) $longitude,
    'type' => $type,
    'description' => $description,
    'images' => $uploadedFiles
];

try {
    $record = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (JsonException $exception) {
    foreach ($uploadedFiles as $uploadedFile) {
        unlink(__DIR__ . '/' . $uploadedFile);
    }
    reportError('Não foi possível preparar o relato. Tente novamente.', 500);
}

if (file_put_contents(__DIR__ . '/marcadores.txt', $record, FILE_APPEND | LOCK_EX) === false) {
    foreach ($uploadedFiles as $uploadedFile) {
        unlink(__DIR__ . '/' . $uploadedFile);
    }
    reportError('Não foi possível guardar o relato. Verifique as permissões e tente novamente.', 500);
}

header('Location: agradecimento.php', true, 303);
exit;
