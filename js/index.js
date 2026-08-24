document.addEventListener("DOMContentLoaded", () => {
    console.log("--> JS Listo y conectado a Backend PHP.");

    const loginForm = document.getElementById("loginForm");
    const errorDiv = document.getElementById("errorMessage");
    const usernameInput = document.getElementById("username");
    const passInput = document.getElementById("password");

    const qrModal = document.getElementById("qrModal");
    const qrModalError = document.getElementById("qrModalError");
    const btnCancelQr = document.getElementById("btnCancelQr");
    const inputManualQr = document.getElementById("inputManualQr");
    const btnValidarManual = document.getElementById("btnValidarManual");

    let html5QrcodeScanner = null;

    usernameInput?.addEventListener("input", (e) => {
        if (e.target.value.trim().toLowerCase() === "consulta") {
            passInput.removeAttribute("required");
        } else {
            passInput.setAttribute("required", "true");
        }
    });

 loginForm.addEventListener("submit", async function (e) {
    e.preventDefault();
    ocultarError();

    const user = usernameInput?.value.trim();
    const pass = passInput?.value.trim();

    try {
        const response = await fetch("login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username: user, password: pass })
        });

        // Leemos la respuesta en texto primero
        const textResult = await response.text();
        console.log("--> Respuesta cruda de PHP:", textResult);

        // Intentamos parsear a JSON
        let result;
        try {
            result = JSON.parse(textResult);
        } catch (jsonErr) {
            console.error("El servidor no devolvió un JSON válido. Respuesta recibida:", textResult);
            mostrarError("Error interno del servidor en PHP (revisa la consola F12).", textResult);
            return;
        }

        if (!result.success) {
            mostrarError(result.message);
            return;
        }

        if (result.redirect) {
            window.location.href = result.redirect;
            return;
        }

        if (result.require_qr) {
            abrirModalQrMaestro();
        }

    } catch (err) {
        console.error("Error en petición fetch:", err);
        mostrarError("Ocurrió un error de red o de servidor.");
    }
});
    async function abrirModalQrMaestro() {
        qrModal.classList.remove("d-none");
        if (qrModalError) qrModalError.style.display = "none";
        if (inputManualQr) inputManualQr.value = "";

        setTimeout(async () => {
            try {
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5Qrcode("qrReader");
                }
                const config = { fps: 10, qrbox: { width: 200, height: 200 } };

                try {
                    await html5QrcodeScanner.start(
                        { facingMode: { exact: "environment" } },
                        config,
                        (decodedText) => procesarCodigoIngresado(decodedText),
                        () => {}
                    );
                } catch (e1) {
                    await html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        config,
                        (decodedText) => procesarCodigoIngresado(decodedText),
                        () => {}
                    );
                }
            } catch (err) {
                mostrarErrorModal("Cámara no disponible. Ingrese el código manualmente abajo.");
            }
        }, 300);
    }

    async function procesarCodigoIngresado(codigo) {
        try {
            const response = await fetch("validar_qr.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ qr_code: codigo })
            });

            const result = await response.json();

            if (result.success) {
                cerrarModalYApagarCamara(() => {
                    localStorage.setItem("usuarioBanco", JSON.stringify(result.user));
                    window.location.href = result.redirect;
                });
            } else {
                mostrarErrorModal(result.message);
            }
        } catch (err) {
            mostrarErrorModal("Error al conectar con el servidor para validar QR.");
        }
    }

    btnValidarManual?.addEventListener("click", () => {
        const val = inputManualQr.value.trim();
        if (!val) {
            mostrarErrorModal("Escriba un código para validar.");
            return;
        }
        procesarCodigoIngresado(val);
    });

    btnCancelQr?.addEventListener("click", () => cerrarModalYApagarCamara());

    async function cerrarModalYApagarCamara(callback) {
        qrModal.classList.add("d-none");
        if (html5QrcodeScanner) {
            try {
                if (html5QrcodeScanner.isScanning) {
                    await html5QrcodeScanner.stop();
                }
            } catch (err) {
                console.warn("Cámara ya detenida:", err);
            }
        }
        if (callback) callback();
    }

    function mostrarErrorModal(mensaje) {
        if (qrModalError) {
            qrModalError.innerText = mensaje;
            qrModalError.style.display = "block";
        }
    }

    function mostrarError(mensaje) {
        if (errorDiv) {
            errorDiv.innerText = mensaje;
            errorDiv.style.display = "block";
        }
    }

    function ocultarError() {
        if (errorDiv) {
            errorDiv.style.display = "none";
            errorDiv.innerText = "";
        }
    }
});