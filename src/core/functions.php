<?php
// src/core/functions.php

// Iniciar sesión si no está ya iniciada (importante para el active_project_id)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirige a otra página.
 * @param string $url La URL a la que redirigir.
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Sanitiza una cadena para mostrarla en HTML de forma segura.
 * @param string|null $string La cadena a sanitizar.
 * @return string La cadena sanitizada.
 */
function sanitize_output(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un ID público basado en un prefijo y un ID numérico.
 * @param string $prefix Ej: "S" para Source, "P" para Page.
 * @param int $numeric_id El ID numérico de la base de datos.
 * @return string El ID público generado.
 */
function generate_public_id(string $prefix, int $numeric_id): string {
    return strtoupper($prefix) . $numeric_id;
}

/**
 * Verifica si hay un proyecto activo en la sesión.
 * Si no, redirige a la página de selección de proyectos.
 * @return int El ID del proyecto activo.
 */
function get_active_project_id_or_redirect(): int {
    if (!isset($_SESSION['active_project_id']) || !is_numeric($_SESSION['active_project_id'])) {
        // Podrías añadir un mensaje flash aquí si tuvieras un sistema para ello.
        redirect('projects.php');
    }
    return (int)$_SESSION['active_project_id'];
}

// Más funciones útiles podrían ir aquí:
// - Formateo de fechas
// - Validaciones específicas
// - etc.