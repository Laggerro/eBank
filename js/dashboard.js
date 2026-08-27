// dashboard.js
let intervalRefresco = null;

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Carga Inicial del tablero
    await actualizarTablero();

    // 2. Auto-refresco cada 5 segundos
    iniciarAutoRefresco(5000);
});

async function actualizarTablero() {
    try {
        // Ajustá la ruta según la carpeta del JS (ej. '../obtener_dashboard.php' o 'obtener_dashboard.php')
        const response = await fetch('../obtener_dashboard.php');
        
        // Si la respuesta HTTP no es exitosa (404, 500, etc.)
        if (!response.ok) {
            console.error(`Error HTTP ${response.status} al consultar obtener_dashboard.php`);
            return;
        }

        const data = await response.json();

        if (!data.success) {
            console.error("Error al obtener datos del backend:", data.message);
            return;
        }

        // --- A. ACTUALIZAR KPIS Y TARJETAS ---
        const kpis = data.kpis || {};
        
        const elEfectivo = document.getElementById('kpiEfectivoCaja');
        if (elEfectivo) elEfectivo.innerText = `$${(kpis.efectivo_caja || 0).toFixed(2)}`;

        const elCirculante = document.getElementById('kpiSaldoCirculante');
        if (elCirculante) elCirculante.innerText = `$${(kpis.saldo_circulante || 0).toFixed(2)}`;

        const elVentas = document.getElementById('kpiVentasStands');
        if (elVentas) elVentas.innerText = `$${(kpis.ventas_stands || 0).toFixed(2)}`;

        const elExtracciones = document.getElementById('kpiExtracciones');
        if (elExtracciones) elExtracciones.innerText = `$${(kpis.extracciones || 0).toFixed(2)}`;

        // Contadores del bloque "Activos"
        const elAlumnos = document.getElementById('kpiAlumnos');
        if (elAlumnos) elAlumnos.innerText = kpis.cant_alumnos ?? 0;

        const elPosnets = document.getElementById('kpiPosnets');
        if (elPosnets) elPosnets.innerText = kpis.cant_posnets ?? 0;

        // --- B. ACTUALIZAR TABLA DE ÚLTIMAS TRANSACCIONES ---
        const tbody = document.getElementById('tblTransacciones');
        if (!tbody) return;

        const transacciones = data.ultimas_transacciones;
        if (!transacciones || transacciones.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No hay movimientos registrados aún.</td></tr>`;
            return;
        }

        tbody.innerHTML = transacciones.map(t => {
            const fecha = t.fecha_hora ? new Date(t.fecha_hora).toLocaleString('es-AR') : '-';
            const tipoUpper = String(t.tipo || '').toUpperCase().trim();
            const montoNum = typeof t.monto === 'number' ? t.monto : parseFloat(t.monto || 0);

            let badgeClass = 'badge-cobro bg-danger';
            if (tipoUpper === 'RECARGA') {
                badgeClass = 'badge-recarga bg-success';
            } else if (tipoUpper === 'EXTRACCION' || tipoUpper === 'RETIRO') {
                badgeClass = 'bg-warning text-dark';
            }

            return `
                <tr>
                    <td>${fecha}</td>
                    <td><b>${t.alumno_dni || '-'}</b></td>
                    <td><span class="badge ${badgeClass}">${t.tipo || '-'}</span></td>
                    <td>${t.stand || 'Caja Central'}</td>
                    <td><b>$${montoNum.toFixed(2)}</b></td>
                    <td><span class="badge bg-secondary">${t.estado || '-'}</span></td>
                </tr>
            `;
        }).join('');

    } catch (e) {
        console.error("Error al actualizar tablero:", e);
    }
}

function iniciarAutoRefresco(intervaloMs = 5000) {                      
    detenerAutoRefresco();
    intervalRefresco = setInterval(async () => {
        if (!document.hidden) {
            await actualizarTablero();
        }
    }, intervaloMs);
}

function detenerAutoRefresco() {
    if (intervalRefresco) {
        clearInterval(intervalRefresco);
        intervalRefresco = null;
    }
}