<?php
// /ebank/obtener_auditoria.php

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode([
        'success' => false,
        'message' => "Error interno PHP ($errno): $errstr en $errfile línea $errline"
    ]);
    exit;
});

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

try {
    $filtroTipo  = trim($_GET['tipo'] ?? 'TODOS');
    $filtroTexto = mb_strtolower(trim($_GET['buscar'] ?? ''));

    $registrosUnificados = [];

    // ==========================================
    // 1. OBTENER TRANSACCIONES MONETARIAS
    // ==========================================
    $tiposMonetarios = ['TODOS', 'RECARGA', 'COBRO', 'EXTRACCION'];
    if (in_array($filtroTipo, $tiposMonetarios)) {
        
        $endpointTrans = 'transacciones?select=id,alumno_dni,monto,tipo,estado,fecha_hora,posnet_id,usuario_id&order=fecha_hora.desc&limit=100';
        if ($filtroTipo !== 'TODOS') {
            $endpointTrans .= '&tipo=eq.' . urlencode($filtroTipo);
        }

        $transacciones = supabaseQuery($endpointTrans, 'GET');

        if (is_array($transacciones) && !isset($transacciones['code']) && !isset($transacciones['error'])) {
            foreach ($transacciones as $t) {
                if (!is_array($t)) continue;

                $operador = "SISTEMA";
                $origen   = "Caja Principal";

                if (!empty($t['posnet_id'])) {
                    $operador = "POSNET ID: " . $t['posnet_id'];
                    $origen   = "Stand #" . $t['posnet_id'];
                } elseif (!empty($t['usuario_id'])) {
                    $operador = "Operador ID: " . $t['usuario_id'];
                    $origen   = "Caja / Terminal";
                }

                $tipoUpper  = strtoupper(trim($t['tipo'] ?? ''));
                $claseBadge = "bg-info";
                if ($tipoUpper === "RECARGA") $claseBadge = "bg-success";
                if ($tipoUpper === "EXTRACCION" || $tipoUpper === "RETIRO") $claseBadge = "bg-warning text-dark";

                $montoNum = floatval($t['monto'] ?? 0);

                $registrosUnificados[] = [
                    'fecha'      => $t['fecha_hora'] ?? '',
                    'tipo'       => $tipoUpper,
                    'afectado'   => 'DNI Alumno: ' . ($t['alumno_dni'] ?? '-'),
                    'operador'   => $operador,
                    'origen'     => $origen,
                    'detalle'    => '$' . number_format($montoNum, 2, '.', ''),
                    'estado'     => $t['estado'] ?? 'OK',
                    'claseBadge' => $claseBadge
                ];
            }
        }
    }

    // ==========================================
    // 2. OBTENER LOGS DE AUDITORÍA (Eventos de Sistema)
    // ==========================================
    $tiposLogs = ['TODOS', 'BLANQUEO_PIN', 'INTENTO_FALLIDO_PIN', 'ALTA_ALUMNO', 'BAJA_ALUMNO', 'MODIFICACION_ALUMNO'];
    if (in_array($filtroTipo, $tiposLogs)) {

        $endpointLogs = 'logs_auditoria?select=id,fecha_hora,tipo_evento,usuario_origen,usuario_destino,detalle&order=fecha_hora.desc&limit=100';
        if ($filtroTipo !== 'TODOS') {
            $endpointLogs .= '&tipo_evento=eq.' . urlencode($filtroTipo);
        }

        $logs = supabaseQuery($endpointLogs, 'GET');

        if (is_array($logs) && !isset($logs['code']) && !isset($logs['error'])) {
            foreach ($logs as $l) {
                if (!is_array($l)) continue;

                $eventoUpper = strtoupper(trim($l['tipo_evento'] ?? ''));
                
                $claseBadge = "bg-secondary";
                if ($eventoUpper === "BLANQUEO_PIN") $claseBadge = "bg-warning text-dark";
                if ($eventoUpper === "INTENTO_FALLIDO_PIN" || $eventoUpper === "BAJA_ALUMNO") $claseBadge = "bg-danger";
                if ($eventoUpper === "ALTA_ALUMNO") $claseBadge = "bg-success";
                if ($eventoUpper === "MODIFICACION_ALUMNO") $claseBadge = "bg-info text-dark";

                $registrosUnificados[] = [
                    'fecha'      => $l['fecha_hora'] ?? '',
                    'tipo'       => $eventoUpper,
                    'afectado'   => $l['usuario_destino'] ?? 'N/A',
                    'operador'   => $l['usuario_origen'] ?? 'ADMIN/CAJERO',
                    'origen'     => 'Panel Administrativo',
                    'detalle'    => $l['detalle'] ?? '-',
                    'estado'     => 'COMPLETADO',
                    'claseBadge' => $claseBadge
                ];
            }
        }
    }

    // ==========================================
    // 3. ORDENAR Y FILTRAR POR TEXTO
    // ==========================================
    
    // Ordenar de más reciente a más antiguo
    usort($registrosUnificados, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });

    // Búsqueda por coincidencia parcial de texto
    if ($filtroTexto !== '') {
        $registrosUnificados = array_values(array_filter($registrosUnificados, function($r) use ($filtroTexto) {
            $busqueda = mb_strtolower(
                $r['afectado'] . ' ' . 
                $r['operador'] . ' ' . 
                $r['origen'] . ' ' . 
                $r['tipo'] . ' ' . 
                $r['detalle']
            );
            return mb_strpos($busqueda, $filtroTexto) !== false;
        }));
    }

    echo json_encode([
        'success'   => true,
        'auditoria' => $registrosUnificados
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción en backend: ' . $e->getMessage()
    ]);
}