<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

if (empty($_SESSION['usuario']['rol'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión ausente o expirada.']);
    exit;
}

echo json_encode(['success' => true, 'rol' => $_SESSION['usuario']['rol']]);