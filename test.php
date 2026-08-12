<?php
// test.php
require_once 'config.php';

echo "<h2>Prueba de Conexión a Supabase</h2>";

$userBuscado = 'admin';
$endpoint = "usuarios_banco?usuario=eq." . urlencode($userBuscado) . "&select=*";

echo "<p><b>URL consultada:</b> " . SUPABASE_URL . '/rest/v1/' . $endpoint . "</p>";

$resultado = supabaseQuery($endpoint);

echo "<h3>Respuesta cruda de Supabase:</h3>";
echo "<pre>";
print_r($resultado);
echo "</pre>";
?>