document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formResetPassword');
    const messageEl = document.getElementById('resetMessage');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    const params = new URLSearchParams((window.location.hash || '').replace(/^#/, ''));
    const query = new URLSearchParams(window.location.search);

    const accessToken = params.get('access_token') || query.get('access_token') || '';
    const refreshToken = params.get('refresh_token') || query.get('refresh_token') || '';

    if (!accessToken) {
        showMessage('El enlace de restablecimiento no es válido o expiró. Pedí uno nuevo desde el login.', 'error');
        form?.querySelector('button')?.setAttribute('disabled', 'disabled');
        return;
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const newPassword = newPasswordInput.value.trim();
        const confirmPassword = confirmPasswordInput.value.trim();

        if (newPassword.length < 6) {
            showMessage('La contraseña debe tener al menos 6 caracteres.', 'error');
            newPasswordInput.focus();
            return;
        }

        if (newPassword !== confirmPassword) {
            showMessage('Las contraseñas no coinciden.', 'error');
            confirmPasswordInput.focus();
            return;
        }

        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reset_password',
                    new_password: newPassword,
                    access_token: accessToken,
                    refresh_token: refreshToken
                })
            });

            const rawText = await response.text();
            let result = null;

            try {
                result = rawText ? JSON.parse(rawText) : null;
            } catch (jsonErr) {
                console.error('Respuesta no JSON al resetear contraseña:', rawText);
                showMessage('No se pudo restablecer la contraseña. Verificá que la app esté corriendo con PHP/Apache y no desde un servidor estático.', 'error');
                return;
            }

            if (!result || !result.success) {
                showMessage((result && result.message) || 'No se pudo restablecer la contraseña.', 'error');
                return;
            }

            showMessage(result.message || 'Contraseña restablecida correctamente.', 'success');
            form.reset();
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1800);
        } catch (error) {
            console.error('Error al restablecer contraseña:', error);
            showMessage('Ocurrió un error al guardar la nueva contraseña.', 'error');
        }
    });

    function showMessage(message, type) {
        if (!messageEl) return;
        messageEl.textContent = message;
        messageEl.className = 'message ' + (type === 'success' ? 'success' : 'error');
    }
});
