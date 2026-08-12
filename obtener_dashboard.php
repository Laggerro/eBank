<?php
// obtener_dashboard.php (en la raíz /ebank)
header('Content-Type: application/json');

// Asegurar que cargue config.php de la raíz
require_once __DIR__ . '/config.php';

// Si la sesión no arrancó, iniciarla
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

try {
    // 1. Alumnos y Saldo Circulante
    $alumnos = supabaseQuery('alumnos?select=saldo');
    $cantAlumnos = is_array($alumnos) ? count($alumnos) : 0;
    $saldoCirculante = 0;
    if (is_array($alumnos)) {
        foreach ($alumnos as $a) {
            $saldoCirculante += floatval($a['saldo'] ?? 0);
        }
    }

    // 2. Stands Activos (Usuarios ROL POSNET)
    $posnets = supabaseQuery('usuarios_banco?rol=eq.POSNET&activo=eq.true&select=id');
    $cantPosnets = is_array($posnets) ? count($posnets) : 0;

    // 3. Totales de Transacciones
    $transacciones = supabaseQuery('transacciones?select=monto,tipo,estado');
    $totalRecargas = 0;
    $totalVentasStands = 0;
    $totalExtracciones = 0;

    if (is_array($transacciones)) {
        foreach ($transacciones as $t) {
            $estado = strtoupper(trim($t['estado'] ?? ''));
            $tipo = strtoupper(trim($t['tipo'] ?? ''));

            if ($estado === 'OK' || $estado === 'COMPLETADO') {
                if ($tipo === 'RECARGA') {
                    $totalRecargas += floatval($t['monto']);
                } elseif ($tipo === 'COBRO' || $tipo === 'VENTA') {
                    $totalVentasStands += floatval($t['monto']);
                } elseif ($tipo === 'EXTRACCION' || $tipo === 'RETIRO') {
                    $totalExtracciones += floatval($t['monto']);
                }
            }
        }
    }

    $efectivoCaja = $totalRecargas - $totalExtracciones;

    // 4. Últimas Transacciones
    $ultimas = supabaseQuery('transacciones?select=id,alumno_dni,monto,tipo,estado,fecha_hora,posnet_id&order=fecha_hora.desc&limit=10');

    $tablaFormateada = [];
    if (is_array($ultimas)) {
        foreach ($ultimas as $u) {
            $tablaFormateada[] = [
                'fecha_hora' => $u['fecha_hora'],
                'alumno_dni' => $u['alumno_dni'],
                'tipo' => $u['tipo'],
                'stand' => 'Caja / Stand #' . ($u['posnet_id'] ?? 'Central'),
                'monto' => floatval($u['monto']),
                'estado' => $u['estado']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'kpis' => [
            'efectivo_caja' => $efectivoCaja,
            'saldo_circulante' => $saldoCirculante,
            'ventas_stands' => $totalVentasStands,
            'extracciones' => $totalExtracciones,
            'cant_alumnos' => $cantAlumnos,
            'cant_posnets' => $cantPosnets
        ],
        'ultimas_transacciones' => $tablaFormateada
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}