<?php
// procesar_cajero.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Control de Sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada.']);
    exit;
}

$usuarioLogueado = $_SESSION['usuario'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = $input['accion'] ?? ($_GET['accion'] ?? '');

try {
    switch ($accion) {

        // 1. BUSCAR ALUMNO
        case 'buscar_alumno':
            $query = trim($_GET['criterio'] ?? '');
            if (empty($query)) throw new Exception("Ingrese un DNI o código QR.");

            $endpoint = "alumnos?or=(dni.eq." . urlencode($query) . ",codigo_qr.eq." . urlencode($query) . ")";
            $res = supabaseQuery($endpoint, 'GET');

            if (empty($res) || isset($res['error']) || isset($res['code'])) {
                echo json_encode(['success' => false, 'message' => 'Alumno no encontrado.']);
                exit;
            }

            $alumno = $res[0];
            unset($alumno['pin']); // No exponemos la clave en el cliente

            echo json_encode(['success' => true, 'alumno' => $alumno]);
            break;

        // 2. ULTIMOS MOVIMIENTOS
        case 'movimientos':
            $alumnoId = trim($_GET['alumno_id'] ?? '');
            if (empty($alumnoId)) throw new Exception("ID de alumno no provisto.");

            $endpoint = "transacciones?alumno_id=eq." . urlencode($alumnoId) . "&order=fecha_hora.desc&limit=10";
            $movs = supabaseQuery($endpoint, 'GET');

            if (is_array($movs) && isset($movs['code'])) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error en base de datos: ' . ($movs['message'] ?? 'Error de consulta'),
                    'movimientos' => []
                ]);
                exit;
            }

            echo json_encode(['success' => true, 'movimientos' => is_array($movs) ? $movs : []]);
            break;

        // 3. ACREDITAR DINERO
        case 'acreditar':
            $alumnoId = trim($input['alumno_id'] ?? '');
            $monto = floatval($input['monto'] ?? 0);
            if ($monto <= 0) throw new Exception("Monto inválido.");

            $resAl = supabaseQuery("alumnos?id=eq." . urlencode($alumnoId), 'GET');
            if (empty($resAl) || isset($resAl['code']) || empty($resAl[0])) throw new Exception("Alumno no encontrado.");
            $alumno = $resAl[0];

            $nuevoSaldo = floatval($alumno['saldo'] ?? 0) + $monto;

            // Actualizar Saldo
            supabaseQuery("alumnos?id=eq." . urlencode($alumnoId), 'PATCH', ['saldo' => $nuevoSaldo]);

            // Insertar Transacción
            $dataTx = [
                'alumno_id'  => $alumnoId,
                'monto'      => $monto,
                'tipo'       => 'RECARGA',
                'estado'     => 'OK',
                'fecha_hora' => date('c')
            ];

            if (!empty($usuarioLogueado['id'])) {
                $dataTx['usuario_banco_id'] = (int)$usuarioLogueado['id'];
            }

            supabaseQuery("transacciones", 'POST', $dataTx);

            registrarAuditoria('RECARGA', $usuarioLogueado['usuario'] ?? 'CAJERO', $alumno['dni'], "Carga de saldo presencial: \${$monto}");

            echo json_encode(['success' => true, 'nuevo_saldo' => $nuevoSaldo, 'message' => "¡Carga exitosa! Nuevo saldo: \${$nuevoSaldo}"]);
            break;

        // 4. EXTRAER DINERO
        case 'extraer':
            $puedeRetirar = !empty($usuarioLogueado['puede_retirar']) || strtoupper($usuarioLogueado['rol'] ?? '') === 'ADMIN';
            if (!$puedeRetirar) throw new Exception("No tiene permisos para realizar extracciones.");

            $alumnoId = trim($input['alumno_id'] ?? '');
            $monto = floatval($input['monto'] ?? 0);
            $pinInput = trim($input['pin'] ?? '');

            if ($monto <= 0) throw new Exception("Monto inválido.");

            $resAl = supabaseQuery("alumnos?id=eq." . urlencode($alumnoId), 'GET');
            if (empty($resAl) || isset($resAl['code']) || empty($resAl[0])) throw new Exception("Alumno no encontrado.");
            $alumno = $resAl[0];

            $saldoActual = floatval($alumno['saldo'] ?? 0);
            if ($monto > $saldoActual) throw new Exception("Fondos insuficientes. Saldo actual: \${$saldoActual}");

            // Validar PIN (soporta tanto hash bcrypt como texto plano)
            $pinDB = (string)($alumno['pin'] ?? '');
            $pinOk = password_verify($pinInput, $pinDB) || ($pinInput === $pinDB);

            if (!$pinOk) {
                registrarAuditoria('INTENTO_FALLIDO_PIN', $usuarioLogueado['usuario'] ?? 'CAJERO', $alumno['dni'], "Fallo de PIN en intento de extracción de \${$monto}");
                throw new Exception("PIN incorrecto.");
            }

            $nuevoSaldo = $saldoActual - $monto;

            // Actualizar Saldo
            supabaseQuery("alumnos?id=eq." . urlencode($alumnoId), 'PATCH', ['saldo' => $nuevoSaldo]);

            // Insertar Transacción
            $dataTx = [
                'alumno_id'  => $alumnoId,
                'monto'      => $monto,
                'tipo'       => 'EXTRACCION',
                'estado'     => 'OK',
                'fecha_hora' => date('c')
            ];

            if (!empty($usuarioLogueado['id'])) {
                $dataTx['usuario_banco_id'] = (int)$usuarioLogueado['id'];
            }

            supabaseQuery("transacciones", 'POST', $dataTx);

            registrarAuditoria('EXTRACCION', $usuarioLogueado['usuario'] ?? 'CAJERO', $alumno['dni'], "Extracción en efectivo: \${$monto}");

            echo json_encode(['success' => true, 'nuevo_saldo' => $nuevoSaldo, 'message' => "¡Extracción autorizada! Entregar \${$monto}."]);
            break;

        // 5. RESTAURAR PIN
        case 'reset_pin':
            $alumnoId = trim($input['alumno_id'] ?? '');
            $nuevoPin = trim($input['nuevo_pin'] ?? '');

            if (strlen($nuevoPin) !== 4 || !is_numeric($nuevoPin)) {
                throw new Exception("El PIN debe contener exactamente 4 números.");
            }

            $pinHash = password_hash($nuevoPin, PASSWORD_BCRYPT);

            supabaseQuery("alumnos?id=eq." . urlencode($alumnoId), 'PATCH', ['pin' => $pinHash]);

            registrarAuditoria('BLANQUEO_PIN', $usuarioLogueado['usuario'] ?? 'CAJERO', $alumnoId, "Blanqueo y cambio de PIN de alumno");

            echo json_encode(['success' => true, 'message' => '¡PIN actualizado exitosamente!']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
