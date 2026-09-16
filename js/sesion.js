(function mantenerSesionActiva() {
    const intervalo = 5 * 60 * 1000;

    setInterval(async () => {
        try {
            const response = await fetch('../mantener_sesion.php', {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (response.status === 401) {
                console.warn('La sesión del banco expiró.');
            }
        } catch (error) {
            console.warn('No se pudo renovar la sesión:', error);
        }
    }, intervalo);
})();