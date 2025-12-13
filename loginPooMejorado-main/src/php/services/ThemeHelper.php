<?php
// src/php/services/ThemeHelper.php
require_once __DIR__ . '/../models/SettingsModel.php';

class ThemeHelper
{
    public static function renderThemeStyles($connection)
    {
        $model = new SettingsModel($connection);
        $colors = $model->getThemeColors();
        
        $primary = $colors['primary_color'] ?? '#007bff';
        $secondary = $colors['secondary_color'] ?? '#2c3e50';
        $text = $colors['text_color'] ?? '#333333';
        $bg = $colors['bg_color'] ?? '#f8f9fa';
        $card = $colors['card_color'] ?? '#ffffff';

        echo "<style>
            /* 1. VARIABLES DE COLOR (SOLO EN MODO CLARO)
               Al definirlas dentro de 'body:not(.dark-mode)', estas variables personalizadas
               DESAPARECEN cuando activas el modo oscuro. Así, el sistema vuelve a leer
               tus variables originales de 'globalVariables.css' (Naranja/Azul).
            */
            body:not(.dark-mode) {
                --primary-color: {$primary};
                --secondary-color: {$secondary};
                --text-color: {$text};
                --bg-color: {$bg};
                --card-color: {$card};
                
                /* Aplicar fondo y texto base solo en modo claro */
                background-color: var(--bg-color) !important;
                color: var(--text-color) !important;
            }

            /* 2. REGLAS ESPECÍFICAS (SOLO EN MODO CLARO)
               Todas estas reglas se apagan automáticamente si existe la clase .dark-mode
            */

            /* Sidebar */
            body:not(.dark-mode) .sidebar {
                background-color: var(--secondary-color) !important;
            }
            body:not(.dark-mode) .sidebar h3 {
                color: #fff !important;
            }

            /* Botones */
            body:not(.dark-mode) .btn-primary,
            body:not(.dark-mode) .btn-save,
            body:not(.dark-mode) .submit-btn,
            body:not(.dark-mode) .btn-action.btn-primary,
            body:not(.dark-mode) .admin-btn {
                background-color: var(--primary-color) !important;
                border-color: var(--primary-color) !important;
                color: #fff !important;
            }

            /* Títulos */
            body:not(.dark-mode) h1,
            body:not(.dark-mode) h2,
            body:not(.dark-mode) h3,
            body:not(.dark-mode) h4,
            body:not(.dark-mode) .productos-title,
            body:not(.dark-mode) .faq-title,
            body:not(.dark-mode) .container-form__title,
            body:not(.dark-mode) #testimonio-pepon {
                color: var(--secondary-color) !important;
            }

            /* Footer */
            body:not(.dark-mode) footer {
                background-color: var(--secondary-color) !important;
                color: var(--primary-color) !important;
            }

            /* 3. CONTENEDORES / TARJETAS (SOLO EN MODO CLARO)
               Aquí es donde se aplica el color de los contenedores que pediste.
            */
            body:not(.dark-mode) #productos figure,
            body:not(.dark-mode) .testimonial-card,
            body:not(.dark-mode) .pregunta-card,
            body:not(.dark-mode) .container-form__form,
            body:not(.dark-mode) .profile-card,
            body:not(.dark-mode) .shein-dropdown {
                background-color: var(--card-color) !important;
                border-color: rgba(0,0,0,0.1) !important;
            }

            /* Textos internos para garantizar contraste en modo claro */
            body:not(.dark-mode) #productos figure figcaption,
            body:not(.dark-mode) #productos figure p,
            body:not(.dark-mode) .testimonial-card blockquote,
            body:not(.dark-mode) .pregunta-card p,
            body:not(.dark-mode) .container-form__form label,
            body:not(.dark-mode) .shein-name {
                color: var(--text-color) !important;
            }
        </style>";
    }
}