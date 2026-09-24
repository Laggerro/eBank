document.addEventListener("DOMContentLoaded", () => {
    console.log("--> JS Listo y conectado a Backend PHP.");

    const loginForm = document.getElementById("loginForm");
    const errorDiv = document.getElementById("errorMessage");
    const usernameInput = document.getElementById("username");
    const passInput = document.getElementById("password");
    const forgotPasswordBtn = document.getElementById("btnForgotPassword");

    usernameInput?.addEventListener("input", (e) => {
        if (e.target.value.trim().toLowerCase() === "consulta") {
            passInput.removeAttribute("required");
        } else {
            passInput.setAttribute("required", "true");
        }
    });

    forgotPasswordBtn?.addEventListener("click", async () => {
        const email = usernameInput?.value.trim();

        if (!email || !email.includes("@")) {
            mostrarError("Ingresá tu email para recuperar la contraseña.");
            usernameInput?.focus();
            return;
        }

        ocultarError();

        try {
            const response = await fetch("login.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "forgot_password", username: email })
            });

            const rawText = await response.text();
            let result = null;

            try {
                result = rawText ? JSON.parse(rawText) : null;
            } catch (jsonErr) {
                console.error("Respuesta no JSON de recuperación:", rawText);
                mostrarError("No se pudo completar la recuperación de contraseña. Verificá que la app esté corriendo con PHP/Apache y no desde un servidor estático.");
                return;
            }

            if (!result || !result.success) {
                mostrarError((result && result.message) || "No se pudo enviar el correo de recuperación.");
                return;
            }

            mostrarExito(result.message || "Si el email está registrado, recibirá un enlace para recuperar la contraseña.");
        } catch (err) {
            console.error("Error al solicitar recuperación:", err);
            mostrarError("No se pudo completar la recuperación de contraseña.");
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
            errorDiv.style.color = "#ffb3b3";
            errorDiv.style.borderColor = "rgba(255, 107, 107, 0.7)";
        }
    }

    function mostrarExito(mensaje) {
        if (errorDiv) {
            errorDiv.innerText = mensaje;
            errorDiv.style.display = "block";
            errorDiv.style.color = "#8ef0a3";
            errorDiv.style.borderColor = "rgba(110, 231, 183, 0.7)";
        }
    }

    function ocultarError() {
        if (errorDiv) {
            errorDiv.style.display = "none";
            errorDiv.innerText = "";
        }
    }
});