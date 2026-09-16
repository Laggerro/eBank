let montoIngresado = "0";
let qrEscaneadoActual = null;
let html5Qrcode = null;

window.onload = async () => {
    document.getElementById("posnetNombre").innerText = "🏪 POSNET";
    document.getElementById("posnetUsuario").innerText = "Terminal autenticada";
    const btnLogout = document.getElementById("btnLogout");
    if (btnLogout) {
        btnLogout.addEventListener("click", cerrarSesion);
    }

    try {
        await cargarMetricasPOSNET();
    } catch (error) {
        console.error("Error al cargar métricas POSNET:", error);
        showStatus("🔴 No se pudo validar la sesión del POSNET", "alert-danger");
    }

    // Inicializamos el objeto Html5Qrcode pero SIN encender la cámara de entrada
    html5Qrcode = new Html5Qrcode("reader");

    const codigoManual = document.getElementById("codigoManualPosnet");
    if (codigoManual) {
        codigoManual.addEventListener("keydown", (event) => {
            if (event.key === "Enter") ingresarCodigoManual();
        });
    }
};

// --- FUNCIÓN PARA CERRAR SESIÓN ---
function cerrarSesion() {
    if (confirm("¿Seguro que querés cerrar la sesión del POSNET?")) {
        // Detener cámara si estaba encendida por seguridad
        detenerCamara();

        // Borrar todos los tokens y credenciales guardados en el navegador
        window.location.href = "../logout.php";
    }
}

async function cargarMetricasPOSNET() {
    const response = await fetch("../procesar_posnet.php?accion=metricas");
    const result = await response.json();

    if (!result.success) {
        throw new Error(result.message || "No se pudieron cargar las métricas.");
    }

    document.getElementById("posnetNombre").innerText = `🏪 ${result.nombre || "POSNET"}`;
    document.getElementById("posnetUsuario").innerText = `Terminal: ${result.usuario || "-"}`;
    document.getElementById("montoAcumulado").innerText = `$ ${(result.metricas.monto_acumulado || 0).toLocaleString()}`;
    document.getElementById("cantVentas").innerText = result.metricas.cant_transacciones || 0;
}

// --- TECLADO NUMÉRICO DE COBRO ---
function pressKey(val) {
    if (montoIngresado === "0") montoIngresado = val;
    else montoIngresado += val;
    updateDisplay();
}

function clearKeypad() {
    montoIngresado = "0";
    updateDisplay();
}

function updateDisplay() {
    document.getElementById("displayMonto").innerText = `$ ${parseInt(montoIngresado).toLocaleString()}`;
}

async function abrirModalAnulacion() {
    const modalElem = document.getElementById("modalAnulacion");
    const select = document.getElementById("ventaAnulacion");
    if (!modalElem || !select) return;

    select.innerHTML = '<option value="">Cargando ventas...</option>';
    new bootstrap.Modal(modalElem).show();

    try {
        const response = await fetch("../procesar_posnet.php?accion=listar_anulables");
        const result = await response.json();
        if (!result.success) throw new Error(result.message || "No se pudieron cargar las ventas.");

        if (!result.transacciones.length) {
            select.innerHTML = '<option value="">No hay ventas anulables</option>';
            return;
        }

        select.innerHTML = result.transacciones.map((venta) => {
            const fecha = venta.fecha_hora ? new Date(venta.fecha_hora).toLocaleString("es-AR") : "sin fecha";
            const monto = Number(venta.monto || 0).toLocaleString("es-AR");
            return `<option value="${venta.id}">#${venta.id} - DNI ${venta.dni || "-"} - $ ${monto} - ${fecha}</option>`;
        }).join("");
    } catch (error) {
        select.innerHTML = '<option value="">No se pudieron cargar las ventas</option>';
        showStatus(`🔴 ${error.message}`, "alert-danger");
    }
}

function cerrarModalAnulacion() {
    const modal = bootstrap.Modal.getInstance(document.getElementById("modalAnulacion"));
    if (modal) modal.hide();
}

