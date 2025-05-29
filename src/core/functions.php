<?php
// src/core/functions.php

// No necesitas incluir config.php aquí si bootstrap.php lo hace antes,
// pero si alguna función se usara en un contexto donde config.php no está cargado,
// podría ser necesario. Con bootstrap.php, las constantes de config.php estarán disponibles.

// -- CONFIGURACIÓN DE ERRORES Y SESIÓN --
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -- FUNCIONES HELPER -- (Las mismas que antes, pero ahora usan constantes de config.php si es necesario)

/**
 * Redirige a otra página.
 * @param string $path La ruta relativa al directorio public (ej: 'projects.php', 'sources.php?id=1')
 */
function redirect(string $path): void {
    if (parse_url($path, PHP_URL_SCHEME) === null) { // Si no es una URL absoluta
        // BASE_URL está definida en config.php
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    } else {
        $url = $path;
    }
    header("Location: " . $url);
    exit;
}

/**
 * Sanitiza una cadena para mostrarla en HTML de forma segura.
 */
function sanitize_output(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un ID público.
 */
function generate_public_id(string $prefix, int $numeric_id): string {
    return strtoupper($prefix) . $numeric_id;
}

/**
 * Obtiene el ID del proyecto activo o redirige.
 */
function get_active_project_id_or_redirect(): int {
    if (!isset($_SESSION['active_project_id']) || !is_numeric($_SESSION['active_project_id'])) {
        set_flash_message('error', "Por favor, selecciona un proyecto para continuar.");
        redirect('projects.php'); // BASE_URL se encargará del path correcto
    }
    return (int)$_SESSION['active_project_id'];
}

/**
 * Obtiene el nombre del proyecto activo. Requiere $pdo.
 */
function get_active_project_name(PDO $pdo, int $project_id): string {
    // Cache en sesión para evitar consultas repetidas
    if (isset($_SESSION['active_project_name']) && isset($_SESSION['active_project_id_for_name']) && $_SESSION['active_project_id_for_name'] === $project_id) {
        return $_SESSION['active_project_name'];
    }

    try {
        $stmt = $pdo->prepare("SELECT project_name FROM Projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $project_details = $stmt->fetch();
        if ($project_details) {
            $_SESSION['active_project_name'] = $project_details['project_name'];
            $_SESSION['active_project_id_for_name'] = $project_id;
            return $project_details['project_name'];
        } else {
            return 'Proyecto Desconocido';
        }
    } catch (PDOException $e) {
        error_log("Error en get_active_project_name: " . $e->getMessage());
        return 'Error al cargar nombre';
    }
}

/**
 * Establece un mensaje flash.
 */
function set_flash_message(string $type, string $message): void {
    $_SESSION[$type . '_message'] = $message;
}

/**
 * Muestra y limpia mensajes flash.
 */
function display_flash_messages(): void {
    if (isset($_SESSION['success_message'])) {
        echo '<div class="message success">' . sanitize_output($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="message error">' . sanitize_output($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
}
?>