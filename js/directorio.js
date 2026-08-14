// /ebank/js/directorio.js

let listaGlobal = [];
let filtroActual = "TODOS";
let modalCamaraBs = null;
let html5QrCode = null;

document.addEventListener("DOMContentLoaded", () => {
  console.log("🚀 directorio.js cargado correctamente. Iniciando carga...");

  // 1. Ejecutamos la carga de datos PRIMERO que todo
  cargarTodo();

  // 2. Escuchador para la búsqueda en tiempo real
  const txtBuscar = document.getElementById("txtBuscarGlobal");
  if (txtBuscar) {
    txtBuscar.addEventListener("keyup", aplicarFiltroYBusqueda);
  }

  // 3. Inicializar Modal de Cámara de forma segura
  try {
    const elModalCamara = document.getElementById("modalCamara");
    if (elModalCamara && typeof bootstrap !== "undefined") {
      modalCamaraBs = new bootstrap.Modal(elModalCamara);
      elModalCamara.addEventListener("hidden.bs.modal", cerrarCamara);
    }
  } catch (errModal) {
    console.warn("No se pudo inicializar el modal de la cámara:", errModal);
  }
});

async function cargarTodo() {
  const tbody = document.getElementById("tblEntidades");
  if (!tbody) {
    console.error("❌ No se encontró el elemento #tblEntidades en el HTML");
    return;
  }

  tbody.innerHTML =
    '<tr><td colspan="5" class="text-center py-4 text-muted">Cargando registros del sistema...</td></tr>';
  listaGlobal = [];

  try {
    console.log("📡 Solicitando datos a ../obtener_directorio.php...");

    const res = await fetch("../obtener_directorio.php");

    if (!res.ok) {
      throw new Error(`HTTP Error: ${res.status} ${res.statusText}`);
    }

    const data = await res.json();
    console.log("📦 Datos recibidos del backend:", data);

    if (data.success) {
      // 1. ALUMNOS
      if (Array.isArray(data.alumnos)) {
    data.alumnos.forEach((a) => {
        // Validación segura del código QR
        const qrTexto = (a.codigo_qr && String(a.codigo_qr).trim() !== "") ? a.codigo_qr : "Sin QR";

        listaGlobal.push({
            tipo: "ALUMNOS",
            id_db: a.id, // <--- Guardamos la PK autoincremental de Supabase
            id: String(a.dni || "Sin DNI"), // Mantiene el DNI para mostrarlo en la columna ID/DNI
            nombre: String(a.nombre_apellido || "Sin Nombre"),
            detalle: String(a.curso || "Sin Curso"),
            qr: String(qrTexto),
            badgeClass: "bg-primary",
        });
    });
}

      // 2. USUARIOS (ADMIN, CAJERO, POSNET)
      if (Array.isArray(data.usuarios)) {
        data.usuarios.forEach((u) => {
          const rolUpper = String(u.rol || "").toUpperCase();
          let tipoEntidad = "CAJEROS";
          let badgeClass = "bg-warning text-dark";

          if (rolUpper === "ADMIN") {
            tipoEntidad = "ADMINS";
            badgeClass = "bg-danger";
          } else if (rolUpper === "POSNET") {
            tipoEntidad = "POSNETS";
            badgeClass = "bg-info text-dark";
          }

          listaGlobal.push({
            tipo: tipoEntidad,
            id: String(u.usuario || u.id || ""),
            nombre: String(u.nombre || u.usuario || "Sin Nombre"),
            detalle: `Rol: ${u.rol || "Sin Rol"}`,
            qr: u.activo ? "🟢 Activo" : "🔴 Inactivo",
            badgeClass: badgeClass,
          });
        });
      }

      console.log(
        `✅ Carga finalizada. Total registros en listaGlobal: ${listaGlobal.length}`,
      );
      aplicarFiltroYBusqueda();
    } else {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${data.message || "Error al obtener datos"}</td></tr>`;
    }
  } catch (err) {
    console.error("❌ Error en cargarTodo():", err);
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error al procesar la respuesta del servidor (${err.message})</td></tr>`;
  }
}

function filtrarEntidad(tipo, btn) {
  filtroActual = tipo;
  document
    .querySelectorAll(".filter-btn")
    .forEach((b) => b.classList.remove("active"));
  if (btn) btn.classList.add("active");
  aplicarFiltroYBusqueda();
}

function aplicarFiltroYBusqueda() {
  const inputBuscar = document.getElementById("txtBuscarGlobal");
  const q = (inputBuscar ? inputBuscar.value : "").toLowerCase().trim();

  const filtrados = listaGlobal.filter((item) => {
    const cumpleTipo = filtroActual === "TODOS" || item.tipo === filtroActual;

    if (!q) return cumpleTipo;

    const idStr = String(item.id || "").toLowerCase();
    const nomStr = String(item.nombre || "").toLowerCase();
    const detStr = String(item.detalle || "").toLowerCase();
    const qrStr = String(item.qr || "").toLowerCase();

    return (
      cumpleTipo &&
      (idStr.includes(q) ||
        nomStr.includes(q) ||
        detStr.includes(q) ||
        qrStr.includes(q))
    );
  });

  renderTabla(filtrados);
}

function renderTabla(datos) {
  const tbody = document.getElementById("tblEntidades");
  if (!tbody) return;

  if (datos.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron registros.</td></tr>`;
    return;
  }

  tbody.innerHTML = datos
    .map(
      (item) => `
        <tr>
            <td><span class="badge ${item.badgeClass}">${item.tipo}</span></td>
            <td class="fw-bold">${item.id}</td>
            <td>${item.nombre}</td>
            <td>${item.detalle}</td>
            <td class="text-muted small">${item.qr}</td>
        </tr>
    `,
    )
    .join("");
}

// ==================== ESCÁNER CÁMARA ====================

function abrirCamaraBusqueda() {
  if (!modalCamaraBs) return;
  modalCamaraBs.show();

  if (!html5QrCode) {
    html5QrCode = new Html5Qrcode("reader");
  }

  const config = { fps: 10, qrbox: { width: 250, height: 250 } };

  html5QrCode
    .start({ facingMode: "environment" }, config, (decodedText) => {
      const txtBuscar = document.getElementById("txtBuscarGlobal");
      if (txtBuscar) {
        txtBuscar.value = decodedText;
      }
      aplicarFiltroYBusqueda();
      cerrarCamara();
      modalCamaraBs.hide();
    })
    .catch((err) => {
      console.error("Error al iniciar cámara:", err);
      alert("No se pudo acceder a la cámara.");
    });
}

function cerrarCamara() {
  if (html5QrCode && html5QrCode.isScanning) {
    html5QrCode
      .stop()
      .then(() => {
        html5QrCode.clear();
      })
      .catch((err) => console.error(err));
  }
}
