<?php
// /ebank/obtener_clientes.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado: Sesión no iniciada']);
    exit;
}

try {
    // 🔍 Agregamos el filtro registrados=eq.true para traer SOLO los confirmados
    $endpoint = 'alumnos?select=id,dni,nombre_apellido,curso,foto_url,codigo_qr&registrado=eq.true&order=nombre_apellido.asc';
    
    $resAlumnos = supabaseQuery($endpoint, 'GET');

    if (isset($resAlumnos['code']) || isset($resAlumnos['error'])) {
        $msgError = $resAlumnos['message'] ?? 'Error al consultar la tabla alumnos';
        echo json_encode(['success' => false, 'message' => $msgError, 'alumnos' => []]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'alumnos' => is_array($resAlumnos) ? $resAlumnos : []
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'alumnos' => []]);
}