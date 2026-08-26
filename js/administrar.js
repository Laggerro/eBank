let idEliminarPendiente = null;

// Funciones globales para que onclick pueda encontrarlas
window.abrirModalEliminar = function(id, nombre) {
    idEliminarPendiente = id;
    const msg = document.getElementById('msgConfirmarEliminar');
    if (msg) msg.innerText = `¿Estás seguro de que deseas eliminar "${nombre}"? Esta acción no se puede deshacer.`;
    const modal = document.getElementById('modalEliminar');
    if (modal) modal.style.display = 'flex';
};

window.cerrarModalEliminar = function() {
    idEliminarPendiente = null;
    const modal = document.getElementById('modalEliminar');
    if (modal) modal.style.display = 'none';
};

document.addEventListener('DOMContentLoaded', () => {
    let qrResetScanner = null;
    let cacheUsuarios = [];
    let cachePosnets = [];

    // Cargar datos iniciales
    cargarUsuarios();
    cargarPosnets();
    cargarCursos();

    // ==========================================
    // 1. USUARIOS DEL BANCO
    // ==========================================
    async function cargarUsuarios() {
        try {
            const res = await fetch('../procesar_admin.php?action=listar_usuarios');
            const data = await res.json();
            if (!data.success) return;
            
            cacheUsuarios = Array.isArray(data.data) ? data.data : [];
            const cont = document.getElementById('listaUsuariosBanco');
            if (!cont) return;

            if (cacheUsuarios.length === 0) {
                cont.innerHTML = `<p style="text-align:center; color:#718096; font-size: 0.85rem;">No hay usuarios registrados.</p>`;
                return;
            }

            cont.innerHTML = cacheUsuarios.map(u => `
                <div class="item-lista">
                    <div class="item-info">
                        <h4>${u.usuario} <span class="item-badge badge-${(u.rol || '').toLowerCase()}">${u.rol}</span></h4>
                        <p>${u.nombre} | Retiro: ${u.puede_retirar ? 'SÍ' : 'NO'}</p>
                    </div>
                    <div class="item-acciones">
                        <button onclick="editarUsuario('${u.id}')" class="btn btn-dark btn-sm">Editar</button>
                        <button onclick="toggleEstadoUsuario('${u.id}', ${!u.activo})" class="btn ${u.activo ? 'btn-danger' : 'btn-success'} btn-sm">
                            ${u.activo ? 'Desactivar' : 'Activar'}
                        </button>
                        <button onclick="abrirModalEliminar('${u.id}', '${u.usuario}')" class="btn btn-danger btn-sm" style="background:#c53030;">🗑️ Eliminar</button>
                    </div>
                </div>
            `).join('');
        } catch (e) { console.error("Error al cargar usuarios:", e); }
    }

    window.editarUsuario = (id) => {
        const usr = cacheUsuarios.find(u => u.id == id);
        if (!usr) return;
        document.getElementById('usrId').value = usr.id;
        document.getElementById('txtUsrUsuario').value = usr.usuario;
        document.getElementById('txtUsrNombre').value = usr.nombre;
        document.getElementById('txtUsrPass').value = '';
        document.getElementById('selUsrRol').value = usr.rol;
        document.getElementById('chkPuedeRetirar').checked = !!usr.puede_retirar;
        document.getElementById('btnGuardarUsr').textContent = 'Actualizar Usuario';
        document.getElementById('btnCancelarUsr').style.display = 'inline-block';
    };

    document.getElementById('btnCancelarUsr')?.addEventListener('click', () => {
        document.getElementById('formUsuarioBanco').reset();
        document.getElementById('usrId').value = '';
        document.getElementById('btnGuardarUsr').textContent = 'Crear Usuario';
        document.getElementById('btnCancelarUsr').style.display = 'none';
    });

    document.getElementById('formUsuarioBanco')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id: document.getElementById('usrId').value,
            usuario: document.getElementById('txtUsrUsuario').value,
            nombre: document.getElementById('txtUsrNombre').value,
            password: document.getElementById('txtUsrPass').value,
            rol: document.getElementById('selUsrRol').value,
            puede_retirar: document.getElementById('chkPuedeRetirar').checked
        };

        const res = await fetch('../procesar_admin.php?action=guardar_usuario', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            e.target.reset();
            document.getElementById('usrId').value = '';
            document.getElementById('btnGuardarUsr').textContent = 'Crear Usuario';
            document.getElementById('btnCancelarUsr').style.display = 'none';
            cargarUsuarios();
        }
    });

    window.toggleEstadoUsuario = async (id, nuevoEstado) => {
        await fetch('../procesar_admin.php?action=cambiar_estado_usuario', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id, activo: nuevoEstado })
        });
        cargarUsuarios();
    };

    // Evento del botón de confirmación del Modal de Eliminación
    document.getElementById('btnConfirmarEliminar')?.addEventListener('click', async () => {
        if (!idEliminarPendiente) return;
        try {
            const res = await fetch('../procesar_admin.php?action=eliminar_usuario', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: idEliminarPendiente })
            });
            const data = await res.json();
            
            cerrarModalEliminar();
            alert(data.message);
            if (data.success) {
                cargarUsuarios();
                cargarPosnets();
            }
        } catch (e) {
            console.error("Error al eliminar:", e);
            cerrarModalEliminar();
        }
    });

    // ==========================================
    // 2. TERMINALES POSNET
    // ==========================================
    async function cargarPosnets() {
        try {
            const res = await fetch('../procesar_admin.php?action=listar_posnets');
            const data = await res.json();
            if (!data.success) return;

            cachePosnets = Array.isArray(data.data) ? data.data : [];
            const cont = document.getElementById('listaPosnets');
            if (!cont) return;

            if (cachePosnets.length === 0) {
                cont.innerHTML = `<p style="text-align:center; color:#718096; font-size: 0.85rem;">No hay terminales POSNET registradas.</p>`;
                return;
            }

            cont.innerHTML = cachePosnets.map(p => `
                <div class="item-lista">
                    <div class="item-info">
                        <h4>${p.nombre}</h4>
                        <p>Usuario: <b>${p.usuario}</b> | Acumulado: $${parseFloat(p.monto_acumulado || 0).toFixed(2)}</p>
                    </div>
                    <div class="item-acciones">
                        <button onclick="editarPosnet('${p.id}')" class="btn btn-dark btn-sm">Editar</button>
                        <button onclick="toggleEstadoPosnet('${p.id}', ${!p.activo})" class="btn ${p.activo ? 'btn-danger' : 'btn-success'} btn-sm">
                            ${p.activo ? 'Baja' : 'Alta'}
                        </button>
                        <button onclick="abrirModalEliminar('${p.id}', '${p.nombre}')" class="btn btn-danger btn-sm" style="background:#c53030;">🗑️ Eliminar</button>
                    </div>
                </div>
            `).join('');
        } catch (e) { console.error("Error al cargar posnets:", e); }
    }

    window.editarPosnet = (id) => {
        const pos = cachePosnets.find(p => p.id == id);
        if (!pos) return;
        document.getElementById('posnetId').value = pos.id;
        document.getElementById('txtPosnetNombre').value = pos.nombre;
        document.getElementById('txtPosnetUsuario').value = pos.usuario;
        document.getElementById('txtPosnetPass').value = '';
        document.getElementById('btnGuardarPosnet').textContent = 'Actualizar Terminal';
        document.getElementById('btnCancelarPosnet').style.display = 'inline-block';
    };

    document.getElementById('btnCancelarPosnet')?.addEventListener('click', () => {
        document.getElementById('formPosnet').reset();
        document.getElementById('posnetId').value = '';
        document.getElementById('btnGuardarPosnet').textContent = 'Registrar Terminal';
        document.getElementById('btnCancelarPosnet').style.display = 'none';
    });

    document.getElementById('formPosnet')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const idInput = document.getElementById('posnetId').value;
        const payload = {
            id: idInput.trim() !== '' ? idInput.trim() : null,
            nombre_stand: document.getElementById('txtPosnetNombre').value,
            usuario_posnet: document.getElementById('txtPosnetUsuario').value,
            password_posnet: document.getElementById('txtPosnetPass').value
        };

        try {
            const res = await fetch('../procesar_admin.php?action=guardar_posnet', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            if (!res.ok) {
                throw new Error("Error en la petición: Status HTTP " + res.status);
            }

            const data = await res.json();
            alert(data.message);
            if (data.success) {
                document.getElementById('formPosnet').reset();
                document.getElementById('posnetId').value = '';
                document.getElementById('btnGuardarPosnet').textContent = 'Registrar Terminal';
                document.getElementById('btnCancelarPosnet').style.display = 'none';
                cargarPosnets();
            }
        } catch (err) {
            console.error("Error al guardar posnet", err);
            alert("Ocurrió un error al procesar la solicitud.");
        }
    });

    window.toggleEstadoPosnet = async (id, nuevoEstado) => {
        try {
            await fetch('../procesar_admin.php?action=cambiar_estado_posnet', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id, activo: nuevoEstado })
            });
            cargarPosnets();
        } catch (e) {
            console.error("Error al cambiar estado:", e);
        }
    };

    // ==========================================
    // 3. CURSOS / DIVISIONES
    // ==========================================
    async function cargarCursos() {
        try {
            const res = await fetch('../procesar_admin.php?action=listar_cursos');
            const data = await res.json();
            if (!data.success) return;

            const cursosLista = Array.isArray(data.data) ? data.data : [];
            const tbody = document.getElementById('tblCursos');
            if (!tbody) return;

            if (cursosLista.length === 0) {
                tbody.innerHTML = `<tr><td colspan="2" style="text-align:center; color: #718096;">No hay cursos registrados.</td></tr>`;
                return;
            }

            tbody.innerHTML = cursosLista.map(c => `
                <tr>
                    <td>${c.nombre}</td>
                    <td style="text-align: right;">
                        <button onclick="eliminarCurso('${c.id}')" class="btn btn-danger" style="padding: 2px 8px; font-size: 0.75rem;">Eliminar</button>
                    </td>
                </tr>
            `).join('');
        } catch (e) { console.error("Error al cargar cursos:", e); }
    }

    document.getElementById('formCurso')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = { nombre: document.getElementById('txtNombreCurso').value };
        const res = await fetch('../procesar_admin.php?action=guardar_curso', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            e.target.reset();
            cargarCursos();
        }
    });

    window.eliminarCurso = async (id) => {
        if (!confirm("¿Seguro de eliminar este curso?")) return;
        await fetch('../procesar_admin.php?action=eliminar_curso', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        cargarCursos();
    };


   // ==========================================
// 4. IMPORTAR CSV
// ==========================================
document.getElementById('formImportarCSV')?.addEventListener('submit', async (e) => {
    e.preventDefault(); // Evita que la página se recargue
    
    const fileInput = document.getElementById('fileCsv');
    if (!fileInput || !fileInput.files.length) {
        alert("Por favor, seleccioná un archivo CSV.");
        return;
    }

    const formData = new FormData();
    formData.append('fileCsv', fileInput.files[0]);

    try {
        const res = await fetch('../procesar_admin.php?action=importar_csv', {
            method: 'POST',
            body: formData
            // IMPORTANTE: NO agregar 'Content-Type': 'multipart/form-data' acá.
            // El navegador debe establecer el boundary automáticamente.
        });

        if (!res.ok) {
            throw new Error(`Error HTTP Status: ${res.status}`);
        }

        const data = await res.json();
        alert(data.message);
        if (data.success) fileInput.value = '';

    } catch (err) {
        // Imprimir el error exacto en la consola para depurar
        console.error("Error detallado en la importación:", err);
        alert("Error al procesar el archivo CSV. Revisa la consola (F12) para más detalles.");
    }
});
    // ==========================================
    // 5. RESET Y MANTENIMIENTO CON CÁMARA QR
    // ==========================================
    document.getElementById('btnProcesarReset')?.addEventListener('click', () => {
        const opciones = getOpcionesReset();
        if (!Object.values(opciones).some(v => v)) {
            alert("Seleccioná al menos una opción para el mantenimiento.");
            return;
        }

        document.getElementById('modalResetQR').style.display = 'flex';
        if (!qrResetScanner) {
            qrResetScanner = new Html5QrcodeScanner("readerReset", { fps: 10, qrbox: 200 }, false);
        }
        qrResetScanner.render(onScanResetSuccess, () => {});
    });

    document.getElementById('btnCerrarResetQR')?.addEventListener('click', cerrarResetQR);

    function cerrarResetQR() {
        if (qrResetScanner) qrResetScanner.clear().catch(() => {});
        document.getElementById('modalResetQR').style.display = 'none';
    }

    async function onScanResetSuccess(qrMaestroText) {
        cerrarResetQR();
        const payload = {
            action: 'reset_bd',
            qr_code: qrMaestroText,
            opciones: getOpcionesReset()
        };

        try {
            const res = await fetch('../procesar_admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            alert(data.message);
            if (data.success) location.reload();
        } catch (err) { alert("Error al ejecutar el mantenimiento."); }
    }

    function getOpcionesReset() {
        return {
            reset_acumulados: document.getElementById('chkResetMontos').checked,
            vaciar_transacciones: document.getElementById('chkVaciarTransacciones').checked,
            vaciar_logs: document.getElementById('chkVaciarLogs').checked,
            borrar_alumnos_no_registrados: document.getElementById('chkBorrarNoReg').checked,
            vaciar_alumnos_todos: document.getElementById('chkVaciarAlumnos').checked
        };
    }
});