<?php
// /ebank/guardar_cliente.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado: Sesión no iniciada']);
    exit;
}

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
    $dni = trim($data['dni'] ?? '');
    $nombreApellido = trim($data['nombre_apellido'] ?? '');

    if (empty($dni) || empty($nombreApellido)) {
        throw new Exception("El DNI y el Nombre Completo son obligatorios.");
    }

    $urlFoto = null;
    if (!empty($data['foto_base64'])) {
        $urlFoto = subirAImgBB($data['foto_base64']);
    }

    // MAPEO EXACTO CON TU TABLA DE SUPABASE:
    // dni, nombre_apellido, curso, saldo, pin, foto_url, codigo_qr
    $payload = [
        'dni'             => $dni,
        'nombre_apellido' => $nombreApellido,
        'curso'           => $data['curso'] ?? '',
        'codigo_qr'       => !empty($data['codigo_qr']) ? $data['codigo_qr'] : null
    ];

    if ($urlFoto) {
        $payload['foto_url'] = $urlFoto;
    }

    if (!empty($data['pin'])) {
        $payload['pin'] = $data['pin'];
    }

    $esEdicion = !empty($data['es_edicion']);

    if ($esEdicion) {
        $endpoint = 'alumnos?dni=eq.' . urlencode($dni);
        $metodo = 'PATCH';
    } else {
        $endpoint = 'alumnos';
        $metodo = 'POST';
        $payload['saldo'] = 0.0;
    }

    $res = supabaseQuery($endpoint, $metodo, $payload);

    // Detectar si Supabase devuelve un error de PostgREST
    if (isset($res['code']) || isset($res['error']) || isset($res['message'])) {
        $msgError = $res['message'] ?? 'Error procesando solicitud en Supabase';
        echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $msgError, 'raw' => $res]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $res]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}