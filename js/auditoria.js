document.addEventListener("DOMContentLoaded", async () => {
    // Validar sesión del lado del cliente
    const sessionData = sessionStorage.getItem("session") || localStorage.getItem("usuarioBanco");
    if (!sessionData) {
        window.location.href = "index.html";
        return;
    }

    const session = JSON.parse(sessionData);
    const lblUsuario = document.getElementById("lblUsuario");
    if (lblUsuario) {
        lblUsuario.innerText = `${session.nombre || session.usuario} (${session.rol})`;
    }

    // Listener de Cierre de Sesión
    document.getElementById("btnLogout")?.addEventListener("click", () => {
        sessionStorage.clear();
        localStorage.clear();
        window.location.href = "index.html";
    });

    // Carga inicial
    await cargarAuditoria();

    // Eventos de botones e insumos de búsqueda
    document.getElementById("btnFiltrar")?.addEventListener("click", cargarAuditoria);
    document.getElementById("btnExportar")?.addEventListener("click", cargarAuditoria);

    document.getElementById("txtBuscar")?.addEventListener("keyup", (e) => {
        if (e.key === "Enter") cargarAuditoria();
    });
});

async function cargarAuditoria() {
    const tbody = document.getElementById("tblAuditoria");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Consultando registros desde el servidor...</td></tr>`;

    try {
        const filtroTipo = document.getElementById("cmbTipoEvento")?.value || "TODOS";
        const filtroTexto = (document.getElementById("txtBuscar")?.value || "").trim();

        // Petición al backend PHP
        const url = `../obtener_auditoria.php?tipo=${encodeURIComponent(filtroTipo)}&buscar=${encodeURIComponent(filtroTexto)}`;
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`Error servidor HTTP: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${data.message || "Error al obtener datos de auditoría."}</td></tr>`;
            return;
        }

        const registros = data.auditoria || [];

        if (registros.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron registros que coincidan.</td></tr>`;
            return;
        }

        // Renderizado de filas en la tabla
        tbody.innerHTML = registros.map(r => {
            const fechaObj = new Date(r.fecha);
            const fechaFormat = !isNaN(fechaObj) 
                ? fechaObj.toLocaleString("es-AR", {
                    day: "2-digit", month: "2-digit", year: "numeric",
                    hour: "2-digit", minute: "2-digit", second: "2-digit"
                  })
                : r.fecha;

            return `
                <tr>
                    <td class="text-nowrap">${fechaFormat}</td>
                    <td><span class="badge ${r.claseBadge}">${r.tipo}</span></td>
                    <td><b>${r.afectado}</b></td>
                    <td class="text-warning">${r.operador}</td>
                    <td><small class="text-secondary">${r.origen}</small></td>
                    <td>${r.detalle}</td>
                    <td><span class="badge bg-outline-success border border-secondary">${r.estado}</span></td>
                </tr>
            `;
        }).join('');

    } catch (err) {
        console.error("Error al cargar auditoría:", err);
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Error de conexión al servidor de auditoría.</td></tr>`;
    }
}