<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

exigirRoles(['ALUMNO']);
$alumnoId = trim((string)($_SESSION['usuario']['id'] ?? ''));
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = $_GET['accion'] ?? ($input['accion'] ?? '');

try {
    if ($alumnoId === '') throw new Exception('Sesión de alumno inválida.');

    if ($accion === 'perfil') {
        $res = supabaseQuery('alumnos?id=eq.' . urlencode($alumnoId) . '&select=id,dni,nombre_apellido,curso,saldo,codigo_qr,foto_url', 'GET');
        if (empty($res[0])) throw new Exception('Alumno no encontrado.');
        echo json_encode(['success' => true, 'alumno' => $res[0]]);
        exit;
    }

    if ($accion === 'movimientos') {
        $res = supabaseQuery('transacciones?alumno_id=eq.' . urlencode($alumnoId) . '&select=monto,tipo,estado,fecha_hora&order=fecha_hora.desc&limit=50', 'GET');
        if (isset($res['error']) || isset($res['code'])) throw new Exception('No se pudieron cargar los movimientos.');
        echo json_encode(['success' => true, 'movimientos' => is_array($res) ? $res : []]);
        exit;
    }

    if ($accion === 'transferir') {
        $dniDestino = trim((string)($input['dni_destino'] ?? ''));
        $monto = filter_var($input['monto'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($dniDestino === '' || $monto === false || $monto <= 0) throw new Exception('Destinatario o monto inválido.');

        $res = supabaseQuery('rpc/transferir_saldo_alumno', 'POST', [
            'p_origen_id' => $alumnoId,
            'p_destino_dni' => $dniDestino,
            'p_monto' => $monto
        ]);
        if (isset($res['error']) || isset($res['code'])) throw new Exception($res['message'] ?? 'No se pudo realizar la transferencia.');
        $resultado = is_array($res) && isset($res[0]) ? $res[0] : $res;
        if (isset($resultado['exito']) && !$resultado['exito']) throw new Exception($resultado['mensaje'] ?? 'Transferencia rechazada.');
        echo json_encode(['success' => true, 'message' => $resultado['mensaje'] ?? 'Transferencia realizada.']);
        exit;
    }

    throw new Exception('Acción no válida.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}