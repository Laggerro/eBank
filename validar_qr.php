<?php
// validar_qr.php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$qrIngresado = trim($input['qr_code'] ?? '');

if (!isset($_SESSION['pre_auth_user'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida o expirada.']);
    exit;
}

// Validar el QR Maestro contra la constante definida en el servidor
if ($qrIngresado !== QR_MAESTRO_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'QR Maestro o Código Inválido.']);
    exit;
}

// QR VÁLIDO: Confirmar la sesión definitiva
$_SESSION['usuario'] = $_SESSION['pre_auth_user'];
unset($_SESSION['pre_auth_user']); // Limpiar temp

$rol = $_SESSION['usuario']['rol'];
$redirects = [
    'ADMIN' => 'pages/admin-dashboard.php',
    'CAJERO' => 'pages/cajero.php',
    'POSNET' => 'pages/posnet.php'
];

$destino = $redirects[$rol] ?? 'login.php';

echo json_encode([
    'success' => true,
    'redirect' => $destino,
    'user' => $_SESSION['usuario']
]);