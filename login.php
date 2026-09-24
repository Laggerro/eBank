<?php
// login.php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? 'login');
$user = trim($input['username'] ?? '');
$pass = trim($input['password'] ?? '');

if ($action === 'forgot_password') {
    if (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ingresá un email válido para recuperar la contraseña.']);
        exit;
    }

    $recover = supabaseAuthRequest('recover', [
        'email' => strtolower($user),
        'redirect_to' => appBaseUrl() . '/reset-password.php'
    ]);

    if ($recover['status'] >= 200 && $recover['status'] < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Si el email está registrado, te enviamos un enlace para restablecer la contraseña.'
        ]);
        exit;
    }

    $message = $recover['data']['error_description']
        ?? $recover['data']['msg']
        ?? $recover['data']['message']
        ?? 'No se pudo enviar el correo de recuperación.';

    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($action === 'reset_password') {
    $newPassword = trim((string)($input['new_password'] ?? ''));
    $accessToken = trim((string)($input['access_token'] ?? ''));

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.']);
        exit;
    }

    if ($accessToken === '') {
        echo json_encode(['success' => false, 'message' => 'El enlace de recuperación no es válido.']);
        exit;
    }

    $update = supabaseAuthRequest('user', ['password' => $newPassword], 'PUT', $accessToken);

    if ($update['status'] >= 200 && $update['status'] < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Contraseña restablecida correctamente. Ya podés iniciar sesión.'
        ]);
        exit;
    }

    $message = $update['data']['error_description']
        ?? $update['data']['msg']
        ?? $update['data']['message']
        ?? 'No se pudo restablecer la contraseña.';

    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

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
        'rol' => 'CONSULTA',
        'puede_retirar' => false,
        'puede_blanquear' => false
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
        'puede_retirar' => ($u['puede_retirar'] == true || $u['puede_retirar'] == 1),
        // AGREGADO: Mapeo del nuevo permiso
        'puede_blanquear' => (!empty($u['puede_blanquear']) && ($u['puede_blanquear'] == true || $u['puede_blanquear'] == 1))
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
            'puede_retirar' => false,
            'puede_blanquear' => false
        ];
    }
}

// 3. Alumnos: Supabase Auth + perfil vinculado. El usuario es el email.
if (!$userData && filter_var($user, FILTER_VALIDATE_EMAIL) && $pass !== '') {
    $auth = supabaseAuthRequest('token?grant_type=password', [
        'email' => strtolower($user),
        'password' => $pass
    ]);

    if ($auth['status'] < 200 || $auth['status'] >= 300) {
        $authMessage = $auth['data']['error_description']
            ?? $auth['data']['msg']
            ?? $auth['data']['message']
            ?? 'No se pudo iniciar sesión con ese email.';
        echo json_encode(['success' => false, 'message' => $authMessage]);
        exit;
    }

    if (!empty($auth['data']['user']['id'])) {
        $_SESSION['supabase_access_token'] = $auth['data']['access_token'] ?? null;
        $authUserId = urlencode($auth['data']['user']['id']);
        $perfil = supabaseQuery(
            'perfiles_alumnos?id=eq.' . $authUserId . '&activo=eq.true&select=id,alumno_id,alumnos(*)',
            'GET'
        );

        if (!empty($perfil[0]['alumnos'])) {
            $alumno = $perfil[0]['alumnos'];
            $_SESSION['usuario'] = [
                'id' => $alumno['id'],
                'auth_id' => $auth['data']['user']['id'],
                'usuario' => $user,
                'nombre' => $alumno['nombre_apellido'],
                'rol' => 'ALUMNO'
            ];
            supabaseQuery('perfiles_alumnos?id=eq.' . $authUserId, 'PATCH', ['ultimo_acceso' => date('c')]);
            echo json_encode(['success' => true, 'redirect' => 'pages/alumno.php']);
            exit;
        }

        unset($_SESSION['supabase_access_token']);
        echo json_encode(['success' => false, 'message' => 'El email existe, pero todavía no está vinculado a un alumno.']);
        exit;
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