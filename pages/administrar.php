<?php
// pages/administrar.php
require_once '../config.php';

// Validar que el usuario logueado sea estrictamente ADMIN
if (!isset($_SESSION['usuario']) || strtoupper($_SESSION['usuario']['rol'] ?? '') !== 'ADMIN') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco Escolar - Administración General</title>
    <link rel="stylesheet" href="../css/cajero.css">
</head>
<body>
 
    <!-- NAVBAR DESDE SERVIDOR -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard-container">
        <div class="page-header">
            <h1>Panel de Administración General</h1>
            <p class="text-subtitle">Gestión de accesos, terminales POSNET, cursos, importación de datos y mantenimiento.</p>
        </div>

        <div class="admin-grid">
            <!-- SECCIÓN 1: GESTIÓN DE USUARIOS BANCO (Cajeros y Admins) -->
            <section class="card">
                <h3>👤 Usuarios del Sistema</h3>
                <p class="text-subtitle">Crear/Editar cajeros y administradores</p>
                <form id="formUsuarioBanco" style="margin-bottom: 20px;">
                    <input type="hidden" id="usrId">
                    <div class="form-group">
                        <label for="txtUsrUsuario">Usuario / Login</label>
                        <input type="text" id="txtUsrUsuario" placeholder="Ej: cajero_turno1" required>
                    </div>
                    <div class="form-group">
                        <label for="txtUsrNombre">Nombre Completo</label>
                        <input type="text" id="txtUsrNombre" placeholder="Ej: María López" required>
                    </div>
                    <div class="form-group">
                        <label for="txtUsrPass">Contraseña / Password</label>
                        <input type="password" id="txtUsrPass" placeholder="•••••••• (dejar en blanco para no cambiar)">
                    </div>
                    <div class="form-group">
                        <label for="selUsrRol">Rol de Sistema</label>
                        <select id="selUsrRol">
                            <option value="CAJERO">CAJERO</option>
                            <option value="ADMIN">ADMINISTRADOR</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; gap: 8px; align-items: center;">
                        <input type="checkbox" id="chkPuedeRetirar" style="width: auto;">
                        <label for="chkPuedeRetirar" style="margin: 0; font-weight: normal;">¿Permite Retiro de Efectivo?</label>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button type="submit" id="btnGuardarUsr" class="btn btn-primary" style="flex: 1;">Crear Usuario</button>
                        <button type="button" id="btnCancelarUsr" class="btn btn-dark" style="display: none;">Cancelar</button>
                    </div>
                </form>
                <div id="listaUsuariosBanco"></div>
            </section>

            <!-- SECCIÓN 2: CONTROL DE TERMINALES POSNET -->
            <section class="card">
                <h3>💳 POSNETS / Terminales de Cobro</h3>
                <p class="text-subtitle">Alta, edición y activación de puntos de cobro</p>
                <form id="formPosnet" style="margin-bottom: 20px;">
                    <input type="hidden" id="posnetId">
                    <div class="form-group">
                        <label for="txtPosnetNombre">Nombre del Stand / Puesto</label>
                        <input type="text" id="txtPosnetNombre" placeholder="Ej: Stand Buffet 5to Año" required>
                    </div>
                    <div class="form-group">
                        <label for="txtPosnetUsuario">Usuario de Acceso POSNET</label>
                        <input type="text" id="txtPosnetUsuario" placeholder="Ej: posnet_buffet" required>
                    </div>
                    <div class="form-group">
                        <label for="txtPosnetPass">Contraseña POSNET</label>
                        <input type="password" id="txtPosnetPass" placeholder="•••••••• (dejar en blanco para mantener)">
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button type="submit" id="btnGuardarPosnet" class="btn btn-primary" style="flex: 1;">Registrar Terminal</button>
                        <button type="button" id="btnCancelarPosnet" class="btn btn-dark" style="display: none;">Cancelar</button>
                    </div>
                </form>
                <div id="listaPosnets"></div>
            </section>

            <!-- SECCIÓN 3: CRUD DE CURSOS / DIVISIONES -->
            <section class="card">
                <h3>🏫 Cursos y Divisiones</h3>
                <p class="text-subtitle">Precarga de años/cursos para el alta de alumnos</p>
                <form id="formCurso" style="margin-bottom: 20px; display: flex; gap: 10px;">
                    <input type="text" id="txtNombreCurso" placeholder="Ej: 6to 1ra Informática" style="flex: 1;" required>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </form>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre del Curso</th>
                                <th style="text-align: right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tblCursos"></tbody>
                    </table>
                </div>
            </section>

            <!-- SECCIÓN 4: IMPORTAR ALUMNOS DESDE CSV -->
            <section class="card">
                <h3>📁 Importar Alumnos (CSV)</h3>
                <p class="text-subtitle">Carga masiva para agilizar el alta. Formato: DNI, Nombre, Curso, Saldo, Foto URL, QR</p>
                <form id="formImportarCSV" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fileCsv">Seleccionar archivo .csv</label>
                        <input type="file" id="fileCsv" accept=".csv" required style="margin-top: 5px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Cargar Datos CSV</button>
                </form>
            </section>

            <!-- SECCIÓN 5: MANTENIMIENTO Y RESET DE BASE DE DATOS -->
            <section class="card card-extraccion">
                <h3 style="color: #e53e3e;">⚠️ Mantenimiento y Reset BD</h3>
                <p class="text-subtitle">Seleccioná los datos a reiniciar. Requiere autorización con QR Maestro.</p>
                <div class="form-group" style="gap: 10px; margin: 15px 0;">
                    <label style="font-weight: normal;"><input type="checkbox" id="chkResetMontos"> Resetear montos acumulados a $0 (Cajeros y Posnets)</label>
                    <label style="font-weight: normal;"><input type="checkbox" id="chkVaciarTransacciones"> Vaciar historial de Transacciones</label>
                    <label style="font-weight: normal;"><input type="checkbox" id="chkVaciarLogs"> Vaciar Logs de Sistema y Auditoría</label>
                    <label style="font-weight: normal;"><input type="checkbox" id="chkBorrarNoReg"> Eliminar alumnos NO registrados</label>
                    <label style="font-weight: bold; color: #c53030;"><input type="checkbox" id="chkVaciarAlumnos" style="accent-color: #e53e3e;"> Vaciar TODOS los Alumnos</label>
                </div>
                <button type="button" id="btnProcesarReset" class="btn btn-danger">Ejecutar Mantenimiento</button>
            </section>
        </div>
    </main>

    <!-- MODAL DE CONFIRMACIÓN CON CÁMARA QR PARA RESET -->
    <div id="modalResetQR" class="modal-qr-overlay" style="display: none;">
        <div class="modal-qr-content">
            <h3 style="color: #fc8181;">Autorizar Mantenimiento</h3>
            <p style="font-size: 0.85rem; color: #a0aec0; margin-bottom: 15px;">Escaneá el QR Maestro para aplicar los cambios en la BD.</p>
            <div id="readerReset" style="width: 100%; max-width: 300px; margin: 0 auto;"></div>
            <button type="button" id="btnCerrarResetQR" class="btn btn-dark" style="margin-top: 15px; width: 100%;">Cancelar</button>
        </div>
    </div>

    <!-- MODAL DE CONFIRMACIÓN PARA ELIMINAR -->
    <div id="modalEliminar" class="modal-backdrop" style="display: none;">
        <div class="modal-box">
            <h3>⚠️ Confirmar Eliminación</h3>
            <p id="msgConfirmarEliminar">¿Estás seguro de que deseas eliminar este registro?</p>
            <div class="modal-actions">
                <button id="btnConfirmarEliminar" class="btn btn-danger">Sí, Eliminar</button>
                <button onclick="cerrarModalEliminar()" class="btn btn-dark">Cancelar</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="../js/administrar.js"></script>
</body>
</html>