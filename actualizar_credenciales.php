<?php
// actualizar_credenciales.php (en la raíz /ebank)
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usrSesion = $_SESSION['usuario'] ?? null;
if (!$usrSesion) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['dni']) || empty($input['dni'])) {
    echo json_encode(['success' => false, 'message' => 'DNI requerido']);
    exit;
}

$dni = rawurlencode($input['dni']);
$dataUpdate = [
    'qr_code' => $input['qr_code'] ?? null,
    'foto_url' => $input['foto_url'] ?? null
];

// Encriptar PIN solo si se envió un nuevo PIN
if (!empty($input['pin'])) {
    $dataUpdate['pin'] = password_hash($input['pin'], PASSWORD_BCRYPT);
}

try {
    // 1. Actualizar datos en Supabase
    supabaseQuery("alumnos?dni=eq.{$dni}", 'PATCH', $dataUpdate);

    // 2. REGISTRO DE AUDITORÍA / LOG
    $logData = [
        'usuario' => $usrSesion['usuario'] ?? $usrSesion['nombre'] ?? 'ADMIN',
        'accion' => 'ACTUALIZAR_CREDENCIALES',
        'detalle' => "Se vincularon credenciales (QR/PIN/Foto) al alumno DNI: {$input['dni']}",
        'fecha_hora' => date('Y-m-d H:i:s')
    ];

    // Se guarda en la tabla 'auditoria' de Supabase
    supabaseQuery('auditoria', 'POST', $logData);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}