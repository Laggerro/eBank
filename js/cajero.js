// js/cajero.js
let alumnoActual = null;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnBuscar')?.addEventListener('click', () => {
        const q = document.getElementById('txtBuscarDni').value.trim();
        if (q) buscarAlumno(q);
    });

    document.getElementById('txtBuscarDni')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const q = e.target.value.trim();
            if (q) buscarAlumno(q);
        }
    });

    // Bindeo del botón Cámara QR usando el modal unificado
 document.getElementById('btnEscanearQR')?.addEventListener('click', () => {
    abrirLectorQR((codigoLeido) => {
        const inputDni = document.getElementById('txtBuscarDni');
        if (inputDni) {
            inputDni.value = codigoLeido;
        }
        buscarAlumno(codigoLeido); // Llama directo a la búsqueda tras cerrar el modal
    });
});
    document.getElementById('formRecarga')?.addEventListener('submit', procesarRecarga);
    document.getElementById('formExtraccion')?.addEventListener('submit', procesarExtraccion);
    document.getElementById('formResetPin')?.addEventListener('submit', procesarResetPin);
});

async function buscarAlumno(criterio) {
    try {
        const res = await fetch(`../procesar_cajero.php?accion=buscar_alumno&criterio=${encodeURIComponent(criterio)}`);
        const data = await res.json();

        if (!data.success) {
            alert(data.message || "Cliente no encontrado.");
            document.getElementById('panelAlumno').style.display = 'none';
            alumnoActual = null;
            return;
        }

        alumnoActual = data.alumno;
        document.getElementById('lblNombre').innerText = alumnoActual.nombre_apellido;
        document.getElementById('lblCurso').innerText = alumnoActual.curso || 'Sin curso';
        document.getElementById('lblDni').innerText = alumnoActual.dni;
        document.getElementById('lblQR').innerText = alumnoActual.codigo_qr || 'Sin QR';
        document.getElementById('lblSaldo').innerText = `$${Number(alumnoActual.saldo || 0).toFixed(2)}`;

        const fotoDefault = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23a0aec0'><circle cx='12' cy='7' r='4'/></svg>";
        document.getElementById('imgAlumno').src = alumnoActual.foto_url || fotoDefault;
        document.getElementById('panelAlumno').style.display = 'grid';

        await cargarUltimosMovimientos();
    } catch (err) {
        console.error("Error en buscarAlumno:", err);
        alert("Error al conectar con el servidor.");
    }
}

async function cargarUltimosMovimientos() {
    if (!alumnoActual) return;
    const tbody = document.getElementById('tblMovimientosBody');
    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted">Cargando...</td></tr>`;

    try {
        const res = await fetch(`../procesar_cajero.php?accion=movimientos&alumno_id=${alumnoActual.id}`);
        const data = await res.json();

        if (!data.success || !data.movimientos || data.movimientos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted">Sin movimientos registrados</td></tr>`;
            return;
        }

        tbody.innerHTML = data.movimientos.map(m => {
            const fechaObj = m.fecha_hora ? new Date(m.fecha_hora) : new Date();
            const fecha = fechaObj.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

            const tipoUpper = String(m.tipo || '').toUpperCase();
            const esResta = ['EXTRACCION', 'COBRO', 'COMPRA'].includes(tipoUpper);
            const clase = tipoUpper === 'RECARGA' ? 'badge-recarga' : (esResta ? 'badge-extraccion' : 'badge-posnet');

            return `
                <tr>
                    <td><small>${fecha}</small></td>
                    <td><span class="${clase}">${m.tipo}</span></td>
                    <td style="font-weight:bold; color: ${esResta ? '#dc3545' : '#28a745'}">
                        ${esResta ? '-' : '+'}$${Number(m.monto).toFixed(2)}
                    </td>
                </tr>`;
        }).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger">Error al cargar movimientos</td></tr>`;
    }
}

async function procesarRecarga(e) {
    e.preventDefault();
    if (!alumnoActual) return;

    const monto = parseFloat(document.getElementById('montoRecarga').value);
    if (isNaN(monto) || monto <= 0) return alert("Monto no válido");

    if (!confirm(`¿Acreditar $${monto.toFixed(2)} a ${alumnoActual.nombre_apellido}?`)) return;

    try {
        const res = await fetch('../procesar_cajero.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'acreditar', alumno_id: alumnoActual.id, monto })
        });
        const data = await res.json();

        alert(data.message);
        if (data.success) {
            alumnoActual.saldo = data.nuevo_saldo;
            document.getElementById('lblSaldo').innerText = `$${Number(data.nuevo_saldo).toFixed(2)}`;
            document.getElementById('formRecarga').reset();
            await cargarUltimosMovimientos();
        }
    } catch (err) {
        alert("Error al procesar la acreditación.");
    }
}

async function procesarExtraccion(e) {
    e.preventDefault();
    if (!alumnoActual) return;

    const monto = parseFloat(document.getElementById('montoExtraccion').value);
    const pin = document.getElementById('pinExtraccion').value;

    if (isNaN(monto) || monto <= 0) return alert("Monto no válido");

    try {
        const res = await fetch('../procesar_cajero.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'extraer', alumno_id: alumnoActual.id, monto, pin })
        });
        const data = await res.json();

        alert(data.message);
        if (data.success) {
            alumnoActual.saldo = data.nuevo_saldo;
            document.getElementById('lblSaldo').innerText = `$${Number(data.nuevo_saldo).toFixed(2)}`;
            document.getElementById('formExtraccion').reset();
            await cargarUltimosMovimientos();
        }
    } catch (err) {
        alert("Error al procesar la extracción.");
    }
}

async function procesarResetPin(e) {
    e.preventDefault();
    if (!alumnoActual) return;

    const nuevoPin = document.getElementById('nuevoPin').value.trim();

    try {
        const res = await fetch('../procesar_cajero.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'reset_pin', alumno_id: alumnoActual.id, nuevo_pin: nuevoPin })
        });
        const data = await res.json();

        alert(data.message);
        if (data.success) {
            document.getElementById('formResetPin').reset();
        }
    } catch (err) {
        alert("Error al intentar cambiar el PIN.");
    }
}