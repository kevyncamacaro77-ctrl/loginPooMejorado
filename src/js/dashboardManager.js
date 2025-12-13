document.addEventListener('DOMContentLoaded', function() {
    
    // Seleccionar todos los botones/enlaces que deben desplegar el carrito en móvil
    const mobileCartTriggers = document.querySelectorAll('.mobile-cart-trigger .cart-link-main');

    mobileCartTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            // Solo aplica si estamos en vista móvil (menor a 768px)
            if (window.innerWidth <= 768) {
                e.preventDefault(); // Evita ir a la página

                // Busca el contenedor padre y le pone la clase 'active-cart'
                const container = this.closest('.cart-icon-container');
                if (container) {
                    container.classList.toggle('active-cart');
                }
            }
        });
    });
});