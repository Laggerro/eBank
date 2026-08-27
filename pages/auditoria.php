<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banco Escolar - Auditoría General</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/cajero.css">
</head>

<body class="bg-light">
      <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
<div id="navbar-container"></div>

  <!-- CONTENIDO PRINCIPAL -->
  <div class="container my-4" style="flex: 1; flex-direction: column">
    
    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card bg-secondary bg-opacity-10 border-secondary mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label text-secondary small">Filtrar por Tipo</label>
         <select id="cmbTipoEvento" class="form-select bg-dark text-white border-secondary">
  <option value="TODOS">-- Todos los eventos --</option>
  <option value="RECARGA">Recargas de Saldo</option>
  <option value="COBRO">Cobros en POSNET</option>
  <option value="EXTRACCION">Extracciones en Efectivo</option>
  <option value="ALTA_ALUMNO">Altas de Clientes</option>
  <option value="BAJA_ALUMNO">Bajas de Clientes</option>
  <option value="MODIFICACION_ALUMNO">Modificaciones</option>
  <option value="BLANQUEO_PIN">Blanqueos de PIN</option>
  <option value="INTENTO_FALLIDO_PIN">Intentos Fallidos PIN</option>
</select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-secondary small">Buscar (DNI / Usuario / Stand)</label>
            <input type="text" id="txtBuscar" class="form-control bg-dark text-white border-secondary" placeholder="Ej: 46342761 o Administrador">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button id="btnFiltrar" class="btn btn-warning w-100 fw-bold">Filtrar Audit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLA DE AUDITORÍA -->
    <div class="card bg-secondary bg-opacity-10 border-secondary">
      <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-warning">Historial de Intervenciones</h5>
        <button id="btnExportar" class="btn btn-outline-success btn-sm">📥 Refrescar</button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
              <tr class="table-active text-secondary">
                <th>Fecha y Hora</th>
                <th>Tipo de Evento</th>
                <th>Afectado (Alumno / Destino)</th>
                <th>Operador / Interviniente</th>
                <th>Origen / Stand</th>
                <th>Detalle / Monto</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody id="tblAuditoria">
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">Cargando datos de auditoría...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script src="../js/auditoria.js"></script>


</body>

</html>