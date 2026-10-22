<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$path = __DIR__ . '/marcadores.txt';
if (!is_file($path)) {
    echo '[]';
    exit;
}

$contents = file_get_contents($path);
if ($contents === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar os relatos.']);
    exit;
}

$reports = [];
foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
    $report = json_decode($line, true);
    if (!is_array($report) && preg_match('/^Latitude:\s*(-?\d+(?:\.\d+)?),\s*Longitude:\s*(-?\d+(?:\.\d+)?),\s*Descrição:\s*(.*)$/u', $line, $legacy)) {
        $legacyLatitude = filter_var($legacy[1], FILTER_VALIDATE_FLOAT);
        $legacyLongitude = filter_var($legacy[2], FILTER_VALIDATE_FLOAT);
        if (
            $legacyLatitude !== false && $legacyLongitude !== false &&
            $legacyLatitude >= -90 && $legacyLatitude <= 90 &&
            $legacyLongitude >= -180 && $legacyLongitude <= 180
        ) {
            $reports[] = [
                'latitude' => (float) $legacyLatitude,
                'longitude' => (float) $legacyLongitude,
                'type' => 'outro',
                'description' => substr($legacy[3], 0, 3000)
            ];
        }
        continue;
    }

    if (
        !is_array($report) ||
        !isset($report['latitude'], $report['longitude'], $report['type'], $report['description']) ||
        !is_numeric($report['latitude']) ||
        !is_numeric($report['longitude']) ||
        $report['latitude'] < -90 || $report['latitude'] > 90 ||
        $report['longitude'] < -180 || $report['longitude'] > 180 ||
        !in_array($report['type'], ['acesso', 'estado', 'sinalizacao', 'outro'], true)
    ) {
        continue;
    }

    $reports[] = [
        'latitude' => (float) $report['latitude'],
        'longitude' => (float) $report['longitude'],
        'type' => $report['type'],
        'description' => substr((string) $report['description'], 0, 3000)
    ];
}

try {
    echo json_encode($reports, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível preparar os relatos.']);
}
