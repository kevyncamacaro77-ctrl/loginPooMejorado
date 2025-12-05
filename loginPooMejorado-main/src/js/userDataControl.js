document.addEventListener("DOMContentLoaded", function () {
  
  // A. LÓGICA DE INICIALIZACIÓN (Prioridades)
  const urlParams = new URLSearchParams(window.location.search);
  const urlTab = urlParams.get("tab");

  // 1. Prioridad: ¿La URL me obliga a ir a una pestaña? (ej: userdata.php?tab=cart)
  if (urlTab && document.getElementById("section-" + urlTab)) {
    showTab(urlTab);
  } 
  // 2. Prioridad: Si no hay URL, ¿Tengo algo guardado en memoria?
  else {
    const lastTab = localStorage.getItem("activeTab");
    if (lastTab && document.getElementById("section-" + lastTab)) {
      showTab(lastTab);
    } else {
      // 3. Prioridad: Por defecto mostrar perfil
      showTab("profile");
    }
  }

  // B. MANEJO DEL SIDEBAR (MÓVIL)
  const toggleBtn = document.getElementById("sidebar-toggle");
  if (toggleBtn) {
    toggleBtn.addEventListener("click", function () {
      const sidebar = document.getElementById("sidebar");
      if (sidebar) {
        sidebar.classList.toggle("active");
      }
    });
  }

  // C. MANEJO DE CLICS EN ENLACES DEL MENÚ
  const tabLinks = document.querySelectorAll(".tab-link");
  tabLinks.forEach(function (link) {
    link.addEventListener("click", function (e) {
      e.preventDefault(); // Evita recarga
      const tabName = this.getAttribute("data-tab");
      showTab(tabName);
    });
  });

  // (AQUÍ HABÍA CÓDIGO DUPLICADO QUE ELIMINÉ)
});

// Función auxiliar
function showTab(tabName) {
  const sections = document.querySelectorAll(".tab-section");
  sections.forEach((el) => el.classList.remove("active"));

  const links = document.querySelectorAll(".sidebar nav ul li a");
  links.forEach((el) => el.classList.remove("active"));

  const targetSection = document.getElementById("section-" + tabName);
  const targetLink = document.querySelector(`.tab-link[data-tab="${tabName}"]`);

  if (targetSection) targetSection.classList.add("active");
  if (targetLink) targetLink.classList.add("active");

  localStorage.setItem("activeTab", tabName);

  if (window.innerWidth <= 900) {
    const sidebar = document.getElementById("sidebar");
    if (sidebar && sidebar.classList.contains("active")) {
      sidebar.classList.remove("active");
    }
  }
}