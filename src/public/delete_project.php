<?php
// src/public/delete_project.php
require_once '../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_project'])) {
    set_flash_message('error', "Acción no permitida.");
    redirect('projects.php');
}

$project_id_to_delete = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if (!$project_id_to_delete) {
    set_flash_message('error', "ID de proyecto no válido para eliminar.");
    redirect('projects.php');
}

// Para V1, solo permitimos borrar si no tiene fuentes.
// En el futuro, podríamos borrar en cascada (la BD lo haría si se borra el proyecto)
// o mover fuentes a un proyecto "archivado", etc.
try {
    $pdo->beginTransaction();

    // 1. Verificar que el proyecto existe y no tiene fuentes
    $stmt_check = $pdo->prepare("
        SELECT p.project_id, COUNT(s.source_id) as source_count
        FROM Projects p
        LEFT JOIN Sources s ON p.project_id = s.project_id
        WHERE p.project_id = ?
        GROUP BY p.project_id
    ");
    $stmt_check->execute([$project_id_to_delete]);
    $project_info = $stmt_check->fetch();

    if (!$project_info) {
        set_flash_message('error', "Proyecto no encontrado o ya fue eliminado.");
        $pdo->rollBack();
        redirect('projects.php');
    }

    if ($project_info['source_count'] > 0) {
        set_flash_message('error', "No se puede eliminar el proyecto porque aún contiene fuentes. Por favor, elimina primero todas sus fuentes.");
        $pdo->rollBack();
        redirect('projects.php');
    }

    // 2. Si llegamos aquí, el proyecto existe y no tiene fuentes. Proceder a eliminar.
    // La restricción ON DELETE CASCADE en la tabla Sources se encargaría de las fuentes si las hubiera,
    // pero nuestra lógica de aplicación lo previene por ahora.
    // Si hubiera otras tablas directamente relacionadas con Projects con ON DELETE CASCADE (ej. Source_Pages.project_id),
    // esas filas también se eliminarían.
    
    // Opcional V1+: Limpiar directorio de uploads si existe y está vacío
    $project_upload_dir = UPLOADS_FS_BASE . 'project_' . $project_id_to_delete;
    if (is_dir($project_upload_dir)) {
        // Comprobar si está vacío (solo '.' y '..')
        $is_empty = (count(scandir($project_upload_dir)) == 2);
        if ($is_empty) {
            if (!rmdir($project_upload_dir)) {
                error_log("No se pudo eliminar el directorio de uploads vacío para el proyecto ID {$project_id_to_delete}: {$project_upload_dir}");
                // No es un error fatal para el borrado del proyecto en BD, solo se loguea.
            } else {
                 error_log("Directorio de uploads vacío eliminado para el proyecto ID {$project_id_to_delete}: {$project_upload_dir}");
            }
        } else {
            error_log("El directorio de uploads para el proyecto ID {$project_id_to_delete} no está vacío, no se eliminará: {$project_upload_dir}");
        }
    }


    $stmt_delete_project = $pdo->prepare("DELETE FROM Projects WHERE project_id = ?");
    $stmt_delete_project->execute([$project_id_to_delete]);

    $pdo->commit();
    set_flash_message('success', "Proyecto eliminado con éxito.");

    // Si el proyecto eliminado era el activo, limpiar la sesión
    if (isset($_SESSION['active_project_id']) && $_SESSION['active_project_id'] == $project_id_to_delete) {
        unset($_SESSION['active_project_id']);
        unset($_SESSION['active_project_name']);
        unset($_SESSION['active_project_id_for_name']);
    }

} catch (Exception $e) { // Exception genérica
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash_message('error', "Error al eliminar el proyecto: " . $e->getMessage());
    error_log("Error en delete_project.php: " . $e->getMessage());
}

redirect('projects.php');
?>