async function confirmarAnulacion() {
    const transaccionId = document.getElementById("ventaAnulacion")?.value || "";
    const pin = document.getElementById("pinAnulacion")?.value || "";
    const codigoMaestro = document.getElementById("codigoMaestroAnulacion")?.value.trim() || "";

    if (!transaccionId) {
        alert("Seleccione una venta.");
        return;
    }
    if (!/^\d{4}$/.test(pin)) {
        alert("Ingrese el PIN del alumno de 4 dígitos.");
        return;
    }
    if (!codigoMaestro) {
        alert("Ingrese el código maestro.");
        return;
    }

    try {
        const response = await fetch("../procesar_posnet.php?accion=anular", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ transaccion_id: transaccionId, pin, codigo_maestro: codigoMaestro })
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || "No se pudo anular la venta.");

        cerrarModalAnulacion();
        document.getElementById("pinAnulacion").value = "";
        document.getElementById("codigoMaestroAnulacion").value = "";
        await cargarMetricasPOSNET();
        showStatus(`✅ ${result.message}`, "alert-success");
    } catch (error) {
        alert(`❌ ${error.message}`);
    }
}

// --- FLUJO: BOTÓN COBRAR Y CÁMARA ---

async function iniciarCobro() {
    const monto = parseInt(montoIngresado);
    if (monto <= 0) {
        showStatus("🔴 Ingrese un monto mayor a $0 para cobrar", "alert-danger");
        return;
    }

    // Abrir modal de escáner QR
    const modalScannerElem = document.getElementById("modalScanner");
    const modalScanner = new bootstrap.Modal(modalScannerElem);
    modalScanner.show();

    // Encender la cámara
    try {
        await html5Qrcode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            onScanSuccess
        );
        showStatus("📷 Lea el código QR del alumno...", "alert-info");
    } catch (err) {
        console.error("Error al iniciar cámara:", err);
        showStatus("🟡 Cámara no disponible. Ingrese el código manualmente.", "alert-warning");
        const codigoManual = document.getElementById("codigoManualPosnet");
        if (codigoManual) codigoManual.focus();
    }
}

// Al detectar un código QR
async function onScanSuccess(decodedText) {
    // 1. Apagar cámara
    await detenerCamara();

    // 2. Cerrar el modal del lector QR
    const modalScannerElem = document.getElementById("modalScanner");
    const modalScanner = bootstrap.Modal.getInstance(modalScannerElem);
    if (modalScanner) modalScanner.hide();

    qrEscaneadoActual = decodedText;
    await buscarAlumnoParaCobro(decodedText);
}

// Permite continuar el cobro cuando la cámara no está disponible.
async function ingresarCodigoManual() {
    const input = document.getElementById("codigoManualPosnet");
    const codigo = input ? input.value.trim() : "";

    if (!codigo) {
        showStatus("🔴 Ingrese el contenido del código QR", "alert-danger");
        if (input) input.focus();
        return;
    }

    await detenerCamara();
    const modalScanner = bootstrap.Modal.getInstance(document.getElementById("modalScanner"));
    if (modalScanner) modalScanner.hide();
    qrEscaneadoActual = codigo;
    await buscarAlumnoParaCobro(codigo);
}

async function buscarAlumnoParaCobro(codigoQr) {

    let result;
    try {
        const response = await fetch("../procesar_posnet.php?accion=buscar_alumno", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ codigo_qr: codigoQr })
        });
        result = await response.json();
    } catch (error) {
        alert("No se pudo consultar el código QR.");
        showStatus("🔴 Error de conexión", "alert-danger");
        return;
    }

    if (!result.success) {
        alert(`❌ ${result.message || "Código QR no registrado en el sistema."}`);
        showStatus("🟡 Ingrese el monto a cobrar", "alert-secondary");
        return;
    }

    const alumno = result.alumno;

    // 4. Cargar datos del alumno en el modal del PIN
    document.getElementById("modalAlumnoNombre").innerText = alumno.nombre_apellido;
    document.getElementById("modalAlumnoFoto").src = alumno.foto_url || "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";
    document.getElementById("modalMontoCobrar").innerText = `$ ${parseInt(montoIngresado).toLocaleString()}`;

    // Limpiar pantalla del PIN virtual
    clearPin();

    // 5. Mostrar modal del PIN tras pequeño tiempo de espera
    setTimeout(() => {
        const modalPinElem = document.getElementById("modalPin");
        const modalPin = new bootstrap.Modal(modalPinElem);
        modalPin.show();
    }, 300);
}

