async function apiAlumno(accion, options = {}) {
    const response = await fetch(`../procesar_alumno.php?accion=${accion}`, options);
    const data = await response.json();
    if (!data.success) throw new Error(data.message || 'No se pudo completar la operación.');
    return data;
}

async function cargarCuenta() {
    try {
        const perfil = await apiAlumno('perfil');
        const alumno = perfil.alumno;
        document.getElementById('nombreAlumno').textContent = alumno.nombre_apellido;
        document.getElementById('saldoAlumno').textContent = `$ ${Number(alumno.saldo || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 })}`;
        document.getElementById('datosAlumno').textContent = `DNI: ${alumno.dni} | Curso: ${alumno.curso || '-'}`;
        const movimientos = await apiAlumno('movimientos');
        document.getElementById('movimientosAlumno').innerHTML = movimientos.movimientos.map(m => `<tr><td>${new Date(m.fecha_hora).toLocaleString('es-AR')}</td><td>${m.tipo}</td><td>$ ${Number(m.monto).toFixed(2)}</td><td>${m.estado}</td></tr>`).join('') || '<tr><td colspan="4">Sin movimientos</td></tr>';
    } catch (error) {
        document.getElementById('mensajeTransferencia').textContent = error.message;
    }
}

document.getElementById('formTransferencia').addEventListener('submit', async event => {
    event.preventDefault();
    const mensaje = document.getElementById('mensajeTransferencia');
    try {
        const data = await apiAlumno('transferir', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ dni_destino: document.getElementById('dniDestino').value.trim(), monto: document.getElementById('montoTransferencia').value }) });
        mensaje.className = 'mt-2 text-success';
        mensaje.textContent = data.message;
        event.target.reset();
        await cargarCuenta();
    } catch (error) {
        mensaje.className = 'mt-2 text-danger';
        mensaje.textContent = error.message;
    }
});

cargarCuenta();