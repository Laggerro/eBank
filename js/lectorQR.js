// js/lectorQR.js
let html5QrCodeScanner = null;
let camaraEscaneando = false;

/**
 * Abre el modal de lectura e inicia la cámara / ingreso manual
 */
async function abrirLectorQR(onSuccessCallback) {
  // Limpieza defensiva si había una instancia previa
  if (html5QrCodeScanner) {
    await cerrarLectorQR();
  }

  // 1. Inyectar HTML del Modal si no existe en el DOM
  let modal = document.getElementById("modalScannerQR");
  if (!modal) {
    const modalHTML = `
      <div id="modalScannerQR" class="modal-qr-overlay">
        <div class="modal-qr-card" style="pointer-events: auto;">
          
          <div class="modal-qr-header">
            <span style="font-weight: bold; font-family: sans-serif;">📷 Escanear Código QR</span>
            <button type="button" id="btnCerrarXQR" style="background: transparent; border: none; color: #ffc107; font-size: 1.5rem; cursor: pointer; padding: 4px 8px; z-index: 1000000;">✕</button>
          </div>
          
          <div class="modal-qr-body">
            <div id="qr-reader-container"></div>
            <div class="qr-scanner-guide" style="pointer-events: none;"></div>
          </div>

          <div style="background-color: #1e1e1e; border-top: 1px solid #333; padding: 12px; box-sizing: border-box; position: relative; z-index: 1000000;">
            <label style="color: #adb5bd; font-size: 0.75rem; display: block; margin-bottom: 6px; font-family: sans-serif;">¿No funciona la cámara? Ingrese el código / DNI:</label>
            <div style="display: flex; gap: 6px; margin-bottom: 8px;">
              <input type="text" id="txtManualQR" style="flex: 1; background-color: #2b2b2b; color: #fff; border: 1px solid #444; padding: 6px 10px; border-radius: 4px; font-size: 0.85rem; outline: none;" placeholder="Ej: 46342761" autocomplete="off">
              <button type="button" id="btnAceptarManualQR" style="background-color: #ffc107; color: #000; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">Ingresar</button>
            </div>
            <button type="button" id="btnCancelarQR" style="width: 100%; background: transparent; color: #ced4da; border: 1px solid #6c757d; padding: 6px; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">Cancelar</button>
          </div>

        </div>
      </div>
    `;
    document.body.insertAdjacentHTML("beforeend", modalHTML);
    modal = document.getElementById("modalScannerQR");
  }

  // Mostrar modal con alta prioridad
  modal.style.setProperty("display", "flex", "important");

  // 2. Vinculación directa de eventos de cierre
  const btnX = document.getElementById("btnCerrarXQR");
  const btnCancelar = document.getElementById("btnCancelarQR");

  if (btnX) btnX.onclick = (e) => { e.preventDefault(); cerrarLectorQR(); };
  if (btnCancelar) btnCancelar.onclick = (e) => { e.preventDefault(); cerrarLectorQR(); };

  const inputManual = document.getElementById("txtManualQR");
  if (inputManual) {
    inputManual.value = "";
    setTimeout(() => inputManual.focus(), 150);
  }

  // 3. Procesar resultados de lectura manual o QR
  const procesarResultado = async (texto) => {
    const valorLimpio = String(texto || "").trim();
    if (!valorLimpio) return;
    
    await cerrarLectorQR();
    if (typeof onSuccessCallback === "function") {
      onSuccessCallback(valorLimpio);
    }
  };

  const btnManual = document.getElementById("btnAceptarManualQR");
  if (btnManual) {
    btnManual.onclick = () => procesarResultado(inputManual.value);
  }

  if (inputManual) {
    inputManual.onkeyup = (e) => {
      if (e.key === "Enter") {
        procesarResultado(inputManual.value);
      }
    };
  }

  // 4. Inicialización de la Cámara Html5Qrcode
  try {
    const container = document.getElementById("qr-reader-container");
    if (container) container.innerHTML = "";

    html5QrCodeScanner = new Html5Qrcode("qr-reader-container");
    const config = { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 };

    await html5QrCodeScanner.start(
      { facingMode: "environment" },
      config,
      (decodedText) => procesarResultado(decodedText),
      () => {}
    );
    camaraEscaneando = true;
  } catch (err) {
    camaraEscaneando = false;
    console.warn("Cámara no disponible. Puede usar el modo manual.");
  }
}

/**
 * Oculta el modal de inmediato y apaga la cámara en segundo plano
 */
async function cerrarLectorQR() {
  const modal = document.getElementById("modalScannerQR");
  
  // Ocultar modal primero para respuesta visual instantánea
  if (modal) {
    modal.style.setProperty("display", "none", "important");
  }

  // Detener hardware en segundo plano
  if (html5QrCodeScanner) {
    const scannerRef = html5QrCodeScanner;
    const estabaEscaneando = camaraEscaneando;
    
    html5QrCodeScanner = null;
    camaraEscaneando = false;

    if (estabaEscaneando) {
      try {
        await scannerRef.stop();
      } catch (e) {
        console.warn("Aviso al detener cámara:", e);
      }
    }
    try {
      scannerRef.clear();
    } catch (e) {}
  }
}