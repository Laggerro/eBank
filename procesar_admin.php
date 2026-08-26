<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);



// procesar_admin.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Validar que el usuario esté autenticado y sea ADMIN
if (!isset($_SESSION['usuario']) || strtoupper($_SESSION['usuario']['rol'] ?? '') !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

try {
    switch ($action) {
//---------------------------------- borrar solo prueba 
//case 'importar_csv':
    // PRUEBA DE DIAGNÓSTICO
  //  var_dump($_FILES);
   // exit;




        // ==========================================
        // 1. GESTIÓN DE USUARIOS DEL BANCO
        // ==========================================
        case 'listar_usuarios':
            $res = supabaseQuery("usuarios_banco?rol=neq.POSNET&select=*&order=id.asc", "GET");
            echo json_encode(['success' => true, 'data' => is_array($res) ? $res : []]);
            break;

        case 'guardar_usuario':
            $id = $input['id'] ?? null;
            $payload = [
                'usuario'       => trim($input['usuario'] ?? ''),
                'nombre'        => trim($input['nombre'] ?? ''),
                'rol'           => strtoupper(trim($input['rol'] ?? 'CAJERO')),
                'puede_retirar' => !empty($input['puede_retirar']),
                'activo'        => true
            ];

            // Mapeo corregido a password_hash
            if (!empty($input['password'])) {
                $payload['password_hash'] = trim($input['password']);
            }

            if ($id) {
                $res = supabaseQuery("usuarios_banco?id=eq." . urlencode($id), "PATCH", $payload);
            } else {
                $payload['monto_acumulado'] = 0;
                $payload['cant_transacciones'] = 0;
                $res = supabaseQuery("usuarios_banco", "POST", $payload);
            }

            if (isset($res['message']) || isset($res['error'])) {
                $errorMsg = $res['message'] ?? $res['error'] ?? 'Error desconocido';
                echo json_encode(['success' => false, 'message' => 'Error de Supabase: ' . $errorMsg]);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Usuario guardado correctamente.']);
            break;

        case 'cambiar_estado_usuario':
            $id = $input['id'] ?? '';
            $nuevoEstado = !empty($input['activo']);
            supabaseQuery("usuarios_banco?id=eq." . urlencode($id), "PATCH", ['activo' => $nuevoEstado]);
            echo json_encode(['success' => true, 'message' => 'Estado del usuario actualizado.']);
            break;

        case 'eliminar_usuario':
            $id = $input['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID no provisto.']);
                exit;
            }
            $res = supabaseQuery("usuarios_banco?id=eq." . urlencode($id), "DELETE");
            echo json_encode(['success' => true, 'message' => 'Registro eliminado con éxito.']);
            break;

        // ==========================================
        // 2. GESTIÓN DE TERMINALES POSNET
        // ==========================================
        case 'listar_posnets':
            $res = supabaseQuery("usuarios_banco?rol=eq.POSNET&select=*&order=id.asc", "GET");
            echo json_encode(['success' => true, 'data' => is_array($res) ? $res : []]);
            break;

        case 'guardar_posnet':
            $id = !empty($input['id']) ? trim($input['id']) : null;
            $payload = [
                'usuario' => trim($input['usuario_posnet'] ?? ''),
                'nombre'  => trim($input['nombre_stand'] ?? ''),
                'rol'     => 'POSNET',
                'activo'  => true
            ];

            if (!empty($input['password_posnet'])) {
                $payload['password_hash'] = trim($input['password_posnet']);
            }

            if ($id) {
                $res = supabaseQuery("usuarios_banco?id=eq." . urlencode($id), "PATCH", $payload);
            } else {
                $payload['monto_acumulado'] = 0;
                $payload['cant_transacciones'] = 0;
                $payload['puede_retirar'] = false;
                $res = supabaseQuery("usuarios_banco", "POST", $payload);
            }

            if (isset($res['message']) || isset($res['error'])) {
                $errorMsg = $res['message'] ?? $res['error'] ?? 'Error desconocido';
                echo json_encode(['success' => false, 'message' => 'Error de Supabase: ' . $errorMsg]);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Terminal POSNET guardada correctamente.']);
            break;

        case 'cambiar_estado_posnet':
            $id = $input['id'] ?? '';
            $nuevoEstado = !empty($input['activo']);
            supabaseQuery("usuarios_banco?id=eq." . urlencode($id), "PATCH", ['activo' => $nuevoEstado]);
            echo json_encode(['success' => true, 'message' => 'Estado del POSNET actualizado.']);
            break;

        // ==========================================
        // 3. GESTIÓN DE CURSOS
        // ==========================================
        case 'listar_cursos':
            $res = supabaseQuery("cursos?select=*&order=nombre.asc", "GET");
            echo json_encode(['success' => true, 'data' => is_array($res) ? $res : []]);
            break;

        case 'guardar_curso':
            $nombre = trim($input['nombre'] ?? '');
            if (empty($nombre)) throw new Exception("El nombre del curso es obligatorio.");
            supabaseQuery("cursos", "POST", ['nombre' => $nombre]);
            echo json_encode(['success' => true, 'message' => 'Curso agregado correctamente.']);
            break;

        case 'eliminar_curso':
            $id = $input['id'] ?? '';
            supabaseQuery("cursos?id=eq." . urlencode($id), "DELETE");
            echo json_encode(['success' => true, 'message' => 'Curso eliminado.']);
            break;


// ==========================================
        // 4. IMPORTACIÓN MASIVA DESDE CSV
        // ==========================================
        case 'importar_csv':
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');

            if (!isset($_FILES['fileCsv']) || $_FILES['fileCsv']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Error en la subida del archivo CSV.']);
                exit;
            }

            $fileHandle = fopen($_FILES['fileCsv']['tmp_name'], 'r');
            if (!$fileHandle) {
                echo json_encode(['success' => false, 'message' => 'No se pudo abrir el archivo CSV temporal.']);
                exit;
            }

            // Eliminar BOM de UTF-8 si estuviera presente
            $bom = fread($fileHandle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($fileHandle);
            }

            // Omitir la fila de encabezados
            fgetcsv($fileHandle, 1000, ",");

            $insertados = 0;
            $errores = [];
            $filaNum = 1;

            $urlSupabase = SUPABASE_URL . "/rest/v1/alumnos";
            $apiKey = SUPABASE_KEY;

            while (($row = fgetcsv($fileHandle, 1000, ",")) !== FALSE) {
                $filaNum++;

                // Ignorar filas totalmente vacías
                if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
                    continue;
                }

                $dni = trim($row[0] ?? '');
                if (empty($dni)) {
                    continue; // Saltar filas sin DNI
                }

                // Sanitizar saldo (reemplazar coma por punto y quitar símbolos de moneda)
                $saldoRaw = str_replace(['$', ' ', ','], ['', '', '.'], $row[3] ?? '0');
                $saldoFinal = is_numeric($saldoRaw) ? floatval($saldoRaw) : 0.00;

                $fotoUrl  = isset($row[4]) && trim($row[4]) !== '' ? trim($row[4]) : null;
                $codigoQr = isset($row[5]) && trim($row[5]) !== '' ? trim($row[5]) : null;
                $pinDefecto = strlen($dni) >= 4 ? substr($dni, -4) : '1234';

                // Limpiar caracteres y asegurar codificación UTF-8
                $nombreLimpio = mb_convert_encoding(trim($row[1] ?? ''), 'UTF-8', 'UTF-8, ISO-8859-1');
                $cursoLimpio  = mb_convert_encoding(trim($row[2] ?? ''), 'UTF-8', 'UTF-8, ISO-8859-1');

                $payload = [
                    'dni'             => (string)$dni,
                    'nombre_apellido' => $nombreLimpio,
                    'curso'           => $cursoLimpio,
                    'saldo'           => $saldoFinal,
                    'pin'             => (string)$pinDefecto,
                    'foto_url'        => $fotoUrl,
                    'codigo_qr'       => $codigoQr,
                    'registrado'      => false
                ];

                $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

                // Validar que el JSON se haya generado correctamente
                if ($jsonPayload === false) {
                    $errores[] = "Fila $filaNum (DNI $dni): Error de codificación JSON - " . json_last_error_msg();
                    continue;
                }

                // Petición cURL a Supabase
                $ch = curl_init($urlSupabase);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "apikey: {$apiKey}",
                    "Authorization: Bearer {$apiKey}",
                    "Content-Type: application/json; charset=utf-8",
                    "Content-Length: " . strlen($jsonPayload),
                    "Prefer: return=representation"
                ]);

                $responseBody = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    $errores[] = "Fila $filaNum (DNI $dni): Error cURL: " . $curlErr;
                } else if ($httpCode >= 400) {
                    $errores[] = "Fila $filaNum (DNI $dni - HTTP $httpCode): " . $responseBody;
                } else {
                    $insertados++;
                }
            }

            fclose($fileHandle);

            // Respuesta final estructurada
            if (count($errores) > 0 && $insertados === 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "No se pudo insertar ningún alumno. Revisá los errores adjuntos.",
                    'errores' => $errores
                ]);
            } else if (count($errores) > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => "Se insertaron $insertados alumnos correctamente, pero se detectaron errores en algunas filas.",
                    'errores' => $errores
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => "Se importaron los $insertados alumnos correctamente en la base de datos."
                ]);
            }
            exit;
            break;
        // ==========================================
        // 5. MANTENIMIENTO Y RESET DE BD
        // ==========================================
        case 'reset_bd':
            $qrIngresado = trim($input['qr_code'] ?? '');

            if (defined('QR_MAESTRO_ADMIN') && $qrIngresado !== QR_MAESTRO_ADMIN) {
                throw new Exception("QR Maestro de confirmación inválido.");
            }

            $opciones = $input['opciones'] ?? [];

            if (!empty($opciones['reset_acumulados'])) {
                supabaseQuery("usuarios_banco?id=gt.0", "PATCH", ['monto_acumulado' => 0, 'cant_transacciones' => 0]);
            }
            if (!empty($opciones['vaciar_transacciones'])) {
                supabaseQuery("transacciones?id=gt.0", "DELETE");
            }
            if (!empty($opciones['vaciar_logs'])) {
                supabaseQuery("logs_sistema?id=gt.0", "DELETE");
                supabaseQuery("logs_auditoria?id=gt.0", "DELETE");
            }
            if (!empty($opciones['borrar_alumnos_no_registrados'])) {
                supabaseQuery("alumnos?registrado=eq.false", "DELETE");
            }
            if (!empty($opciones['vaciar_alumnos_todos'])) {
                supabaseQuery("alumnos?id=neq.00000000-0000-0000-0000-000000000000", "DELETE");
            }

            echo json_encode(['success' => true, 'message' => "Mantenimiento realizado con éxito."]);
            break;

        default:
            throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}