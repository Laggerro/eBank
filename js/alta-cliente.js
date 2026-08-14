// /ebank/js/alta-cliente.js

// Variables globales para captura de foto y modal/cámara QR
let fotoBlobCapturada = null;
let streamWebcam = null;
let html5QrCode = null;
let destinoQr = "FORM"; // 'FORM' para el campo QR del formulario, 'TABLA' para el buscador
let modalCamaraBs = null;
let modalQrBs = null;
let listaClientesCache = [];

document.addEventListener("DOMContentLoaded", async () => {
    // 1. Inicializar Modales Bootstrap de forma segura
    const elModalCamara = document.getElementById("modalCamara");
    if (elModalCamara) {
        modalCamaraBs = new bootstrap.Modal(elModalCamara);
        elModalCamara.addEventListener("hidden.bs.modal", detenerWebcam);
    }

    const elModalQr = document.getElementById("modalLectorQR");
    if (elModalQr) {
        modalQrBs = new bootstrap.Modal(elModalQr);
        elModalQr.addEventListener("hidden.bs.modal", detenerEscanerQR);
    }

    // 2. Vincular Eventos de Botones de Foto y QR
    document.getElementById("btnAbrirCamara")?.addEventListener("click", abrirWebcam);
    document.getElementById("btnCapturar")?.addEventListener("click", tomarFotoWebcam);
    document.getElementById("btnEscanearQR")?.addEventListener("click", () => iniciarEscanerQR("FORM"));
    document.getElementById("btnEscanearQRTabla")?.addEventListener("click", () => iniciarEscanerQR("TABLA"));

    // 3. Vincular Buscador en Tiempo Real
    document.getElementById("txtBuscarTabla")?.addEventListener("keyup", filtrarTabla);

    // 4. Vincular Formulario Submit y Cancelar
    const form = document.getElementById("formAltaCliente");
    if (form) {
        form.addEventListener("submit", guardarCliente);
    } else {
        console.error("No se encontró el formulario #formAltaCliente");
    }

    document.getElementById("btnCancelarEdicion")?.addEventListener("click", resetFormulario);

    // 5. Cargar datos iniciales (Cursos y Tabla de Clientes)
    await cargarComboCursos();
    await cargarTablaClientes();
});

// ==================== CÁMARA WEBCAM (FOTO DE PERFIL) ====================
async function abrirWebcam() {
    const video = document.getElementById("webcam");
    if (!video || !modalCamaraBs) return;

    try {
        streamWebcam = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: "user" },
            audio: false,
        });
        video.srcObject = streamWebcam;
        modalCamaraBs.show();
    } catch (err) {
        console.error("Error al acceder a la webcam:", err);
        alert("No se pudo acceder a la cámara web. Verifique los permisos.");
    }
}

function tomarFotoWebcam() {
    const video = document.getElementById("webcam");
    const canvas = document.getElementById("canvasFoto");
    const imgPreview = document.getElementById("imgPreview");
    if (!video || !canvas || !imgPreview) return;

    const ctx = canvas.getContext("2d");
    canvas.width = video.videoWidth || 300;
    canvas.height = video.videoHeight || 300;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(
        (blob) => {
            fotoBlobCapturada = blob;
            imgPreview.src = URL.createObjectURL(blob);
            modalCamaraBs.hide();
        },
        "image/jpeg",
        0.85
    );
}

function detenerWebcam() {
    if (streamWebcam) {
        streamWebcam.getTracks().forEach((track) => track.stop());
        streamWebcam = null;
    }
}

// ==================== ESCÁNER QR (MODAL REUTILIZABLE) ====================
function iniciarEscanerQR(destino) {
    destinoQr = destino;
    if (!modalQrBs) return;
    modalQrBs.show();

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }

    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    html5QrCode
        .start({ facingMode: "environment" }, config, (decodedText) => {
            if (destinoQr === "FORM") {
                const txtQr = document.getElementById("txtCodigoQr");
                if (txtQr) txtQr.value = decodedText;
            } else if (destinoQr === "TABLA") {
                const txtBuscar = document.getElementById("txtBuscarTabla");
                if (txtBuscar) {
                    txtBuscar.value = decodedText;
                    filtrarTabla();
                }
            }
            modalQrBs.hide();
        })
        .catch((err) => {
            console.error("Error al iniciar lector QR:", err);
        });
}

