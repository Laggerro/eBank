<?php
// /ebank/obtener_dashboard.php

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
    // 1. Alumnos REGISTRADOS y Saldo Circulante
    $alumnos = supabaseQuery('alumnos?select=saldo&registrado=eq.true');
    
    // Si Supabase devuelve error, nos aseguramos de no procesar strings de error
    if (isset($alumnos['code']) || isset($alumnos['error']) || !is_array($alumnos)) {
        $alumnos = [];
    }

    $cantAlumnos = count($alumnos);
    $saldoCirculante = 0;

    foreach ($alumnos as $a) {
        if (is_array($a) && isset($a['saldo'])) {
            $saldoCirculante += floatval($a['saldo']);
        }
    }

    // 2. Stands Activos (Usuarios ROL POSNET)
    $posnets = supabaseQuery('usuarios_banco?rol=eq.POSNET&activo=eq.true&select=id');
    if (isset($posnets['code']) || isset($posnets['error']) || !is_array($posnets)) {
        $posnets = [];
    }
    $cantPosnets = count($posnets);

    // 3. Totales de Transacciones
    $transacciones = supabaseQuery('transacciones?select=monto,tipo,estado');
    if (isset($transacciones['code']) || isset($transacciones['error']) || !is_array($transacciones)) {
        $transacciones = [];
    }

    $totalRecargas = 0;
    $totalVentasStands = 0;
    $totalExtracciones = 0;

    foreach ($transacciones as $t) {
        if (!is_array($t)) continue; // Evita el error "offset of type string on string"

        $estado = strtoupper(trim($t['estado'] ?? ''));
        $tipo   = strtoupper(trim($t['tipo'] ?? ''));
        $monto  = floatval($t['monto'] ?? 0);

        if ($estado === 'OK' || $estado === 'COMPLETADO') {
            if ($tipo === 'RECARGA') {
                $totalRecargas += $monto;
            } elseif ($tipo === 'COBRO' || $tipo === 'VENTA') {
                $totalVentasStands += $monto;
            } elseif ($tipo === 'EXTRACCION' || $tipo === 'RETIRO') {
                $totalExtracciones += $monto;
            }
        }
    }

    $efectivoCaja = $totalRecargas - $totalExtracciones;

    // 4. Últimas Transacciones
    $ultimas = supabaseQuery('transacciones?select=id,alumno_dni,monto,tipo,estado,fecha_hora,posnet_id&order=fecha_hora.desc&limit=10');
    if (isset($ultimas['code']) || isset($ultimas['error']) || !is_array($ultimas)) {
        $ultimas = [];
    }

    $tablaFormateada = [];
    foreach ($ultimas as $u) {
        if (!is_array($u)) continue;
        
        $tablaFormateada[] = [
            'fecha_hora' => $u['fecha_hora'] ?? null,
            'alumno_dni' => $u['alumno_dni'] ?? '-',
            'tipo'       => $u['tipo'] ?? '-',
            'stand'      => 'Caja / Stand #' . ($u['posnet_id'] ?? 'Central'),
            'monto'      => floatval($u['monto'] ?? 0),
            'estado'     => $u['estado'] ?? '-'
        ];
    }

    echo json_encode([
        'success' => true,
        'kpis' => [
            'efectivo_caja'    => $efectivoCaja,
            'saldo_circulante' => $saldoCirculante,
            'ventas_stands'    => $totalVentasStands,
            'extracciones'     => $totalExtracciones,
            'cant_alumnos'     => $cantAlumnos,
            'cant_posnets'     => $cantPosnets
        ],
        'ultimas_transacciones' => $tablaFormateada
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción en backend: ' . $e->getMessage()
    ]);
}