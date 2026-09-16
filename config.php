<?php
// C:\xampp\htdocs\eBank\config.php
ini_set('session.gc_maxlifetime', '2592000');
session_set_cookie_params([
    'lifetime' => 2592000,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

function exigirRoles(array $roles): void
{
    $rolActual = strtoupper(trim($_SESSION['usuario']['rol'] ?? ''));

    if (!in_array($rolActual, $roles, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $rolActual === ''
                ? 'Sesión ausente o expirada. Vuelva a iniciar sesión.'
                : 'Acceso no autorizado para el rol ' . $rolActual . '.'
        ]);
        exit;
    }
}

define('SUPABASE_URL', 'https://hkxigkizqedbirytsrmb.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhreGlna2l6cWVkYmlyeXRzcm1iIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODY1MTIzNzEsImV4cCI6MjEwMjA4ODM3MX0.DLgrW2Z-32cJljfV0MYUKsxKcsG2C0WFI58KTVIQXJU');

// QR Maestro oculto en el servidor
define('QR_MAESTRO_ADMIN', '1'); //ObiWanKenobi


// imgBB key
define('IMGBB_API_KEY', '61a76cc12d06bd22948b4b5b76f5b45e');
/**
 * Función global para hacer consultas a la API REST de Supabase mediante cURL
 */
function supabaseQuery($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . ($_SESSION['supabase_access_token'] ?? SUPABASE_KEY),
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Desactivar verificación SSL solo para pruebas locales en XAMPP si cURL da problemas
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // 1. Definimos explícitamente el método (GET, POST, PATCH, DELETE, etc.)
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

    // 2. Si enviamos un Payload y NO es un GET, adjuntamos los datos JSON
    if ($data !== null && strtoupper($method) !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => $error_msg];
    }

    curl_close($ch);

    return json_decode($response, true);
}

if (!function_exists('registrarAuditoria')) {
    function registrarAuditoria($tipoEvento, $usuarioOrigen, $usuarioDestino, $detalle = '') {
        $payload = [
            'tipo_evento'     => $tipoEvento,
            'usuario_origen'  => $usuarioOrigen,
            'usuario_destino' => $usuarioDestino,
            'detalle'         => $detalle
        ];
        return supabaseQuery('logs_auditoria', 'POST', $payload);
    }
}

function supabaseAuthRequest($endpoint, $data) {
    $ch = curl_init(SUPABASE_URL . '/auth/v1/' . ltrim($endpoint, '/'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $httpCode, 'data' => json_decode($response, true)];
}