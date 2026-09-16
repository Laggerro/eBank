<?php
// obtener_cursos.php (en la raíz /ebank)
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

exigirRoles(['ADMIN', 'CAJERO']);

try {
    // Consulta a la tabla 'cursos' ordenados por nombre
    $cursos = supabaseQuery('cursos?select=id,nombre&order=nombre.asc');

    echo json_encode([
        'success' => true,
        'cursos'  => is_array($cursos) ? $cursos : []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'cursos'  => []
    ]);
}