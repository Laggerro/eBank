<?php
// pages/cajero.php
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.html");
    exit;
}

$usuarioLogueado = $_SESSION['usuario'];
$puedeRetirar = !empty($usuarioLogueado['puede_retirar']) || strtoupper($usuarioLogueado['rol'] ?? '') === 'ADMIN';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Escolar - Módulo de Cajero</title>
    <link rel="stylesheet" href="../css/cajero.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        .panel-cajero-grid {
            display: grid;
            grid-template-columns: 280px 1fr 320px;
            gap: 20px;
        }
        @media (max-width: 1100px) {
            .panel-cajero-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard-container">
        <!-- Buscador Superior -->
        <section class="card card-busqueda mb-4">
            <h2>Buscar Cuenta de Alumno</h2>
            <div class="search-box">
                <input type="text" id="txtBuscarDni" placeholder="Escaneá la tarjeta QR o ingresá el DNI..." autofocus>
                <button id="btnBuscar" class="btn btn-primary">Buscar</button>
                <button id="btnEscanearQR" class="btn btn-dark">Cámara QR</button>
            </div>
        </section>

        <!-- PANEL EN 3 COLUMNAS: PERFIL | OPERACIONES (CENTRO) | MOVIMIENTOS (DERECHA) -->
        <div id="panelAlumno" class="panel-cajero-grid" style="display: none;">
            
            <!-- COLUMNA 1: FICHA Y SALDO -->
            <div class="columna-perfil">
                <section class="card card-perfil text-center">
                    <div class="avatar-container mb-2">
                        <img id="imgAlumno" src="" alt="Foto perfil" style="width: 100px; height: 100px; border-radius: 50%;">
                    </div>
                    <h3 id="lblNombre" class="mb-1">-</h3>
                    <p id="lblCurso" class="text-muted mb-3">-</p>
                    <div class="meta-info text-start small mb-3">
                        <p class="mb-1"><strong>DNI:</strong> <span id="lblDni"></span></p>
                        <p class="mb-0"><strong>QR:</strong> <span id="lblQR"></span></p>
                    </div>
                    <div class="saldo-box p-3 bg-light rounded">
                        <span class="saldo-label text-uppercase small text-muted">Saldo Actual</span>
                        <h2 id="lblSaldo" class="text-success m-0">$0.00</h2>
                    </div>
                </section>
            </div>

            <!-- COLUMNA 2: ACCIONES PRINCIPALES (CENTRO) -->
            <div class="columna-operaciones d-flex flex-column gap-3">
                <!-- Acreditar -->
                <section class="card card-recarga">
                    <h3>💵 Acreditar Dinero (Efectivo)</h3>
                    <form id="formRecarga" class="mt-2">
                        <div class="form-group mb-2">
                            <label for="montoRecarga">Monto a cargar ($)</label>
                            <input type="number" id="montoRecarga" class="form-control" min="1" step="0.5" placeholder="Ej: 500.00" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Acreditar Saldo</button>
                    </form>
                </section>

                <!-- Extraer -->
                <?php if ($puedeRetirar): ?>
                <section class="card card-extraccion" id="secExtraccion">
                    <h3>💸 Extracción / Retiro de Efectivo</h3>
                    <form id="formExtraccion" class="mt-2">
                        <div class="form-group mb-2">
                            <label for="montoExtraccion">Monto a retirar ($)</label>
                            <input type="number" id="montoExtraccion" class="form-control" min="1" step="0.5" placeholder="Ej: 200.00" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="pinExtraccion">PIN del Alumno</label>
                            <input type="password" id="pinExtraccion" class="form-control" maxlength="4" placeholder="****" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Confirmar Extracción</button>
                    </form>
                </section>
                <?php endif; ?>

                <!-- Restaurar PIN -->
                <section class="card card-pin">
                    <h3>🔑 Blanqueo / Restauración de PIN</h3>
                    <form id="formResetPin" class="mt-2">
                        <div class="form-group mb-3">
                            <label for="nuevoPin">Nuevo PIN (4 dígitos)</label>
                            <input type="password" id="nuevoPin" class="form-control" maxlength="4" placeholder="****" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Restaurar PIN</button>
                    </form>
                </section>
            </div>

            <!-- COLUMNA 3: ULTIMOS MOVIMIENTOS (DERECHA) -->
            <div class="columna-movimientos">
                <section class="card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="m-0">Últimos Movimientos</h4>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cargarUltimosMovimientos()">🔄</button>
                    </div>
                    <div class="tabla-movimientos-container" style="max-height: 480px; overflow-y: auto;">
                        <table class="tabla-movimientos w-100">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody id="tblMovimientosBody">
                                <tr><td colspan="3" class="text-center text-muted">A la espera...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

        </div>
    </main>
<!-- MODAL LECTOR QR -->
<div id="modalQR" class="modal-qr-overlay" style="display: none;">
  <div class="modal-qr-content">
    <h3>Escanear Código QR</h3>
    <div id="reader"></div>
    <button type="button" id="btnCerrarQR" class="btn-cancel mt-3">Cancelar / Cerrar</button>
  </div>
</div>
    <script src="../js/cajero.js"></script>
</body>
</html>