<?php
// src/public/delete_page.php
require_once '../core/bootstrap.php'; // Carga config, functions, y db_connection ($pdo)

$active_project_id = get_active_project_id_or_redirect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_page'])) {
    // Solo permitir acceso vía POST desde el formulario de borrado
    set_flash_message('error', "Acción no permitida.");
    redirect('list_pages.php'); // O a la página anterior si se puede determinar
}

$page_id_to_delete = filter_input(INPUT_POST, 'page_id', FILTER_VALIDATE_INT);
$source_id_filter_for_redirect = filter_input(INPUT_POST, 'source_id_filter', FILTER_VALIDATE_INT); // Para redirigir correctamente

if (!$page_id_to_delete) {
    set_flash_message('error', "ID de página no válido para eliminar.");
    redirect('list_pages.php' . ($source_id_filter_for_redirect ? '?source_id='.$source_id_filter_for_redirect : ''));
}

try {
    $pdo->beginTransaction();

    // 1. Obtener información de la página (nombre de archivo, project_id para la ruta, source_id)
    //    y verificar que pertenece al proyecto activo
    $stmt_page_info = $pdo->prepare("SELECT image_filename, project_id, source_id FROM Source_Pages WHERE page_id = ? AND project_id = ?");
    $stmt_page_info->execute([$page_id_to_delete, $active_project_id]);
    $page_info = $stmt_page_info->fetch();

    if (!$page_info) {
        set_flash_message('error', "Página no encontrada, no pertenece a este proyecto, o ya fue eliminada.");
        $pdo->rollBack(); // Aunque no se hizo nada aún, por consistencia
        redirect('list_pages.php' . ($source_id_filter_for_redirect ? '?source_id='.$source_id_filter_for_redirect : ''));
    }

    // 2. Eliminar el archivo de imagen del servidor
    $image_filesystem_path = UPLOADS_FS_BASE . 'project_' . $page_info['project_id'] . '/' . $page_info['image_filename'];
    if (file_exists($image_filesystem_path)) {
        if (!unlink($image_filesystem_path)) {
            // Si no se puede borrar el archivo, es un problema, pero podríamos continuar borrando de la BD
            // o detener todo. Por ahora, registraremos el error y continuaremos.
            error_log("No se pudo eliminar el archivo de imagen: " . $image_filesystem_path);
            // set_flash_message('error', "No se pudo eliminar el archivo de imagen del servidor, pero se intentará borrar el registro de la BD.");
            // O podrías lanzar una excepción para detener el proceso si el borrado del archivo es crítico:
            // throw new Exception("No se pudo eliminar el archivo de imagen: " . $image_filesystem_path);
        }
    } else {
        error_log("Archivo de imagen no encontrado para eliminar: " . $image_filesystem_path);
    }

    // 3. Eliminar menciones asociadas (Cuando se implemente la tabla de Menciones)
    // $stmt_delete_mentions = $pdo->prepare("DELETE FROM Source_Mentions WHERE page_id = ?");
    // $stmt_delete_mentions->execute([$page_id_to_delete]);
    // error_log("Eliminadas " . $stmt_delete_mentions->rowCount() . " menciones para la página ID " . $page_id_to_delete);


    // 4. Eliminar el registro de la página de la base de datos
    $stmt_delete_page = $pdo->prepare("DELETE FROM Source_Pages WHERE page_id = ? AND project_id = ?");
    $stmt_delete_page->execute([$page_id_to_delete, $active_project_id]);

    $pdo->commit();
    set_flash_message('success', "Página eliminada con éxito.");

    // 5. Lógica para verificar si la fuente quedó sin páginas (esto se maneja en sources.php por ahora)
    //    Según lo acordado, `sources.php` es quien ofrece el borrado de fuentes con 0 páginas.
    //    Así que solo redirigimos.

} catch (Exception $e) { // Exception genérica para errores de lógica o de archivo
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash_message('error', "Error al eliminar la página: " . $e->getMessage());
    error_log("Error en delete_page.php: " . $e->getMessage());
}

// Redirigir de vuelta a la lista de páginas (filtrada o no)
redirect('list_pages.php' . ($source_id_filter_for_redirect ? '?source_id='.$source_id_filter_for_redirect : ''));

?>