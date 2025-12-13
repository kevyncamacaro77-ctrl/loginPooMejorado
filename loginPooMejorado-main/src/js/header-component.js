document.addEventListener("DOMContentLoaded", function () {
  const mobileMenuBtn = document.getElementById("mobile-menu-btn");
  const mobileMenu = document.getElementById("mobile-menu");
  
  // Seleccionamos los items del menú
  const menuMobileLists = document.querySelectorAll("#mobile-menu > ul > li");
  
  let isActive = false;
  let isAnimating = false; // Semáforo para evitar clicks múltiples

  // --- 1. EVENTOS DE LOS ÍTEMS DEL MENÚ ---
  menuMobileLists.forEach(item => {
    // Ignorar el click si es el botón del carrito (para que no cierre el menú si quieres usar el dropdown)
    if (item.classList.contains("mobile-cart-trigger")) {
        return; 
    }

    item.addEventListener("click", function () {
      if (isActive && !isAnimating) {
        cerrarMenu();
      }
    });
  });

  // --- 2. EVENTO DEL BOTÓN HAMBURGUESA ---
  if (mobileMenuBtn) {
      mobileMenuBtn.addEventListener("click", function () {
        if (isAnimating) return; // Si se está moviendo, ignorar click

        if (!isActive) {
          abrirMenu();
        } else {
          cerrarMenu();
        }
      });
  }

  // --- 3. FUNCIONES DE APERTURA/CIERRE SEGURAS ---

  function abrirMenu() {
    isAnimating = true;
    mobileMenuBtn.innerHTML = "✖";
    mobileMenu.style.display = "flex";
    
    // Forzamos al navegador a reconocer el cambio de display antes de animar
    void mobileMenu.offsetWidth; 

    requestAnimationFrame(() => {
      mobileMenu.style.right = "0";
    });

    // Esperar a que termine la transición O forzar desbloqueo en 400ms
    esperarFinAnimacion(() => {
        isActive = true;
        isAnimating = false;
    });
  }
  
  function cerrarMenu() {
    isAnimating = true;
    mobileMenuBtn.innerHTML = "☰";
    mobileMenu.style.right = "100%";

    // Esperar a que termine la transición O forzar desbloqueo en 400ms
    esperarFinAnimacion(() => {
        mobileMenu.style.display = "none";
        isActive = false;
        isAnimating = false;
    });
  }

  // Función auxiliar para manejar la transición con seguridad
  function esperarFinAnimacion(callback) {
    let finished = false;

    const onTransitionEnd = () => {
      if (finished) return;
      finished = true;
      mobileMenu.removeEventListener("transitionend", onTransitionEnd);
      callback();
    };

    // 1. Escuchar el evento real del navegador
    mobileMenu.addEventListener("transitionend", onTransitionEnd);

    // 2. TEMPORIZADOR DE SEGURIDAD (Backup)
    // Si por alguna razón 'transitionend' no se dispara en 400ms, ejecutamos igual.
    setTimeout(() => {
        if (!finished) {
            onTransitionEnd();
        }
    }, 400); // 400ms es un poco más que los 0.3s típicos de transición CSS
  }

  // --- 4. REDIMENSIONAR PANTALLA ---
  window.addEventListener("resize", function () {
    if (window.innerWidth > 768 && isActive) {
      // Si agrandan la pantalla, resetear todo sin animación
      mobileMenu.style.display = "none";
      mobileMenu.style.right = "100%";
      mobileMenuBtn.innerHTML = "☰";
      isActive = false;
      isAnimating = false;
    }
  });
});