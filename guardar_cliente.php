<?php
// /ebank/guardar_cliente.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

exigirRoles(['ADMIN', 'CAJERO']);

function subirAImgBB($base64Data) {
    if (!defined('IMGBB_API_KEY') || empty(IMGBB_API_KEY)) {
        throw new Exception("Falta configurar IMGBB_API_KEY en config.php");
    }

    if (strpos($base64Data, 'data:image') === 0) {
        $partes = explode(',', $base64Data);
        $base64Data = $partes[1] ?? $base64Data;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . IMGBB_API_KEY);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $base64Data]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Error al subir imagen a ImgBB. HTTP " . $httpCode);
    }

    $resData = json_decode($response, true);
    if (!empty($resData['success']) && isset($resData['data']['url'])) {
        return $resData['data']['url'];
    }

    throw new Exception("No se pudo obtener la URL de la imagen en ImgBB.");
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos JSON válidos']);
    exit;
}

try {
    if (($data['accion'] ?? '') === 'eliminar') {
        $alumnoId = trim((string)($data['id'] ?? ''));
        if ($alumnoId === '') {
            throw new Exception('ID de alumno no provisto.');
        }

        $resAlumno = supabaseQuery(
            'alumnos?id=eq.' . urlencode($alumnoId) . '&registrado=eq.true&select=id,saldo,nombre_apellido',
            'GET'
        );
        if (empty($resAlumno[0])) {
            throw new Exception('El alumno no existe o ya fue dado de baja.');
        }

        $saldo = (float)($resAlumno[0]['saldo'] ?? 0);
        if (abs($saldo) > 0.000001) {
            throw new Exception('No se puede dar de baja: la cuenta tiene saldo $' . number_format($saldo, 2, ',', '.'));
        }

        $resBaja = supabaseQuery('rpc/dar_baja_alumno_sin_saldo', 'POST', [
            'p_alumno_id' => $alumnoId
        ]);
        if (isset($resBaja['error']) || isset($resBaja['code'])) {
            $mensajeBaja = $resBaja['message'] ?? '';
            if (stripos($mensajeBaja, 'dar_baja_alumno_sin_saldo') !== false || stripos($mensajeBaja, 'schema cache') !== false) {
                throw new Exception('La función de baja todavía no está creada en Supabase. Ejecutá nuevamente sql/portal_alumnos.sql.');
            }
            throw new Exception($mensajeBaja ?: 'No se pudo dar de baja al alumno.');
        }
        $resultadoBaja = is_array($resBaja) && isset($resBaja[0]) ? $resBaja[0] : $resBaja;
        if (isset($resultadoBaja['exito']) && !$resultadoBaja['exito']) {
            throw new Exception($resultadoBaja['mensaje'] ?? 'No se puede dar de baja al alumno.');
        }
        registrarAuditoria('BAJA_ALUMNO', $_SESSION['usuario']['usuario'] ?? $_SESSION['usuario']['nombre'] ?? 'ADMIN', $alumnoId, 'Baja lógica de alumno con saldo en cero.');

        echo json_encode(['success' => true, 'message' => 'Alumno dado de baja correctamente.']);
        exit;
    }

    $id = !empty($data['id']) ? trim($data['id']) : null;
    $dniNuevo = trim($data['dni'] ?? '');
    $nombreApellido = trim($data['nombre_apellido'] ?? '');
    $esEdicion = !empty($data['es_edicion']);
    $codigoQrNuevo = trim($data['codigo_qr'] ?? '');
    $emailCuenta = strtolower(trim($data['email_cuenta'] ?? ''));
    $passwordCuenta = (string)($data['password_cuenta'] ?? '');
    $crearCuenta = !empty($data['crear_cuenta']);
    $pinNuevo = trim((string)($data['pin'] ?? ''));

    if (empty($dniNuevo) || empty($nombreApellido)) {
        throw new Exception("El DNI y el Nombre Completo son obligatorios.");
    }
    if ($pinNuevo !== '' && !preg_match('/^\d{4}$/', $pinNuevo)) {
        throw new Exception('El PIN debe contener exactamente 4 números.');
    }

    if ($crearCuenta && !filter_var($emailCuenta, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Ingresá un email válido para crear la cuenta nueva.');
    }
    if ($crearCuenta && strlen($passwordCuenta) < 6) {
        throw new Exception('La contraseña inicial debe tener al menos 6 caracteres.');
    }

    $filtroExclusion = $id ? '&id=neq.' . urlencode($id) : '';
    $resDni = supabaseQuery(
        'alumnos?dni=eq.' . urlencode($dniNuevo) . $filtroExclusion . '&select=id&limit=1',
        'GET'
    );
    if (is_array($resDni) && !empty($resDni[0])) {
        throw new Exception('El DNI ya está registrado en otro alumno.');
    }

    if ($codigoQrNuevo !== '') {
        $resQr = supabaseQuery(
            'alumnos?codigo_qr=eq.' . urlencode($codigoQrNuevo) . $filtroExclusion . '&select=id&limit=1',
            'GET'
        );
        if (is_array($resQr) && !empty($resQr[0])) {
            throw new Exception('El código QR ya está asignado a otro alumno.');
        }
    }

    $urlFoto = null;
    if (!empty($data['foto_base64'])) {
        $urlFoto = subirAImgBB($data['foto_base64']);
    }

    // Armamos el payload con lo que se modifica
    $payload = [
        'dni'             => $dniNuevo,
        'nombre_apellido' => $nombreApellido,
        'curso'           => $data['curso'] ?? '',
        'codigo_qr'       => $codigoQrNuevo !== '' ? $codigoQrNuevo : null,
        'registrado'      => true // 👈 Marca como registrado al guardar
    ];

    if ($urlFoto) {
        $payload['foto_url'] = $urlFoto;
    }

    if ($pinNuevo !== '') {
        $payload['pin'] = password_hash($pinNuevo, PASSWORD_BCRYPT);
    }

    // Si tiene ID (ya sea por edición o por autocompletado del CSV préviamene importado)
    if ($id) {
        // Hacemos PATCH para actualizar la fila existente y pasar registrado a true
        $endpoint = 'alumnos?id=eq.' . urlencode($id);
        $metodo   = 'PATCH';
    } else {
        // Si no existía en la base de datos, se crea un registro totalmente nuevo
        $endpoint        = 'alumnos';
        $metodo          = 'POST';
        $payload['saldo'] = 0.0;
    }

    // Ejecutamos la consulta a Supabase
    $res = supabaseQuery($endpoint, $metodo, $payload);



    if (isset($res['code']) || isset($res['error'])) {
        if (($res['code'] ?? '') === '23505' && strpos($res['message'] ?? '', 'alumnos_codigo_qr_unique') !== false) {
            throw new Exception('El código QR ya está asignado a otro alumno.');
        }
        $msgError = $res['message'] ?? 'Error al procesar en Supabase';
        echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $msgError, 'raw' => $res]);
        exit;
    }

    if ($crearCuenta) {
        $alumnoCreado = is_array($res) && isset($res[0]) ? $res[0] : null;
        if (!$alumnoCreado && $id) {
            $resAlumnoActual = supabaseQuery(
                'alumnos?id=eq.' . urlencode($id) . '&select=id',
                'GET'
            );
            $alumnoCreado = is_array($resAlumnoActual) && isset($resAlumnoActual[0]) ? $resAlumnoActual[0] : null;
        }
        if (!$alumnoCreado || empty($alumnoCreado['id'])) {
            throw new Exception('No se pudo obtener el alumno creado para vincular la cuenta.');
        }

        $auth = supabaseAuthRequest('signup', [
            'email' => $emailCuenta,
            'password' => $passwordCuenta,
            'data' => ['alumno_id' => $alumnoCreado['id']]
        ]);
        $authData = is_array($auth['data']) ? $auth['data'] : [];
        $authUser = isset($authData['user']) && is_array($authData['user'])
            ? $authData['user']
            : $authData;
        $authUserId = $authUser['id'] ?? '';
        $emailYaRegistrado = is_array($authUser['identities'] ?? null)
            && count($authUser['identities']) === 0;

        if ($auth['status'] < 200 || $auth['status'] >= 300 || empty($authUserId) || $emailYaRegistrado) {
            $mensajeCuenta = $emailYaRegistrado
                ? 'Ese email ya tiene una cuenta en Supabase Auth. Usá otro email o vinculá la cuenta existente.'
                : ($authData['msg']
                    ?? $authData['error_description']
                    ?? $authData['message']
                    ?? (is_string($authData['error'] ?? null) ? $authData['error'] : null)
                    ?? $auth['transport_error']
                    ?? 'Supabase Auth rechazó el alta sin devolver un mensaje.');

            $codigoAuth = $authData['code'] ?? null;
            if ($codigoAuth) {
                $mensajeCuenta .= ' (código: ' . $codigoAuth . ').';
            } elseif (!empty($auth['status'])) {
                $mensajeCuenta .= ' (HTTP ' . (int)$auth['status'] . ').';
            }

            if (!$id) {
                $rollback = supabaseQuery(
                    'alumnos?id=eq.' . urlencode($alumnoCreado['id']),
                    'DELETE'
                );
                if (isset($rollback['code']) || isset($rollback['error'])) {
                    $mensajeCuenta .= ' El registro del alumno quedó guardado; buscá el DNI antes de reintentar.';
                }
            }

            throw new Exception($mensajeCuenta);
        }
    }

    echo json_encode(['success' => true, 'data' => $res]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}