// js/theme.js

const btnDesktop = document.getElementById('theme-toggle-desktop');
const btnMobile = document.getElementById('theme-toggle-mobile');
const body = document.body;

// 1. Revisar preferencia guardada
const currentTheme = localStorage.getItem('theme');

// Función para actualizar iconos en AMBOS botones
function updateIcons(isDark) {
    const icon = isDark ? '☀️' : '🌙';
    if (btnDesktop) btnDesktop.textContent = icon;
    if (btnMobile) btnMobile.textContent = icon;
}

// 2. Aplicar tema al cargar
if (currentTheme === 'dark') {
    body.classList.add('dark-mode');
    updateIcons(true);
}

// 3. Función lógica de cambio
function switchTheme() {
    body.classList.toggle('dark-mode');
    
    const isDark = body.classList.contains('dark-mode');
    updateIcons(isDark); // Cambia el icono en los dos menús
    
    // Guardar en localStorage
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

// 4. Escuchar eventos (si los botones existen)
if (btnDesktop) btnDesktop.addEventListener('click', switchTheme);
if (btnMobile) btnMobile.addEventListener('click', switchTheme);