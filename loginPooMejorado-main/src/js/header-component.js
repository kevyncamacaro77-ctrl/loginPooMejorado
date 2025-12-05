document.addEventListener("DOMContentLoaded", function () {
  const nav = document.getElementById("navigation-bar");
  const mobileMenuBtn = document.getElementById("mobile-menu-btn");
  const mobileMenu = document.getElementById("mobile-menu");
  
  const menuMobileLists = document.querySelectorAll("#mobile-menu > ul > li");
  
  let isActive = false;
  let isAnimating = false; // 🔒 NUEVA VARIABLE DE BLOQUEO

  // Eventos de Scroll (Se mantienen igual)
  window.addEventListener("scroll", () => (mobileMenuBtn.style.opacity = ".5"));
  window.addEventListener("scrollend", () => {
    const currentScroll = window.scrollY || document.documentElement.scrollTop;
    const atBottom = window.innerHeight + currentScroll >= document.body.scrollHeight;
    atBottom ? (mobileMenuBtn.style.opacity = ".5") : (mobileMenuBtn.style.opacity = ".7");
  });

  // CLICK DE LOS ÍTEMS DEL MENÚ MÓVIL
  menuMobileLists.forEach(item => {
    if (item.classList.contains("mobile-cart-trigger")) {
        return; 
    }

    item.addEventListener("click", function () {
      // Si ya se está animando, ignoramos el click
      if (isAnimating) return; 
      
      cerrarMenuConAnimacion();
      isActive = false;
    });
  });

  // CLICK DEL BOTÓN HAMBURGUESA
  mobileMenuBtn.addEventListener("click", function () {
    // 🔒 SI SE ESTÁ MOVIENDO, NO HACEMOS NADA
    if (isAnimating) return;

    if (!isActive) {
      configurarMenu("✖", "flex", "0");
      isActive = true;
      return;
    }
    cerrarMenuConAnimacion();
    isActive = false;
  });

  // REDIMENSIONAR PANTALLA
  window.addEventListener("resize", function () {
    if (isActive) {
      // Forzamos el cierre sin animación para evitar bugs visuales al rotar pantalla
      mobileMenu.style.display = "none";
      mobileMenu.style.right = "100%";
      mobileMenuBtn.innerHTML = "☰";
      isActive = false;
      isAnimating = false; // Liberamos el bloqueo por si acaso
    }
  });

  // FUNCIONES AUXILIARES

  function configurarMenu(btnText, menuDisplay, menuRight) {
    isAnimating = true; // 🔒 BLOQUEAMOS CLICKS
    
    mobileMenuBtn.innerHTML = btnText;
    mobileMenu.style.display = menuDisplay;
    
    requestAnimationFrame(() => {
      mobileMenu.style.right = menuRight;
    });

    // Detectar cuando termina de ABRIRSE para desbloquear
    const onOpenEnd = () => {
        isAnimating = false; // 🔓 DESBLOQUEAMOS
        mobileMenu.removeEventListener("transitionend", onOpenEnd);
    };
    mobileMenu.addEventListener("transitionend", onOpenEnd);
  }
  
  function cerrarMenuConAnimacion() {
    isAnimating = true; // 🔒 BLOQUEAMOS CLICKS

    mobileMenuBtn.innerHTML = "☰";
    mobileMenu.style.right = "100%";

    const handleTransitionEnd = () => {
      mobileMenu.style.display = "none";
      isAnimating = false; // 🔓 DESBLOQUEAMOS CLICKS
      mobileMenu.removeEventListener("transitionend", handleTransitionEnd);
    };
    mobileMenu.addEventListener("transitionend", handleTransitionEnd);
  }
});