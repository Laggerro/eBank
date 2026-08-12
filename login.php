<?php
// login.php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$user = trim($input['username'] ?? '');
$pass = trim($input['password'] ?? '');

if (empty($user)) {
    echo json_encode(['success' => false, 'message' => 'El usuario es requerido.']);
    exit;
}

// 1. CASO ESPECIAL: CONSULTA LIBRE
if (strtolower($user) === 'consulta') {
    $_SESSION['usuario'] = [
        'id' => 0,
        'usuario' => 'consulta',
        'nombre' => 'Consulta Pública',
        'rol' => 'CONSULTA'
    ];
    echo json_encode(['success' => true, 'redirect' => 'consulta-saldo.html']);
    exit;
}

$userData = null;

// 2. BUSCAR EN TABLA 'usuarios_banco' (ADMIN / CAJERO)
$resUser = supabaseQuery("usuarios_banco?usuario=eq." . urlencode($user) . "&select=*");

if (!empty($resUser) && is_array($resUser) && !isset($resUser['error']) && isset($resUser[0])) {
    $u = $resUser[0];
    
    // Verificación de contraseña (funciona si está en texto plano o con password_hash)
    $passOk = password_verify($pass, $u['password_hash']) || ($u['password_hash'] === $pass);
    
    if (!$passOk) {
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
        exit;
    }

    if ($u['activo'] == false) {
        echo json_encode(['success' => false, 'message' => 'El usuario se encuentra desactivado.']);
        exit;
    }

    $rolNormalizado = strtoupper(trim($u['rol'] ?? 'CAJERO'));

    $userData = [
        'id' => $u['id'],
        'usuario' => $u['usuario'],
        'nombre' => $u['nombre'] ?? $u['usuario'],
        'rol' => $rolNormalizado,
        'puede_retirar' => ($u['puede_retirar'] == true || $u['puede_retirar'] == 1)
    ];
} else {
    // 3. SI NO ESTÁ EN 'usuarios_banco', BUSCAR EN TABLA 'posnets'
    $resPosnet = supabaseQuery("posnets?usuario=eq." . urlencode($user) . "&select=*");

    if (!empty($resPosnet) && is_array($resPosnet) && !isset($resPosnet['error']) && isset($resPosnet[0])) {
        $p = $resPosnet[0];

        $passOk = password_verify($pass, $p['password']) || ($p['password'] === $pass);

        if (!$passOk) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
            exit;
        }

        if ($p['habilitado'] == false) {
            echo json_encode(['success' => false, 'message' => 'El POSNET se encuentra deshabilitado.']);
            exit;
        }

        $userData = [
            'id' => $p['id'],
            'usuario' => $p['usuario'],
            'nombre' => $p['nombre_posnet'] ?? $p['usuario'],
            'rol' => 'POSNET',
            'puede_retirar' => false
        ];
    }
}

// SI NO EXISTE EN NINGUNA DE LAS DOS TABLAS
if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'El usuario o posnet no existe.']);
    exit;
}

// Guardar temporalmente en sesión pre-autorizada
$_SESSION['pre_auth_user'] = $userData;

echo json_encode([
    'success' => true,
    'require_qr' => true,
    'message' => 'Credenciales OK. Solicitando QR Maestro Admin.'
]);