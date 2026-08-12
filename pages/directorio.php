<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banco Escolar - Directorio General de Entidades</title>
  
  <!-- BOOTSTRAP 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- LIBRERÍA ESCÁNER QR -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body class="bg-light">

  <!-- NAVBAR DESDE SERVIDOR -->
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <main class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h2 fw-bold text-dark">Directorio General de Entidades</h1>
        <p class="text-muted mb-0">Vista de la estructura del banco</p>
      </div>
    </div>

    <!-- BUSCADOR CON CÁMARA Y FILTROS -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-7 col-12">
            <div class="input-group">
              <span class="input-group-text bg-white">🔍</span>
              <input type="text" id="txtBuscarGlobal" class="form-control" placeholder="Buscar por DNI, Nombre, Usuario o QR...">
            </div>
          </div>
          <div class="col-md-2 col-6">
            <button class="btn btn-dark w-100 fw-bold" onclick="abrirCamaraBusqueda()">📷 Escanear QR</button>
          </div>
          <div class="col-md-3 col-6">
            <button class="btn btn-outline-secondary w-100 fw-bold" onclick="cargarTodo()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- FILTROS (PESTAÑAS) -->
        <div class="btn-group w-100" role="group">
          <button type="button" class="btn btn-outline-primary active filter-btn" onclick="filtrarEntidad('TODOS', this)">Todos</button>
          <button type="button" class="btn btn-outline-primary filter-btn" onclick="filtrarEntidad('ALUMNOS', this)">Alumnos</button>
          <button type="button" class="btn btn-outline-primary filter-btn" onclick="filtrarEntidad('CAJEROS', this)">Cajeros</button>
          <button type="button" class="btn btn-outline-primary filter-btn" onclick="filtrarEntidad('POSNETS', this)">POSNETS</button>
          <button type="button" class="btn btn-outline-primary filter-btn" onclick="filtrarEntidad('ADMINS', this)">Admins</button>
        </div>
      </div>
    </div>

    <!-- TABLA UNIFICADA -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>Tipo / Rol</th>
                <th>Identificador / DNI</th>
                <th>Nombre / Descripción</th>
                <th>Curso / Stand</th>
                <th>Código QR / Tarjeta</th>
              </tr>
            </thead>
            <tbody id="tblEntidades">
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">Cargando registros...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- MODAL CÁMARA ESCÁNER QR -->
  <div class="modal fade" id="modalCamara" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold">Escanear Tarjeta QR</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="cerrarCamara()"></button>
        </div>
        <div class="modal-body text-center">
          <div id="reader" style="width: 100%; max-width: 400px; margin: 0 auto;"></div>
          <p class="text-muted small mt-2">Apunta la tarjeta QR a la cámara de tu PC o celular.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/directorio.js"></script>
</body>
</html>