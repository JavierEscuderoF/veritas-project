<?php
// src/public/view_page.php
require_once '../core/bootstrap.php'; // Carga config, functions, y db_connection ($pdo)

$active_project_id = get_active_project_id_or_redirect();
$active_project_name = get_active_project_name($pdo, $active_project_id);

$page_id_to_view = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$page_data = null;
$source_data = null;
$image_web_path = '';

if (!$page_id_to_view) {
    set_flash_message('error', "ID de página no válido.");
    redirect('list_pages.php'); // O a sources.php si es más apropiado
}

try {
    $sql = "SELECT sp.*, s.title as source_title, s.source_public_id
            FROM Source_Pages sp
            JOIN Sources s ON sp.source_id = s.source_id
            WHERE sp.page_id = ? AND sp.project_id = ?"; // Asegurar que la página pertenece al proyecto activo
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$page_id_to_view, $active_project_id]);
    $page_data = $stmt->fetch();

    if (!$page_data) {
        set_flash_message('error', "Página no encontrada o no pertenece a este proyecto.");
        redirect('list_pages.php'); // O a sources.php
    }

    // Construir la ruta web de la imagen
    // UPLOADS_WEB_BASE (ej: 'uploads/') y la carpeta del proyecto
    $image_web_path = rtrim(BASE_URL, '/') . '/' . UPLOADS_WEB_BASE . 'project_' . $page_data['project_id'] . '/' . $page_data['image_filename'];

} catch (PDOException $e) {
    set_flash_message('error', "Error al cargar la página: " . $e->getMessage());
    error_log("Error en view_page.php: " . $e->getMessage());
    redirect('list_pages.php');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Página <?php echo $page_data ? sanitize_output($page_data['page_public_id']) : ''; ?> - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 90%; margin: auto; } /* Más ancho para imágenes */
        .page-header { margin-bottom: 20px; }
        .page-header h1 { margin-bottom: 5px; }
        .page-info { margin-bottom: 15px; font-size: 0.9em; color: #555; }
        .page-image-container { text-align: center; margin-bottom: 20px; border: 1px solid #ccc; padding:10px; }
        .page-image-container img { max-width: 100%; height: auto; border: 1px solid #eee; }
        .actions a { margin-right: 10px; text-decoration: none; padding: 8px 12px; background-color: #007bff; color: white; border-radius: 4px; }
        .actions a:hover { background-color: #0056b3; }
        .project-nav a { margin-right: 15px; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="project-nav">
            <a href="projects.php">Cambiar Proyecto</a> | 
            <a href="sources.php">Fuentes de "<?php echo sanitize_output($active_project_name); ?>"</a>
            <?php if ($page_data): ?>
                | <a href="list_pages.php?source_id=<?php echo $page_data['source_id']; ?>">Páginas de "<?php echo sanitize_output($page_data['source_public_id'] . ' - ' . $page_data['source_title']); ?>"</a>
            <?php endif; ?>
        </div>

        <?php display_flash_messages(); ?>

        <?php if ($page_data): ?>
            <div class="page-header">
                <h1>Página: <?php echo sanitize_output($page_data['page_public_id']); ?></h1>
                <div class="page-info">
                    Fuente: <?php echo sanitize_output($page_data['source_public_id'] . ' - ' . $page_data['source_title']); ?><br>
                    Número en fuente: <?php echo sanitize_output($page_data['page_number_in_source'] ?? 'N/A'); ?><br>
                    Subida el: <?php echo date("d/m/Y H:i", strtotime($page_data['uploaded_at'])); ?><br>
                    <?php if ($page_data['page_description']): ?>
                        Descripción: <?php echo nl2br(sanitize_output($page_data['page_description'])); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="actions">
                <a href="edit_page.php?id=<?php echo $page_data['page_id']; ?>">Editar Detalles de Página</a>
                </div>

            <div class="page-image-container">
                <img src="<?php echo sanitize_output($image_web_path); ?>" alt="Imagen de la página <?php echo sanitize_output($page_data['page_public_id']); ?>">
            </div>
            
            <div id="mention-marking-area" style="border:1px dashed #aaa; padding: 20px; text-align:center; color:#777; margin-top:20px;">
                (Futura Área para Marcar Menciones en la Imagen)
            </div>

        <?php else: ?>
            <p>La página solicitada no pudo ser cargada.</p>
        <?php endif; ?>
    </div>
</body>
</html>