// Apaga físicamente el hardware de la cámara
async function detenerCamara() {
    if (html5Qrcode && html5Qrcode.isScanning) {
        try {
            await html5Qrcode.stop();
        } catch (err) {
            console.error("Error al apagar la cámara:", err);
        }
    }
}

// Cancelar escáner desde el modal QR
async function cancelarEscaneo() {
    await detenerCamara();
    const modalScannerElem = document.getElementById("modalScanner");
    const modalScanner = bootstrap.Modal.getInstance(modalScannerElem);
    if (modalScanner) modalScanner.hide();
    showStatus("🟡 Cobro cancelado", "alert-secondary");
}


// --- MANEJO DEL PIN ESTILO .NET (MEMORIA PRIVADA) ---

let pinMemoria = "";

function pressPinKey(num) {
    if (pinMemoria.length < 4) {
        pinMemoria += num;
        actualizarDisplayPin();
    }
}

function deletePinKey() {
    pinMemoria = pinMemoria.slice(0, -1);
    actualizarDisplayPin();
}

function clearPin() {
    pinMemoria = "";
    actualizarDisplayPin();
}

function actualizarDisplayPin() {
    const display = document.getElementById("displayPin");
    if (!display) return;

    if (pinMemoria.length === 0) {
        display.innerText = "____";
    } else {
        display.innerText = "* ".repeat(pinMemoria.length).trim();
    }
}

// Capturar el teclado físico del PC si presiona números o 'Backspace'
document.addEventListener("keydown", (e) => {
    const modalPin = document.getElementById("modalPin");
    if (modalPin && modalPin.classList.contains("show")) {
        if (e.key >= "0" && e.key <= "9") {
            pressPinKey(e.key);
        } else if (e.key === "Backspace") {
            deletePinKey();
        } else if (e.key === "Enter") {
            confirmarPago();
        }
    }
});


// --- CONFIRMAR PAGO ---

async function confirmarPago() {
    const monto = parseInt(montoIngresado);

    if (pinMemoria.length < 4) {
        alert("Ingrese el PIN completo de 4 dígitos");
        return;
    }

    let data;
    try {
        const response = await fetch("../procesar_posnet.php?accion=cobrar", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                codigo_qr: qrEscaneadoActual,
                pin: pinMemoria,
                monto
            })
        });
        data = await response.json();
    } catch (error) {
        alert("No se pudo conectar con el servidor para procesar el cobro.");
        return;
    }

    if (!data.success) {
        alert(`❌ ${data.message || "Cobro rechazado."}`);
        clearPin();
    } else {
        const nombreAlumno = document.getElementById("modalAlumnoNombre").innerText;

        // Ocultar Modal de PIN
        const modalPinElem = document.getElementById("modalPin");
        const modalPin = bootstrap.Modal.getInstance(modalPinElem);
        if (modalPin) modalPin.hide();

        // Cargar datos en Modal de Éxito
        document.getElementById("exitoMonto").innerText = `$ ${monto.toLocaleString()}`;
        document.getElementById("exitoAlumno").innerText = nombreAlumno;

        // Resetear variables y métricas
        clearKeypad();
        clearPin();
        const codigoManual = document.getElementById("codigoManualPosnet");
        if (codigoManual) codigoManual.value = "";
        qrEscaneadoActual = null;
        await cargarMetricasPOSNET();

        // Mostrar Modal de Éxito
        setTimeout(() => {
            const modalExitoElem = document.getElementById("modalExito");
            const modalExito = new bootstrap.Modal(modalExitoElem);
            modalExito.show();
        }, 300);
    }
}

// Función para cerrar el modal de éxito
function cerrarExito() {
    const modalExitoElem = document.getElementById("modalExito");
    const modalExito = bootstrap.Modal.getInstance(modalExitoElem);
    if (modalExito) modalExito.hide();

    showStatus("🟡 Ingrese el monto a cobrar", "alert-secondary");
}

function cancelarPago() {
    clearPin();
    const modal = bootstrap.Modal.getInstance(document.getElementById("modalPin"));
    if (modal) modal.hide();
    showStatus("🟡 Cobro cancelado", "alert-secondary");
}

function showStatus(text, bgClass) {
    const box = document.getElementById("statusBox");
    if (box) {
        box.className = `status-bar alert ${bgClass} text-center fw-bold mb-3`;
        box.innerText = text;
    }
}