<?php
require_once __DIR__ . '/../config.php';
if (strtoupper($_SESSION['usuario']['rol'] ?? '') !== 'ALUMNO') {
    header('Location: ../index.html');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4" style="max-width: 900px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1">Mi cuenta</h1><p id="nombreAlumno" class="text-muted mb-0"></p></div>
        <a class="btn btn-outline-danger" href="../logout.php">Cerrar sesión</a>
    </div>
    <section class="card mb-3"><div class="card-body"><small class="text-muted">Saldo disponible</small><div id="saldoAlumno" class="display-5 fw-bold text-success">$ 0</div><div id="datosAlumno" class="text-muted"></div></div></section>
    <div class="row g-3">
        <div class="col-lg-5"><section class="card"><div class="card-body"><h2 class="h5">Transferir saldo</h2><form id="formTransferencia"><input id="dniDestino" class="form-control mb-2" placeholder="DNI destinatario" required><input id="montoTransferencia" type="number" min="0.01" step="0.01" class="form-control mb-2" placeholder="Monto" required><button class="btn btn-primary w-100">Transferir</button></form><div id="mensajeTransferencia" class="mt-2"></div></div></section></div>
        <div class="col-lg-7"><section class="card"><div class="card-body"><h2 class="h5">Mis movimientos</h2><div class="table-responsive"><table class="table"><thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Estado</th></tr></thead><tbody id="movimientosAlumno"></tbody></table></div></div></section></div>
    </div>
</main>
<script src="../js/alumno.js?v=1"></script>
</body></html>