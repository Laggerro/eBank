let alumnoActual = null;
let html5QrcodeScanner = null;

document.addEventListener('DOMContentLoaded', () => {
    const sessionData = localStorage.getItem('usuarioBanco') || sessionStorage.getItem('session');
    if (!sessionData) {
        window.location.href = 'index.html';
        return;
    }
    const session = JSON.parse(sessionData);

    const lblUsuario = document.getElementById('lblUsuario');
    if (lblUsuario) {
        lblUsuario.innerText = `${session.nombre || session.usuario || 'Usuario'} (${session.rol || 'CAJERO'})`;
    }

    const secExtraccion = document.getElementById('secExtraccion');
    if (secExtraccion) {
        const puedeRetirar = session.puede_retirar === true || session.puede_retirar === 'true' || session.puede_retirar === 1;
        const esAdmin = (session.rol || '').toUpperCase() === 'ADMIN';
        if (puedeRetirar || esAdmin) {
            secExtraccion.style.display = 'block';
        } else {
            secExtraccion.style.display = 'none';
        }
    }

    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) {
        btnLogout.addEventListener('click', () => {
            localStorage.removeItem('usuarioBanco');
            sessionStorage.clear();
            window.location.href = 'index.html';
        });
    }

    const btnBuscar = document.getElementById('btnBuscar');
    if (btnBuscar) {
        btnBuscar.addEventListener('click', () => {
            const query = document.getElementById('txtBuscarDni').value.trim();
            if (query) buscarAlumno(query);
        });
    }

    const txtBuscarDni = document.getElementById('txtBuscarDni');
    if (txtBuscarDni) {
        txtBuscarDni.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const query = txtBuscarDni.value.trim();
                if (query) buscarAlumno(query);
            }
        });
    }

    const btnEscanearQR = document.getElementById('btnEscanearQR');
    if (btnEscanearQR) btnEscanearQR.addEventListener('click', abrirLectorQR);

    const btnCerrarLectorQR = document.getElementById('btnCerrarLectorQR');
    if (btnCerrarLectorQR) btnCerrarLectorQR.addEventListener('click', cerrarLectorQR);

    const formRecarga = document.getElementById('formRecarga');
    if (formRecarga) formRecarga.addEventListener('submit', solicitarConfirmacionRecarga);

    const formResetPin = document.getElementById('formResetPin');
    if (formResetPin) formResetPin.addEventListener('submit', procesarResetPin);

    const formExtraccion = document.getElementById('formExtraccion');
    if (formExtraccion) formExtraccion.addEventListener('submit', procesarExtraccion);
});

async function buscarAlumno(criterio) {
    try {
        const client = window._supabase || supabase;
        const { data, error } = await client
            .from('alumnos')
            .select('*')
            .or(`dni.eq.${criterio},codigo_qr.eq.${criterio}`)
            .single();

        if (error || !data) {
            alert("Alumno no encontrado. Verificá el DNI o el QR de la tarjeta.");
            const panel = document.getElementById('panelAlumno');
            if (panel) panel.style.display = 'none';
            alumnoActual = null;
            return;
        }

        alumnoActual = data;
        document.getElementById('lblNombre').innerText = data.nombre_apellido;
        document.getElementById('lblCurso').innerText = data.curso || 'Sin curso';
        document.getElementById('lblDni').innerText = data.dni;
        document.getElementById('lblQR').innerText = data.codigo_qr || 'Sin QR registrado';
        document.getElementById('lblSaldo').innerText = `$${Number(data.saldo || 0).toFixed(2)}`;

        const fotoDefault = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23a0aec0'><circle cx='12' cy='7' r='4'/></svg>";
        document.getElementById('imgAlumno').src = data.foto_url || fotoDefault;

        const panelAlumno = document.getElementById('panelAlumno');
        if (panelAlumno) panelAlumno.style.display = 'grid';

        // Cargar historial con el nombre exacto de columna: fecha_hora
        await cargarUltimosMovimientos(data.dni);

    } catch (err) {
        console.error(err);
        alert("Error al realizar la consulta en la base de datos.");
    }
}