function detenerEscanerQR() {
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode
            .stop()
            .then(() => html5QrCode.clear())
            .catch((err) => console.error(err));
    }
}

// ==================== FUNCIONES BASE Y CARGA DE DATOS ====================
function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

async function cargarComboCursos() {
    const selectCurso = document.getElementById("txtCurso");
    if (!selectCurso) return;

    try {
        const res = await fetch("../obtener_cursos.php");
        const data = await res.json();
        if (data.success && Array.isArray(data.cursos) && data.cursos.length > 0) {
            selectCurso.innerHTML =
                '<option value="">-- Seleccionar Sector / Curso --</option>' +
                data.cursos
                    .map((c) => `<option value="${c.nombre}">${c.nombre}</option>`)
                    .join("");
        } else {
            selectCurso.innerHTML = '<option value="">Sin sectores/cursos cargados</option>';
        }
    } catch (e) {
        console.error("Error al cargar cursos:", e);
        selectCurso.innerHTML = '<option value="">Error al obtener cursos</option>';
    }
}

async function cargarTablaClientes() {
    const tbody = document.getElementById("tblClientes");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Cargando alumnos...</td></tr>`;

    try {
        const res = await fetch("../obtener_clientes.php");
        const data = await res.json();
        
        if (data.success && Array.isArray(data.alumnos)) {
            // Vaciamos y reasignamos el array en memoria
            listaClientesCache = [...data.alumnos];
            renderizarTabla(listaClientesCache);
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">${data.message || "No hay clientes registrados."}</td></tr>`;
        }
    } catch (err) {
        console.error("Error al cargar clientes:", err);
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Error de conexión al cargar la lista.</td></tr>`;
    }
}


function renderizarTabla(clientes) {
    const tbody = document.getElementById("tblClientes");
    if (!tbody) return;

    if (clientes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron clientes.</td></tr>`;
        return;
    }

    tbody.innerHTML = clientes
        .map((c) => {
            const nombreCompleto =
                c.nombre_apellido ||
                `${c.nombre || ""} ${c.apellido || ""}`.trim() ||
                "Sin Nombre";
            const qrCodigo = c.codigo_qr || c.qr_code || "Sin QR";

            // OJO AQUÍ: usá '${c.id}' con comillas simples alrededor para asegurar que pase como string
            return `
            <tr>
                <td><img src="${c.foto_url || "../img/default-avatar.png"}" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;"></td>
                <td class="fw-bold">${nombreCompleto}</td>
                <td>${c.dni}</td>
                <td><span class="badge bg-secondary">${c.curso || "Sin Sector"}</span></td>
                <td><small class="text-muted">${qrCodigo}</small></td>
                <td class="text-center">
                    <button type="button" onclick="editarCliente('${c.id}')" class="btn btn-sm btn-outline-primary fw-bold"> ✏️ Editar</button>
                </td>
            </tr>`;
        })
        .join("");
}

function filtrarTabla() {
    const q = (document.getElementById("txtBuscarTabla")?.value || "")
        .toLowerCase()
        .trim();

    const filtrados = listaClientesCache.filter((c) => {
        const nom = (c.nombre_apellido || "").toLowerCase();
        const dni = String(c.dni || "").toLowerCase();
        const qr = String(c.codigo_qr || "").toLowerCase();
        return nom.includes(q) || dni.includes(q) || qr.includes(q);
    });

    renderizarTabla(filtrados);
}

