<?php
// /ebank/obtener_directorio.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

try {
    // 1. Alumnos (Traemos todos los alumnos, tengan o no QR)
   $resAlumnos = supabaseQuery('alumnos?select=id,dni,nombre_apellido,curso,foto_url,codigo_qr&order=nombre_apellido.asc', 'GET');
    $alumnos = (is_array($resAlumnos) && !isset($resAlumnos['code'])) ? $resAlumnos : [];

    // 2. Usuarios del Banco (CORREGIDO: la tabla exacta es 'usuarios_banco')
    $resUsuarios = supabaseQuery('usuarios_banco?select=id,usuario,nombre,rol,activo&order=nombre.asc', 'GET');
    $usuarios = (is_array($resUsuarios) && !isset($resUsuarios['code'])) ? $resUsuarios : [];

    echo json_encode([
        'success'  => true,
        'alumnos'  => $alumnos,
        'usuarios' => $usuarios
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}