<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

exigirRoles(['POSNET']);

$usuario = $_SESSION['usuario'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = $_GET['accion'] ?? ($input['accion'] ?? '');

try {
    $posnetId = trim((string)($usuario['id'] ?? ''));

    if ($posnetId === '') {
        throw new Exception('La sesión no tiene un POSNET válido.');
    }

    $resPosnet = supabaseQuery(
        'usuarios_banco?id=eq.' . urlencode($posnetId) . '&rol=eq.POSNET&activo=eq.true&select=id,usuario,nombre,monto_acumulado,cant_transacciones',
        'GET'
    );

    if (isset($resPosnet['error']) || isset($resPosnet['code'])) {
        throw new Exception('No se pudo validar el POSNET en este momento. Intente nuevamente.');
    }

    if (empty($resPosnet) || empty($resPosnet[0])) {
        session_destroy();
        throw new Exception('El POSNET está deshabilitado o ya no existe.');
    }

    $posnet = $resPosnet[0];

    switch ($accion) {
        case 'metricas':
            echo json_encode([
                'success' => true,
                'usuario' => $posnet['usuario'] ?? '',
                'nombre' => $posnet['nombre'] ?? '',
                'metricas' => [
                    'monto_acumulado' => (float)($posnet['monto_acumulado'] ?? 0),
                    'cant_transacciones' => (int)($posnet['cant_transacciones'] ?? 0)
                ]
            ]);
            break;

        case 'buscar_alumno':
            $codigoQr = trim((string)($input['codigo_qr'] ?? ''));
            if ($codigoQr === '' || strlen($codigoQr) > 255) {
                throw new Exception('Código QR inválido.');
            }

            $res = supabaseQuery(
                'alumnos?codigo_qr=eq.' . urlencode($codigoQr) . '&select=nombre_apellido,foto_url&limit=1',
                'GET'
            );

            if (empty($res) || isset($res['error']) || isset($res['code']) || empty($res[0])) {
                echo json_encode(['success' => false, 'message' => 'Código QR no registrado en el sistema.']);
                break;
            }

            echo json_encode(['success' => true, 'alumno' => $res[0]]);
            break;

        case 'listar_anulables':
            $res = supabaseQuery(
                'transacciones?posnet_id=eq.' . urlencode($posnetId) . '&tipo=eq.COBRO&estado=eq.OK&select=id,alumno_id,alumnos(dni),monto,fecha_hora&order=fecha_hora.desc&limit=20',
                'GET'
            );

            if (isset($res['error']) || isset($res['code'])) {
                throw new Exception('No se pudieron cargar las ventas anulables.');
            }

            $ventas = [];
            foreach (is_array($res) ? $res : [] as $venta) {
                $venta['dni'] = $venta['alumnos']['dni'] ?? '-';
                unset($venta['alumnos']);
                $ventas[] = $venta;
            }

            echo json_encode(['success' => true, 'transacciones' => $ventas]);
            break;

        case 'anular':
            $transaccionId = trim((string)($input['transaccion_id'] ?? ''));
            $pin = (string)($input['pin'] ?? '');
            $codigoMaestro = trim((string)($input['codigo_maestro'] ?? ''));

            if ($transaccionId === '' || !ctype_digit($transaccionId)) {
                throw new Exception('Venta inválida.');
            }
            if (!preg_match('/^\d{4}$/', $pin)) {
                throw new Exception('El PIN debe contener exactamente 4 números.');
            }
            if ($codigoMaestro === '') {
                throw new Exception('Ingrese el código maestro.');
            }

            $res = supabaseQuery('rpc/anular_pago_posnet', 'POST', [
                'p_transaccion_id' => (int)$transaccionId,
                'p_pin' => $pin,
                'p_codigo_maestro' => $codigoMaestro,
                'p_posnet_id' => (int)$posnetId,
                'p_usuario_posnet' => $usuario['usuario'] ?? 'POSNET'
            ]);

            if (isset($res['error']) || isset($res['code'])) {
                throw new Exception($res['message'] ?? 'No se pudo anular la venta.');
            }

            $resultado = is_array($res) && isset($res[0]) ? $res[0] : $res;
            if (isset($resultado['exito']) && !$resultado['exito']) {
                throw new Exception($resultado['mensaje'] ?? 'Anulación rechazada.');
            }

            echo json_encode([
                'success' => true,
                'message' => $resultado['mensaje'] ?? 'Venta anulada correctamente.'
            ]);
            break;

        case 'cobrar':
            $codigoQr = trim((string)($input['codigo_qr'] ?? ''));
            $pin = (string)($input['pin'] ?? '');
            $monto = filter_var($input['monto'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($codigoQr === '' || strlen($codigoQr) > 255) {
                throw new Exception('Código QR inválido.');
            }
            if (!preg_match('/^\d{4}$/', $pin)) {
                throw new Exception('El PIN debe contener exactamente 4 números.');
            }
            if ($monto === false || $monto <= 0 || $monto > 1000000) {
                throw new Exception('Monto inválido.');
            }

            // La RPC realiza la validación del PIN, bloqueo del saldo y registro
            // de la transacción en una sola operación de base de datos.
            $res = supabaseQuery('rpc/procesar_pago_posnet', 'POST', [
                'p_codigo_qr' => $codigoQr,
                'p_pin' => $pin,
                'p_monto' => $monto,
                'p_posnet_id' => $posnetId,
                'p_tipo' => 'COBRO'
            ]);

            if (isset($res['error']) || isset($res['code'])) {
                throw new Exception($res['message'] ?? 'No se pudo procesar el cobro.');
            }

            $resultado = is_array($res) && isset($res[0]) ? $res[0] : $res;
            if (isset($resultado['exito']) && !$resultado['exito']) {
                throw new Exception($resultado['mensaje'] ?? 'Cobro rechazado.');
            }

            echo json_encode([
                'success' => true,
                'message' => $resultado['mensaje'] ?? 'Cobro aprobado.'
            ]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}