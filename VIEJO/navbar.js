// js/navbar.js
document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById('navbar-container');
    if (!container) return;

    // 1. Cargar el HTML de la navbar
    const resHtml = await fetch('navbar.html');
    container.innerHTML = await resHtml.text();

    // 2. Pedir la sesión a PHP para rellenar datos y permisos
    try {
        const resSesion = await fetch('obtener_sesion.php');
        const data = await resSesion.json();

        if (data.activo) {
            const u = data.usuario;
            document.getElementById('nav-nombre-usr').innerText = u.nombre || u.usuario;
            document.getElementById('nav-rol-usr').innerText = u.rol;

            // Si es Admin, mostramos los enlaces de Admin
            if ((u.rol || '').toUpperCase() === 'ADMIN') {
                document.getElementById('nav-admin-links').style.display = 'flex';
            }
        } else {
            window.location.href = 'index.html';
        }
    } catch (e) {
        console.error("Error al cargar sesión en navbar:", e);
    }

    // 3. Evento Salir
    document.getElementById('btnCerrarSesion')?.addEventListener('click', async () => {
        await fetch('logout.php');
        sessionStorage.clear();
        localStorage.clear();
        window.location.href = 'index.html';
    });
});