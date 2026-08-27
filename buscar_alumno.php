<?php
// ebank/buscar_alumno.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$dni = trim($_GET['dni'] ?? '');

if (empty($dni)) {
    echo json_encode(['success' => false, 'message' => 'DNI no proporcionado']);
    exit;
}

try {
    // Buscar en la tabla alumnos por DNI
    $res = supabaseQuery("alumnos?dni=eq." . urlencode($dni), 'GET');

    if (isset($res['code']) || isset($res['error'])) {
        echo json_encode(['success' => false, 'message' => 'Error al consultar la BD']);
        exit;
    }

    if (is_array($res) && count($res) > 0) {
        echo json_encode([
            'success' => true,
            'encontrado' => true,
            'alumno' => $res[0] // Devuelve los datos de la fila hallada
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'encontrado' => false
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}