// ==================== EDICIÓN Y GUARDADO (POST / PATCH) ====================
// ✅ CÓDIGO CORRECTO (Compara como String/UUID):
function editarCliente(id) {
    console.log("Editando ID:", id);
    
    // Buscamos directamente en el array recién obtenido
    const cliente = listaClientesCache.find((c) => String(c.id) === String(id));
    if (!cliente) {
        alert("Error: No se encontraron los datos del cliente seleccionado.");
        return;
    }

    // Guardamos la clave primaria UUID en el hidden
    document.getElementById("clienteId").value = cliente.id;
    
    // Rellenamos el resto de los campos
    document.getElementById("txtDni").value = cliente.dni || "";
    document.getElementById("txtNombre").value = cliente.nombre_apellido || "";
    document.getElementById("txtCurso").value = cliente.curso || "";
    document.getElementById("txtCodigoQr").value = cliente.codigo_qr || "";
    document.getElementById("txtPin").value = ""; // PIN vacío por seguridad al editar

    document.getElementById("lblTituloForm").innerText = " ✏️ Editar Cliente";
    document.getElementById("btnGuardar").innerText = "Actualizar Cliente";
    document.getElementById("btnCancelarEdicion").classList.remove("d-none");

    if (cliente.foto_url) {
        document.getElementById("imgPreview").src = cliente.foto_url;
    } else {
        document.getElementById("imgPreview").src = "../img/default-avatar.png";
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
}

async function guardarCliente(e) {
    e.preventDefault();
    console.log("Enviando formulario...");

    const btn = document.getElementById("btnGuardar");
    btn.disabled = true;

    // ⚠️ CAPTURAR LOS VALORES DIRECTAMENTE DE LOS INPUTS EN ESTE PRECISO MOMENTO
    const clienteId = document.getElementById("clienteId").value; // ID de la BD
    const dniInput = document.getElementById("txtDni").value.trim();
    const nombreInput = document.getElementById("txtNombre").value.trim();
    const cursoInput = document.getElementById("txtCurso").value;
    const pinInput = document.getElementById("txtPin").value.trim();
    const qrInput = document.getElementById("txtCodigoQr").value.trim();

    // Armamos el objeto PAYLOAD justo con lo que el usuario acaba de escribir
    const payload = {
        es_edicion: !!clienteId,
        id: clienteId || null,
        dni: dniInput,
        nombre_apellido: nombreInput,
        curso: cursoInput,
        codigo_qr: qrInput || null
    };

    // Si escribieron un nuevo PIN, lo adjuntamos
    if (pinInput !== "") {
        payload.pin = pinInput;
    }

    // Si capturaron foto nueva, la enviamos
    if (fotoBlobCapturada) {
        payload.foto_base64 = await blobToBase64(fotoBlobCapturada);
    }

    console.log("Payload que se envía a guardar_cliente.php:", payload);

    try {
        const res = await fetch("../guardar_cliente.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const respData = await res.json();
        console.log("Respuesta del servidor:", respData);

        if (respData.success) {
            alert(clienteId ? "¡Cliente actualizado con éxito!" : "¡Cliente registrado con éxito!");
            resetFormulario();
            await cargarTablaClientes(); // Recarga la lista desde la BD
        } else {
            alert("Error: " + (respData.message || "No se pudo guardar"));
        }
    } catch (err) {
        console.error("Error en Fetch:", err);
        alert("Error al conectar con el servidor.");
    } finally {
        btn.disabled = false;
    }
}

function mostrarError(msg) {
    const msgDiv = document.getElementById("msgAlta");
    if (msgDiv) {
        msgDiv.innerText = msg;
        msgDiv.classList.remove("d-none");
    } else {
        alert(msg);
    }
}

function resetFormulario() {
    document.getElementById("formAltaCliente").reset();
    document.getElementById("clienteId").value = "";
    document.getElementById("txtDni").readOnly = false;
    fotoBlobCapturada = null;
    document.getElementById("imgPreview").src = "../img/default-avatar.png";
    document.getElementById("lblTituloForm").innerText = " 👤 Registrar Nuevo Cliente";
    document.getElementById("btnGuardar").innerText = "Registrar Cliente";
    document.getElementById("btnCancelarEdicion").classList.add("d-none");
    document.getElementById("helpPin")?.classList.add("d-none");

    const msgDiv = document.getElementById("msgAlta");
    if (msgDiv) msgDiv.classList.add("d-none");
}