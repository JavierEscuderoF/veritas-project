<?php
// src/public/edit_page.php
require_once '../core/bootstrap.php'; // Carga config, functions, y db_connection ($pdo)

$active_project_id = get_active_project_id_or_redirect();
$active_project_name = get_active_project_name($pdo, $active_project_id);

$page_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$page_data = null;

if (!$page_id_to_edit) {
    set_flash_message('error', "ID de página no válido.");
    redirect('list_pages.php'); // O a sources.php si es más apropiado
}

// Cargar datos de la página para editar
try {
    $sql = "SELECT sp.*, s.title as source_title, s.source_public_id
            FROM Source_Pages sp
            JOIN Sources s ON sp.source_id = s.source_id
            WHERE sp.page_id = ? AND sp.project_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$page_id_to_edit, $active_project_id]);
    $page_data = $stmt->fetch();

    if (!$page_data) {
        set_flash_message('error', "Página no encontrada o no pertenece a este proyecto.");
        redirect('list_pages.php');
    }
} catch (PDOException $e) {
    set_flash_message('error', "Error al cargar la página para editar: " . $e->getMessage());
    error_log("Error en edit_page.php (carga): " . $e->getMessage());
    redirect('list_pages.php');
}

// Manejar la actualización de la página
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_page'])) {
    if (!$page_data) {
        // Si hubo error cargando la página, no procesar
        redirect('list_pages.php');
    }

    $page_number_in_source = trim($_POST['page_number_in_source'] ?? $page_data['page_number_in_source']);
    $page_description = trim($_POST['page_description'] ?? $page_data['page_description']);
    // Por ahora no manejamos la re-subida de imagen para V1

    // Aquí podrías añadir más validaciones si fueran necesarias

    try {
        $sql_update = "UPDATE Source_Pages SET 
                        page_number_in_source = ?, 
                        page_description = ?
                       WHERE page_id = ? AND project_id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            $page_number_in_source,
            $page_description,
            $page_id_to_edit,
            $active_project_id
        ]);

        set_flash_message('success', "Detalles de la página '" . sanitize_output($page_data['page_public_id']) . "' actualizados con éxito.");
        redirect('view_page.php?id=' . $page_id_to_edit);

    } catch (PDOException $e) {
        set_flash_message('error', "Error al actualizar los detalles de la página: " . $e->getMessage());
        error_log("Error en edit_page.php (actualización): " . $e->getMessage());
        // No redirigir, permitir al usuario reintentar o ver el error y los datos que ingresó
        // Los datos del formulario se repoblarán con los valores POST o los datos originales si el POST falla antes de la BD
        // Para repoblar con $_POST en caso de error de BD, necesitarías asignar los valores POST a las variables de $page_data
        $page_data['page_number_in_source'] = $page_number_in_source; // Mostrar el intento del usuario
        $page_data['page_description'] = $page_description;        // Mostrar el intento del usuario
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Página <?php echo $page_data ? sanitize_output($page_data['page_public_id']) : ''; ?> - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 700px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        form div { margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: calc(100% - 18px); padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        textarea { resize: vertical; min-height: 80px; }
        input[type="submit"], .cancel-link { padding: 10px 20px; text-decoration:none; border: none; cursor: pointer; border-radius: 4px; margin-right:10px;}
        input[type="submit"] { background-color: #007bff; color: white; }
        .cancel-link { background-color: #6c757d; color:white; display:inline-block;}
        .project-nav a { margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="project-nav">
            <a href="projects.php">Cambiar Proyecto</a> | 
            <a href="sources.php">Fuentes de "<?php echo sanitize_output($active_project_name); ?>"</a>
            <?php if ($page_data): ?>
                | <a href="list_pages.php?source_id=<?php echo $page_data['source_id']; ?>">Páginas de "<?php echo sanitize_output($page_data['source_public_id'] . ' - ' . $page_data['source_title']); ?>"</a>
                | <a href="view_page.php?id=<?php echo $page_data['page_id']; ?>">Ver Página <?php echo sanitize_output($page_data['page_public_id']); ?></a>
            <?php endif; ?>
        </div>

        <h1>Editar Detalles de Página: <?php echo $page_data ? sanitize_output($page_data['page_public_id']) : 'Página no encontrada'; ?></h1>

        <?php display_flash_messages(); ?>

        <?php if ($page_data): ?>
            <form action="edit_page.php?id=<?php echo $page_data['page_id']; ?>" method="POST">
                <div>
                    <label for="page_number_in_source">Número/Identificador de Página en Fuente:</label>
                    <input type="text" id="page_number_in_source" name="page_number_in_source" value="<?php echo sanitize_output($page_data['page_number_in_source'] ?? ''); ?>" placeholder="Ej: f. 12r, p. 5">
                </div>
                <div>
                    <label for="page_description">Descripción de la Página (opcional):</label>
                    <textarea id="page_description" name="page_description" rows="5"><?php echo sanitize_output($page_data['page_description'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <input type="submit" name="update_page" value="Actualizar Detalles">
                    <a href="view_page.php?id=<?php echo $page_data['page_id']; ?>" class="cancel-link">Cancelar</a>
                </div>
            </form>
        <?php elseif(!isset($_SESSION['error_message'])): // Solo mostrar si no hubo ya un mensaje de error por redirect ?>
            <p>No se pudo cargar la información de la página para editar.</p>
        <?php endif; ?>
    </div>
</body>
</html>