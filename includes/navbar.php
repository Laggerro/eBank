<?php
// navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.html");
    exit;
}

$u = $_SESSION['usuario'];
$rol = strtoupper($u['rol'] ?? 'CAJERO');
$nombreUsr = htmlspecialchars($u['nombre'] ?? $u['usuario'] ?? 'Operador');
$esAdmin = ($rol === 'ADMIN');
?>

<nav class="navbar" style="background-color: #2b6cb0; color: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
  <div style="display: flex; align-items: center; gap: 20px;">
    <span style="font-weight: bold; font-size: 1.2rem;">Banco 🏦 Escolar</span>
    
    <div class="nav-links" style="display: flex; gap: 15px;">
      <!-- Enlaces Comunes -->
      <a href="cajero-dashboard.php" style="color: white; text-decoration: none; font-weight: 500;">Inicio / Operaciones 🏠</a>
      <a href="alta-cliente.php" style="color: white; text-decoration: none; font-weight: 500;">Alta Cliente 👤</a>
      <a href="directorio.php" style="color: white; text-decoration: none; font-weight: 500;">Directorio General 👥</a>
      
      <!-- Enlaces Exclusivos de Admin -->
      <?php if ($esAdmin): ?>
        <a href="admin-dashboard.php" style="color: #fbd38d; text-decoration: none; font-weight: 600;">Tablero Estado 📊</a>
        <a href="admin-config.php" style="color: #fbd38d; text-decoration: none; font-weight: 600;">Configuración Admin ⚙</a>
        <a href="generar.php" style="color: #fbd38d; text-decoration: none; font-weight: 600;">Generar Tarjetas 🖨</a>
        <a href="auditoria.php" style="color: #fbd38d; text-decoration: none; font-weight: 600;">Auditoría ⚖</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Usuario y Botón Salir -->
  <div style="display: flex; align-items: center; gap: 12px;">
    <div style="text-align: right; line-height: 1.2;">
      <span style="font-size: 0.95rem; font-weight: 600; display: block;">👤 <?= $nombreUsr ?></span>
      <small style="font-size: 0.75rem; background: rgba(255,255,255,0.25); padding: 2px 8px; border-radius: 10px; font-weight: bold; text-transform: uppercase;">
        <?= $rol ?>
      </small>
    </div>
    <a href="../logout.php" style="background: #e53e3e; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-left: 5px; text-decoration: none;">
      Salir
    </a>
  </div>
</nav>