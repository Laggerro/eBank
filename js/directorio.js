// js/directorio.js
let listaGlobal = [];
let filtroActual = 'TODOS';
let modalCamaraBs = null;
let html5QrCode = null;

document.addEventListener('DOMContentLoaded', () => {
    // Inicializamos únicamente el modal que sí existe en la página (Cámara)
    const elModalCamara = document.getElementById('modalCamara');
    if (elModalCamara) {
        modalCamaraBs = new bootstrap.Modal(elModalCamara);
        elModalCamara.addEventListener('hidden.bs.modal', cerrarCamara);
    }

    // Listener para la búsqueda global en tiempo real
    document.getElementById('txtBuscarGlobal')?.addEventListener('keyup', aplicarFiltroYBusqueda);

    // Cargar automáticamente los datos al entrar
    cargarTodo();
});

async function cargarTodo() {
    const tbody = document.getElementById('tblEntidades');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Cargando registros del sistema...</td></tr>';
    listaGlobal = [];

    try {
        const res = await fetch('../obtener_directorio.php');
        const data = await res.json();

        if (data.success) {
            // 1. Alumnos
            if (Array.isArray(data.alumnos)) {
                data.alumnos.forEach(a => {
                    listaGlobal.push({
                        tipo: 'ALUMNOS',
                        id: a.dni,
                        nombre: `${a.apellido || ''}, ${a.nombre || ''}`.trim(),
                        detalle: a.curso || 'Sin Curso',
                        qr: a.qr_code || 'Sin QR',
                        badgeClass: 'bg-primary'
                    });
                });
            }

            // 2. Usuarios Banco
            if (Array.isArray(data.usuarios)) {
                data.usuarios.forEach(u => {
                    const rolUpper = (u.rol || '').toUpperCase();
                    let tipoEntidad = 'CAJEROS';
                    let badgeClass = 'bg-warning text-dark';

                    if (rolUpper === 'ADMIN') {
                        tipoEntidad = 'ADMINS';
                        badgeClass = 'bg-danger';
                    } else if (rolUpper === 'POSNET') {
                        tipoEntidad = 'POSNETS';
                        badgeClass = 'bg-info text-dark';
                    }

                    listaGlobal.push({
                        tipo: tipoEntidad,
                        id: u.usuario || u.id,
                        nombre: u.nombre || u.usuario,
                        detalle: `Rol: ${u.rol}`,
                        qr: u.activo ? '🟢 Activo' : '🔴 Inactivo',
                        badgeClass: badgeClass
                    });
                });
            }

            // 3. POSNETs
            if (Array.isArray(data.posnets)) {
                data.posnets.forEach(p => {
                    const yaExiste = listaGlobal.some(item => item.id === p.device_id_autorizado || item.id === p.nombre_posnet);
                    if (!yaExiste) {
                        listaGlobal.push({
                            tipo: 'POSNETS',
                            id: p.device_id_autorizado || `ID: ${p.id}`,
                            nombre: p.nombre_posnet,
                            detalle: `Stand #${p.id}`,
                            qr: p.activo ? '🟢 Conectado' : '🔴 Desconectado',
                            badgeClass: 'bg-info text-dark'
                        });
                    }
                });
            }
            if (Array.isArray(data.alumnos)) {
    data.alumnos.forEach(a => {
        listaGlobal.push({
            tipo: 'ALUMNOS',
            id: a.dni,
            nombre: a.nombre_apellido || 'Sin Nombre',
            detalle: a.curso || 'Sin Curso',
            qr: a.codigo_qr || 'Sin QR',
            badgeClass: 'bg-primary'
        });
    });
}

            aplicarFiltroYBusqueda();
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${data.message || 'Error al obtener datos'}</td></tr>`;
        }
    } catch (err) {
        console.error("Error al cargar todo:", err);
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error al procesar la respuesta del servidor</td></tr>`;
    }
}

function filtrarEntidad(tipo, btn) {
    filtroActual = tipo;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    aplicarFiltroYBusqueda();
}

function aplicarFiltroYBusqueda() {
    const q = (document.getElementById('txtBuscarGlobal')?.value || '').toLowerCase().trim();

    const filtrados = listaGlobal.filter(item => {
        const cumpleTipo = (filtroActual === 'TODOS') || (item.tipo === filtroActual);
        const cumpleTexto = item.id.toString().toLowerCase().includes(q) ||
                            item.nombre.toLowerCase().includes(q) ||
                            item.detalle.toLowerCase().includes(q) ||
                            item.qr.toLowerCase().includes(q);
        return cumpleTipo && cumpleTexto;
    });

    renderTabla(filtrados);
}

function renderTabla(datos) {
    const tbody = document.getElementById('tblEntidades');
    if (!tbody) return;

    if (datos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron registros.</td></tr>`;
        return;
    }

    tbody.innerHTML = datos.map(item => `
        <tr>
            <td><span class="badge ${item.badgeClass}">${item.tipo}</span></td>
            <td class="fw-bold">${item.id}</td>
            <td>${item.nombre}</td>
            <td>${item.detalle}</td>
            <td class="text-muted small">${item.qr}</td>
        </tr>
    `).join('');
}

// ==================== LÓGICA ESCÁNER CÁMARA ====================

function abrirCamaraBusqueda() {
    if (!modalCamaraBs) return;
    modalCamaraBs.show();

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }

    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    html5QrCode.start({ facingMode: "environment" }, config, (decodedText) => {
        const txtBuscar = document.getElementById('txtBuscarGlobal');
        if (txtBuscar) {
            txtBuscar.value = decodedText;
        }
        aplicarFiltroYBusqueda();
        cerrarCamara();
        modalCamaraBs.hide();
    }).catch(err => {
        console.error("Error al iniciar cámara:", err);
        alert("No se pudo acceder a la cámara.");
    });
}

function cerrarCamara() {
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
        }).catch(err => console.error(err));
    }
}