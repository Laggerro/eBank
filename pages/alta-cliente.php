<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banco Escolar - Alta de Clientes</title>

  <!-- BOOTSTRAP 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/cajero.css">
  <link rel="stylesheet" href="../css/lector-qr.css">

  <!-- ESCÁNER QR Y SUPABASE SDK -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
</head>
<body class="bg-light">

  <!-- NAVBAR UNIFICADA -->
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <main class="container-fluid px-4 py-4" style="max-width: 1000px;">
    
    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h2 fw-bold text-dark mb-1" id="lblTituloForm">👤 Registrar Nuevo Cliente</h1>
        <p class="text-muted mb-0">Gestiona las altas, actualización de datos, PIN y credenciales QR.</p>
      </div>
   
    </div>

    <!-- TARJETA FORMULARIO -->
    <div class="card shadow-sm mb-4">
      <div class="card-body p-4">
        <div id="msgAlta" class="alert alert-danger d-none mb-3"></div>

        <form id="formAltaCliente">
          <input type="hidden" id="clienteId" value="">

          <div class="row g-3">
            <!-- FOTO DE PERFIL -->
            <div class="col-12 text-center mb-2">
              <div class="position-relative d-inline-block">
                <img id="imgPreview" src="../img/default-avatar.png" class="rounded-circle border border-2 shadow-sm" style="width: 110px; height: 110px; object-fit: cover;" alt="Foto de perfil">
              </div>
              <div class="mt-2">
                <button type="button" id="btnAbrirCamara" class="btn btn-sm btn-dark fw-bold">📸 Tomar Foto</button>
              </div>
            </div>

            <!-- DNI / IDENTIFICADOR -->
            <div class="col-md-6">
              <label for="txtDni" class="form-label fw-bold">DNI / Documento</label>
              <input type="text" id="txtDni" class="form-control" placeholder="Ej: 45123456" required>
            </div>

            <!-- NOMBRE Y APELLIDO -->
            <div class="col-md-6">
              <label for="txtNombre" class="form-label fw-bold">Nombre y Apellido</label>
              <input type="text" id="txtNombre" class="form-control" placeholder="Ej: Juan Pérez" required>
            </div>

            <!-- CURSO / SECTOR (DESDE TABLA CURSOS) -->
            <div class="col-md-6">
              <label for="txtCurso" class="form-label fw-bold">Curso / Sector</label>
              <select id="txtCurso" class="form-select" required>
                <option value="">Cargando sectores/cursos...</option>
              </select>
            </div>

            <!-- PIN -->
            <div class="col-md-6">
              <label for="txtPin" class="form-label fw-bold">PIN de Seguridad (4 dígitos)</label>
              <input type="password" id="txtPin" class="form-control" maxlength="4" placeholder="****">
              <small id="helpPin" class="form-text text-muted d-none">(Dejar en blanco para mantener el actual)</small>
            </div>

            <!-- CÓDIGO QR / TARJETA EN FORMULARIO -->
            <div class="col-12">
              <label for="txtCodigoQr" class="form-label fw-bold">Código QR / Tarjeta</label>
              <div class="input-group">
                <input type="text" id="txtCodigoQr" class="form-control" placeholder="Escanear o ingresar código de tarjeta...">
                <button type="button" id="btnEscanearQR" class="btn btn-primary fw-bold">📷 Escanear QR</button>
              </div>
            </div>

            <!-- BOTONES ACCIÓN -->
            <div class="col-12 d-flex gap-2 pt-2">
              <button type="submit" id="btnGuardar" class="btn btn-primary fw-bold flex-grow-1">Registrar Cliente</button>
              <button type="button" id="btnCancelarEdicion" class="btn btn-danger fw-bold d-none">Cancelar</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- TARJETA LISTADO -->
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <div class="row align-items-center g-2">
          <div class="col-md-5">
            <h5 class="fw-bold mb-0">📋 Listado de Clientes registrados</h5>
          </div>
          <!-- BUSCADOR CON BOTÓN DE CÁMARA QR -->
          <div class="col-md-7">
            <div class="input-group">
              <input type="text" id="txtBuscarTabla" class="form-control" placeholder="🔍 Buscar por Nombre, DNI o QR...">
              <button type="button" id="btnEscanearQRTabla" class="btn btn-dark fw-bold">📷 Buscar por QR</button>
            </div>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th style="width: 70px;">Foto</th>
                <th>Cliente</th>
                <th>DNI</th>
                <th>Sector / Curso</th>
                <th>QR / Tarjeta</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="tblClientes">
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">Cargando registros...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>

   <!-- MODAL WEBCAM FOTO PERFIL -->
  <div class="modal fade" id="modalCamara" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold">Capturar Foto de Perfil</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="btnCerrarCamara"></button>
        </div>
        <div class="modal-body text-center">
          <video id="webcam" autoplay playsinline class="w-100 rounded bg-black" style="max-height: 260px; object-fit: cover;"></video>
          <canvas id="canvasFoto" class="d-none"></canvas>
          <div class="d-flex gap-2 mt-3">
            <button type="button" id="btnCapturar" class="btn btn-success fw-bold flex-grow-1">📸 Tomar Foto</button>
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- SCRIPTS BOOTSTRAP Y LÓGICA -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/lectorQR.js"></script>
  <script src="../js/alta-cliente.js"></script>
</body>
</html>