// CONSULTA DE MOVIMIENTOS AJUSTADA A LA BASE DE DATOS REAL
async function cargarUltimosMovimientos(dni) {
    const tbody = document.getElementById('tblMovimientosBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center; color:#888;">Cargando...</td></tr>`;

    try {
        const client = window._supabase || supabase;
        
        // Ordenamos estrictamente por 'fecha_hora' desc
        const { data, error } = await client
            .from('transacciones')
            .select('*')
            .eq('alumno_dni', String(dni))
            .order('fecha_hora', { ascending: false })
            .limit(10);

        if (error) {
            console.error("Error Supabase al traer movimientos:", error);
            tbody.innerHTML = `<tr><td colspan="3" style="text-align:center; color:red;">Error de lectura</td></tr>`;
            return;
        }

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" style="text-align:center; color:#888;">Sin movimientos registrados</td></tr>`;
            return;
        }

        tbody.innerHTML = data.map(m => {
            const fechaObj = m.fecha_hora ? new Date(m.fecha_hora) : new Date();
            const fecha = fechaObj.toLocaleString('es-AR', {
                day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
            });

            let claseBadge = 'text-muted';
            const tipoUpper = String(m.tipo || '').toUpperCase();
            
            if (tipoUpper === 'RECARGA') claseBadge = 'badge-recarga';
            else if (tipoUpper === 'EXTRACCION') claseBadge = 'badge-extraccion';
            else if (tipoUpper === 'COBRO' || tipoUpper === 'COMPRA') claseBadge = 'badge-posnet';

            const esResta = tipoUpper === 'EXTRACCION' || tipoUpper === 'COBRO' || tipoUpper === 'COMPRA';
            const signo = esResta ? '-' : '+';

            return `
                <tr>
                    <td><small>${fecha}</small></td>
                    <td><span class="${claseBadge}">${m.tipo}</span></td>
                    <td style="font-weight:bold; color: ${esResta ? '#dc3545' : '#28a745'}">
                        ${signo}$${Number(m.monto).toFixed(2)}
                    </td>
                </tr>
            `;
        }).join('');

    } catch (err) {
        console.error("Error al obtener movimientos:", err);
        tbody.innerHTML = `<tr><td colspan="3" style="text-align:center; color:red;">Error de conexión</td></tr>`;
    }
}

// CONFIRMACIÓN VISUAL PREVIA AL DEPÓSITO
function solicitarConfirmacionRecarga(e) {
    e.preventDefault();
    if (!alumnoActual) return;

    const monto = parseFloat(document.getElementById('montoRecarga').value);
    if (isNaN(monto) || monto <= 0) {
        alert("Ingresá un monto válido.");
        return;
    }

    const confirmacion = confirm(
        `⚠️ CONFIRMACIÓN DE DEPÓSITO\n\n` +
        `¿Desea acreditar $${monto.toFixed(2)} a favor de:\n` +
        `👤 ${alumnoActual.nombre_apellido} (DNI: ${alumnoActual.dni})?`
    );

    if (confirmacion) {
        procesarRecarga(monto);
    }
}

async function procesarRecarga(monto) {
    const sessionData = localStorage.getItem('usuarioBanco') || sessionStorage.getItem('session');
    const session = JSON.parse(sessionData || '{}');
    const client = window._supabase || supabase;

    try {
        const { error: errUpdate } = await client.rpc('cargar_saldo', {
            p_dni: alumnoActual.dni,
            p_monto: monto
        });
        if (errUpdate) throw errUpdate;

        // Aseguramos conversión de tipo para usuario_banco_id (int8)
        const idUsuario = session.id ? parseInt(session.id) : null;

        await client.from('transacciones').insert([{
            alumno_dni: String(alumnoActual.dni),
            usuario_banco_id: isNaN(idUsuario) ? null : idUsuario,
            monto: monto,
            tipo: 'RECARGA',
            estado: 'OK'
        }]);

        alumnoActual.saldo = Number(alumnoActual.saldo || 0) + monto;
        alert(`¡Carga exitosa!\nNuevo saldo de ${alumnoActual.nombre_apellido}: $${Number(alumnoActual.saldo).toFixed(2)}`);

        document.getElementById('formRecarga').reset();
        document.getElementById('lblSaldo').innerText = `$${Number(alumnoActual.saldo).toFixed(2)}`;

        await cargarUltimosMovimientos(alumnoActual.dni);

    } catch (err) {
        console.error(err);
        alert("Error al procesar la carga: " + err.message);
    }
}

