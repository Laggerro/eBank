// Usamos el cliente global o creamos uno si no existe
const db = window._supabase || supabase.createClient(SUPABASE_URL, SUPABASE_KEY);

let html5QrcodeScanner = null;

window.onload = () => {
    html5QrcodeScanner = new Html5Qrcode("reader");
    html5QrcodeScanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        onScanSuccess
    );
};

async function onScanSuccess(codigoQr) {
    html5QrcodeScanner.pause();

    // 1. Consultar Alumno en Supabase (necesitamos también el DNI para buscar sus movimientos)
    const { data: alumno, error } = await db
        .from("alumnos")
        .select("dni, nombre_apellido, curso, saldo, foto_url")
        .eq("codigo_qr", codigoQr)
        .maybeSingle();

    if (error || !alumno) {
        // MOSTRAR PANTALLA DE ERROR POR 4 SEGUNDOS
        document.getElementById("viewScan").classList.add("d-none");
        document.getElementById("viewError").classList.remove("d-none");

        iniciarTemporizadorReiniciar("viewError", "progressBarError", 4);
        return;
    }

    // 2. Cargar datos del alumno en pantalla
    document.getElementById("resNombre").innerText = alumno.nombre_apellido;
    document.getElementById("resCurso").innerText = alumno.curso || "Alumno";
    document.getElementById("resSaldo").innerText = `$ ${Number(alumno.saldo || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 })}`;
    document.getElementById("resFoto").src = alumno.foto_url || "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";

    // 3. Consultar los últimos 5 movimientos
    await cargarUltimosMovimientos(alumno.dni);

    // 4. Mostrar pantalla de resultado por 8 segundos (ampliado un poco para dar tiempo a leer)
    document.getElementById("viewScan").classList.add("d-none");
    document.getElementById("viewResult").classList.remove("d-none");

    iniciarTemporizadorReiniciar("viewResult", "progressBar", 8);
}

async function cargarUltimosMovimientos(dni) {
    const container = document.getElementById("listaMovimientos");
    if (!container) return;

    container.innerHTML = `<div class="text-muted small py-2">Cargando últimos movimientos...</div>`;

    try {
        const { data: transacciones, error } = await db
            .from("transacciones")
            .select("tipo, monto, fecha_hora")
            .eq("alumno_dni", String(dni))
            .order("fecha_hora", { ascending: false })
            .limit(5);

        if (error || !transacciones || transacciones.length === 0) {
            container.innerHTML = `<div class="text-muted small py-2">Sin movimientos recientes</div>`;
            return;
        }

        // Renderizar tabla compacta
        container.innerHTML = `
            <table class="table table-dark table-sm table-borderless text-start align-middle m-0" style="font-size: 0.85rem;">
                <thead>
                    <tr class="text-muted border-bottom border-secondary">
                        <th>Hora/Fecha</th>
                        <th>Tipo</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    ${transacciones.map(t => {
                        const fecha = t.fecha_hora ? new Date(t.fecha_hora) : new Date();
                        const horaFormateada = fecha.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
                        const fechaFormateada = fecha.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' });
                        
                        const tipoUpper = String(t.tipo || '').toUpperCase();
                        const esResta = tipoUpper === 'EXTRACCION' || tipoUpper === 'COBRO' || tipoUpper === 'COMPRA';
                        const colorClass = esResta ? 'text-danger' : 'text-success';
                        const signo = esResta ? '-' : '+';

                        return `
                            <tr class="border-bottom border-dark">
                                <td><span class="text-white-50">${fechaFormateada}</span> <small class="text-muted">${horaFormateada}</small></td>
                                <td><span class="badge ${esResta ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'} p-1">${tipoUpper}</span></td>
                                <td class="text-end fw-bold ${colorClass}">${signo}$${Number(t.monto).toFixed(2)}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    } catch (err) {
        console.error("Error al obtener movimientos:", err);
        container.innerHTML = `<div class="text-danger small py-2">No se pudieron cargar los movimientos</div>`;
    }
}

function iniciarTemporizadorReiniciar(viewActualId, progressBarId, segundos) {
    let tiempo = segundos;
    const progress = document.getElementById(progressBarId);
    progress.style.width = "100%";

    const interval = setInterval(() => {
        tiempo -= 0.1; // Suavidad en la barra
        const porcentaje = (tiempo / segundos) * 100;
        progress.style.width = `${porcentaje}%`;

        if (tiempo <= 0) {
            clearInterval(interval);
            // Ocultar pantalla actual y volver al escáner
            document.getElementById(viewActualId).classList.add("d-none");
            document.getElementById("viewScan").classList.remove("d-none");
            html5QrcodeScanner.resume();
        }
    }, 100);
}