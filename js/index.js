document.addEventListener("DOMContentLoaded", () => {
    console.log("--> JS Listo y conectado a Backend PHP.");

    const loginForm = document.getElementById("loginForm");
    const errorDiv = document.getElementById("errorMessage");
    const usernameInput = document.getElementById("username");
    const passInput = document.getElementById("password");

    usernameInput?.addEventListener("input", (e) => {
        if (e.target.value.trim().toLowerCase() === "consulta") {
            passInput.removeAttribute("required");
        } else {
            passInput.setAttribute("required", "true");
        }
    });

    loginForm?.addEventListener("submit", async function (e) {
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

            const textResult = await response.text();
            console.log("--> Respuesta cruda de PHP:", textResult);

            let result;
            try {
                result = JSON.parse(textResult);
            } catch (jsonErr) {
                console.error("El servidor no devolvió un JSON válido. Respuesta recibida:", textResult);
                mostrarError("Error interno del servidor en PHP (revisa la consola F12).");
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
                // Abrimos el lector unificado compartiendo el callback de validación
                abrirLectorQR(procesarCodigoIngresado);
            }

        } catch (err) {
            console.error("Error en petición fetch:", err);
            mostrarError("Ocurrió un error de red o de servidor.");
        }
    });

    async function procesarCodigoIngresado(codigo) {
        try {
            const response = await fetch("validar_qr.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ qr_code: codigo })
            });

            const result = await response.json();

            if (result.success) {
                localStorage.setItem("usuarioBanco", JSON.stringify(result.user));
                window.location.href = result.redirect;
            } else {
                alert(result.message || "Código QR no válido.");
                // Reabrir si falla la validación
                abrirLectorQR(procesarCodigoIngresado);
            }
        } catch (err) {
            console.error("Error al validar QR:", err);
            alert("Error al conectar con el servidor para validar QR.");
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