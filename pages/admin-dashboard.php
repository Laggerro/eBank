<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banco Escolar - Tablero de Control</title>
  
  <!-- BOOTSTRAP 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
 
</head>
<body class="bg-light">

  <!-- INCLUIR NAVBAR DESDE SERVIDOR -->
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <main class="container-fluid px-4 py-3">
    <header class="page-header mb-4">
      <h1 class="h2 fw-bold text-dark">Estado del Banco en Tiempo Real</h1>
      <p class="text-muted">Métricas generales e indicadores</p>
    </header>

    <!-- TARJETAS DE MÉTRICAS -->
    <section class="mb-4">
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3">
        
        <div class="col">
          <div class="card bg-dark text-white border-success shadow-sm h-100">
            <div class="card-body p-3">
              <h6 class="text-uppercase text-success small fw-bold mb-1">Caja Banco (Efectivo)</h6>
              <h3 id="kpiEfectivoCaja" class="text-success fw-bold my-1">$0.00</h3>
              <small class="text-secondary" style="font-size: 0.75rem;">Efectivo físico recaudado</small>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card bg-dark text-white border-primary shadow-sm h-100">
            <div class="card-body p-3">
              <h6 class="text-uppercase text-primary small fw-bold mb-1">Saldo en Tarjetas</h6>
              <h3 id="kpiSaldoCirculante" class="text-primary fw-bold my-1">$0.00</h3>
              <small class="text-secondary" style="font-size: 0.75rem;">Disponible para gastar</small>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card bg-dark text-white border-info shadow-sm h-100">
            <div class="card-body p-3">
              <h6 class="text-uppercase text-info small fw-bold mb-1">Ventas en Stands</h6>
              <h3 id="kpiVentasStands" class="text-info fw-bold my-1">$0.00</h3>
              <small class="text-secondary" style="font-size: 0.75rem;">A rendir a los Stands</small>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card bg-dark text-white border-warning shadow-sm h-100">
            <div class="card-body p-3">
              <h6 class="text-uppercase text-warning small fw-bold mb-1">Extracciones</h6>
              <h3 id="kpiExtracciones" class="text-warning fw-bold my-1">$0.00</h3>
              <small class="text-secondary" style="font-size: 0.75rem;">Devuelto a alumnos</small>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card bg-dark text-white border-secondary shadow-sm h-100">
            <div class="card-body p-3">
              <h6 class="text-uppercase text-secondary small fw-bold mb-1">Activos</h6>
              <div class="d-flex justify-content-between align-items-center mt-2" style="font-size: 0.85rem;">
                <span>Alumnos:</span> <b id="kpiAlumnos" class="text-light">0</b>
              </div>
              <div class="d-flex justify-content-between align-items-center" style="font-size: 0.85rem;">
                <span>Stands:</span> <b id="kpiPosnets" class="text-light">0</b>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- TABLA DE ÚLTIMAS TRANSACCIONES -->
    <section class="transactions-section">
      <div class="card bg-dark text-white border-secondary shadow-sm">
        <div class="card-header border-secondary py-3">
          <h5 class="mb-0 fw-bold text-light">Últimas Transacciones del Sistema</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
              <thead>
                <tr class="table-active text-secondary">
                  <th>Fecha / Hora</th>
                  <th>Alumno (DNI)</th>
                  <th>Tipo</th>
                  <th>Detalle / Stand</th>
                  <th>Monto</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody id="tblTransacciones">
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">Cargando movimientos...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- SCRIPTS -->
  <script src="../js/dashboard.js"></script>
</body>
</html>