async function procesarExtraccion(e) {
    e.preventDefault();
    if (!alumnoActual) return;

    const montoInput = document.getElementById('montoExtraccion').value;
    const pinInput = document.getElementById('pinExtraccion').value;
    const monto = parseFloat(montoInput);
    const pinIngresado = String(pinInput).trim();

    if (isNaN(monto) || monto <= 0) {
        alert("Ingresá un monto válido para extraer.");
        return;
    }

    const saldoActual = Number(alumnoActual.saldo || 0);
    if (monto > saldoActual) {
        alert(`Fondos insuficientes. El alumno solo dispone de $${saldoActual.toFixed(2)}`);
        return;
    }

    const pinDB = String(alumnoActual.pin || '').trim();
    if (pinIngresado !== pinDB) {
        alert("🔑 PIN incorrecto. El alumno debe ingresar su clave secreta.");
        document.getElementById('pinExtraccion').value = '';

        const sessionData = localStorage.getItem('usuarioBanco') || sessionStorage.getItem('session');
        const session = JSON.parse(sessionData || '{}');
        await registrarLog('INTENTO_FALLIDO_PIN', session.nombre || session.usuario,
            `Alumno DNI ${alumnoActual.dni}`, `Fallo de clave en intento de extracción de $${monto}`);
        return;
    }

    const sessionData = localStorage.getItem('usuarioBanco') || sessionStorage.getItem('session');
    const session = JSON.parse(sessionData || '{}');
    const client = window._supabase || supabase;

    try {
        const { error: errUpdate } = await client.rpc('cargar_saldo', {
            p_dni: alumnoActual.dni,
            p_monto: -monto
        });
        if (errUpdate) throw errUpdate;

        const idUsuario = session.id ? parseInt(session.id) : null;

        await client.from('transacciones').insert([{
            alumno_dni: String(alumnoActual.dni),
            usuario_banco_id: isNaN(idUsuario) ? null : idUsuario,
            monto: monto,
            tipo: 'EXTRACCION',
            estado: 'OK'
        }]);

        alumnoActual.saldo = saldoActual - monto;
        alert(`¡Extracción autorizada! Entregar $${monto.toFixed(2)} a ${alumnoActual.nombre_apellido}.\nNuevo saldo: $${Number(alumnoActual.saldo).toFixed(2)}`);

        document.getElementById('formExtraccion').reset();
        document.getElementById('lblSaldo').innerText = `$${Number(alumnoActual.saldo).toFixed(2)}`;

        await cargarUltimosMovimientos(alumnoActual.dni);

    } catch (err) {
        console.error("Error crítico en extracción:", err);
        alert("Error al procesar el retiro: " + (err.message || err));
    }
}

async function procesarResetPin(e) {
    e.preventDefault();
    if (!alumnoActual) return;

    const nuevoPin = document.getElementById('nuevoPin').value.trim();
    if (nuevoPin.length !== 4 || isNaN(nuevoPin)) {
        alert("El PIN debe ser una clave numérica de 4 dígitos.");
        return;
    }

    const sessionData = localStorage.getItem('usuarioBanco') || sessionStorage.getItem('session');
    const session = JSON.parse(sessionData || '{}');
    const client = window._supabase || supabase;

    try {
        const { error } = await client
            .from('alumnos')
            .update({ pin: nuevoPin })
            .eq('dni', alumnoActual.dni);

        if (error) throw error;

        await registrarLog(
            'BLANQUEO_PIN',
            session.nombre || session.usuario,
            `Alumno DNI: ${alumnoActual.dni} (${alumnoActual.nombre_apellido})`,
            `Se cambió/blanqueó la clave PIN a '${nuevoPin}' por solicitud presencial.`
        );

        alert("¡PIN actualizado con éxito!");
        document.getElementById('formResetPin').reset();
        alumnoActual.pin = nuevoPin;
    } catch (err) {
        console.error(err);
        alert("Error al restaurar el PIN: " + err.message);
    }
}

async function registrarLog(tipoEvento, usuarioOrigen, usuarioDestino, detalle) {
    try {
        const client = window._supabase || supabase;
        await client.from('logs_auditoria').insert([{
            tipo_evento: tipoEvento,
            usuario_origen: usuarioOrigen || 'SISTEMA',
            usuario_destino: usuarioDestino || 'N/A',
            detalle: detalle
        }]);
    } catch (err) {
        console.error("Error al registrar evento en logs_auditoria:", err);
    }
}

async function abrirLectorQR() {
    const modalLector = document.getElementById('modalLectorQR');
    if (modalLector) modalLector.style.display = 'flex';

    if (html5QrcodeScanner) {
        try {
            await html5QrcodeScanner.stop();
            html5QrcodeScanner.clear();
        } catch (e) {}
    }

    html5QrcodeScanner = new Html5Qrcode("reader");
    const config = {
        fps: 20,
        qrbox: function (viewfinderWidth, viewfinderHeight) {
            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
            return {
                width: Math.floor(minEdge * 0.8),
                height: Math.floor(minEdge * 0.8)
            };
        },
        aspectRatio: 1.0
    };

    const onScanSuccess = (qrMessage) => {
        const txtBuscar = document.getElementById('txtBuscarDni');
        if (txtBuscar) txtBuscar.value = qrMessage.trim();
        cerrarLectorQR();
        buscarAlumno(qrMessage.trim());
    };

    html5QrcodeScanner.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        () => {}
    ).catch(err => {
        html5QrcodeScanner.start(
            { facingMode: "user" },
            config,
            onScanSuccess,
            () => {}
        ).catch(finalErr => {
            alert("No se pudo acceder a la cámara.");
            cerrarLectorQR();
        });
    });
}

function cerrarLectorQR() {
    const modalLector = document.getElementById('modalLectorQR');
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner.clear();
            if (modalLector) modalLector.style.display = 'none';
        }).catch(() => {
            if (modalLector) modalLector.style.display = 'none';
        });
    } else if (modalLector) {
        modalLector.style.display = 'none';
    }
}