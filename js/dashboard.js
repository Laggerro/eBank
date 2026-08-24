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
        const response = await fetch('../obtener_dashboard.php');
        const data = await response.json();

        if (!data.success) {
            console.error("Error al obtener datos:", data.message);
            return;
        }

        // --- A. ACTUALIZAR KPIS Y TARJETAS ---
        const kpis = data.kpis;
        document.getElementById('kpiEfectivoCaja').innerText = `$${kpis.efectivo_caja.toFixed(2)}`;
        document.getElementById('kpiSaldoCirculante').innerText = `$${kpis.saldo_circulante.toFixed(2)}`;
        document.getElementById('kpiVentasStands').innerText = `$${kpis.ventas_stands.toFixed(2)}`;
        document.getElementById('kpiExtracciones').innerText = `$${kpis.extracciones.toFixed(2)}`;
        document.getElementById('kpiAlumnos').innerText = kpis.cant_alumnos;
        document.getElementById('kpiPosnets').innerText = kpis.cant_posnets;

        // --- B. ACTUALIZAR TABLA DE ÚLTIMAS TRANSACCIONES ---
        const tbody = document.getElementById('tblTransacciones');
        if (!tbody) return;

        const transacciones = data.ultimas_transacciones;
        if (!transacciones || transacciones.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No hay movimientos registrados aún.</td></tr>`;
            return;
        }

        tbody.innerHTML = transacciones.map(t => {
            const fecha = new Date(t.fecha_hora).toLocaleString('es-AR');
            const tipoUpper = String(t.tipo || '').toUpperCase().trim();

            let badgeClass = 'badge-cobro bg-danger';
            if (tipoUpper === 'RECARGA') {
                badgeClass = 'badge-recarga bg-success';
            } else if (tipoUpper === 'EXTRACCION' || tipoUpper === 'RETIRO') {
                badgeClass = 'bg-warning text-dark';
            }

            return `
                <tr>
                    <td>${fecha}</td>
                    <td><b>${t.alumno_id || '-'}</b></td>
                    <td><span class="badge ${badgeClass}">${t.tipo}</span></td>
                    <td>${t.stand}</td>
                    <td><b>$${t.monto.toFixed(2)}</b></td>
                    <td><span class="badge bg-secondary">${t.estado}</span></td>
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