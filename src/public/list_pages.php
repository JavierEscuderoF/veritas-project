<?php
// src/public/list_pages.php
require_once '../core/bootstrap.php';

$active_project_id = get_active_project_id_or_redirect();
$active_project_name = get_active_project_name($pdo, $active_project_id);

$source_id_filter = filter_input(INPUT_GET, 'source_id', FILTER_VALIDATE_INT);

$page_title = "Páginas del proyecto «" . sanitize_output($active_project_name) . "»";
$current_source_title = null;
$pages = [];

try {
    if ($source_id_filter) {
        // Listar páginas de una fuente específica
        $stmt_source_title = $pdo->prepare("SELECT title, source_public_id FROM Sources WHERE source_id = ? AND project_id = ?");
        $stmt_source_title->execute([$source_id_filter, $active_project_id]);
        $source_info = $stmt_source_title->fetch();

        if ($source_info) {
            $current_source_title = $source_info['title'];
            $page_title = "Páginas de la fuente «" . $current_source_title . "» (" . sanitize_output($source_info['source_public_id'] . ")" );

            $sql_pages = "SELECT sp.page_id, sp.page_public_id, sp.page_number_in_source, sp.uploaded_at, s.source_public_id as parent_source_public_id
                          FROM Source_Pages sp
                          JOIN Sources s ON sp.source_id = s.source_id
                          WHERE sp.source_id = ? AND sp.project_id = ? 
                          ORDER BY sp.page_number_in_source ASC, sp.uploaded_at ASC"; // Podrías necesitar un mejor orden para page_number_in_source
            $stmt_pages = $pdo->prepare($sql_pages);
            $stmt_pages->execute([$source_id_filter, $active_project_id]);
            $pages = $stmt_pages->fetchAll();
        } else {
            set_flash_message('error', "Fuente no encontrada o no pertenece a este proyecto.");
            redirect('sources.php');
        }
    } else {
        // Listar todas las páginas del proyecto activo
        $sql_pages = "SELECT sp.page_id, sp.page_public_id, sp.page_number_in_source, sp.uploaded_at, s.source_public_id as parent_source_public_id, s.title as parent_source_title
                      FROM Source_Pages sp
                      JOIN Sources s ON sp.source_id = s.source_id
                      WHERE sp.project_id = ?
                      ORDER BY s.title ASC, sp.page_number_in_source ASC, sp.uploaded_at ASC";
        $stmt_pages = $pdo->prepare($sql_pages);
        $stmt_pages->execute([$active_project_id]);
        $pages = $stmt_pages->fetchAll();
    }
} catch (PDOException $e) {
    set_flash_message('error', "Error al cargar las páginas: " . $e->getMessage());
    // Redirigir a sources.php si hay error, o manejarlo para mostrar el mensaje
    redirect($source_id_filter ? 'sources.php?id=' . $source_id_filter : 'sources.php');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a, .action-links button { margin-right: 5px; text-decoration: none; padding: 5px 8px; border: 1px solid #ccc; background-color: #f0f0f0; color: #333; cursor: pointer; font-size: 0.9em; border-radius: 3px;}
        .action-links form { display: inline-block; margin: 0; padding: 0;}
        .header-actions { margin-bottom: 20px; }
        .header-actions a { text-decoration: none; padding: 10px 15px; background-color: #007bff; color: white; border-radius: 4px; }
        .project-nav a { margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="project-nav">
            <a href="projects.php">Cambiar de proyecto</a> | 
            <a href="sources.php">Fuentes del proyecto «<?php echo sanitize_output($active_project_name); ?>»</a>
        </div>
        <h1><?php echo $page_title; ?></h1>

        <?php display_flash_messages(); ?>

        <div class="header-actions">
            <a href="add_page.php<?php echo $source_id_filter ? '?source_id='.$source_id_filter : ''; ?>">Añadir página nueva <?php echo $source_id_filter ? 'a esta fuente' : ''; ?></a>
        </div>

        <?php if (count($pages) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Página</th>
                        <?php if (!$source_id_filter): // Mostrar columna Fuente solo en la vista de todas las páginas ?>
                            <th>Fuente</th>
                        <?php endif; ?>
                        <th>Nº en fuente</th>
                        <th>Subida el</th>
                        <th>Nº de menciones</th> <th>% de menciones enlazadas</th> <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?php echo sanitize_output($page['page_public_id']); ?></td>
                            <?php if (!$source_id_filter): ?>
                                <td>
                                    <a href="list_pages.php?source_id=<?php 
                                        // Necesitamos el source_id numérico para el enlace
                                        $temp_stmt = $pdo->prepare("SELECT source_id FROM Sources WHERE source_public_id = ? AND project_id = ?");
                                        $temp_stmt->execute([$page['parent_source_public_id'], $active_project_id]);
                                        $temp_source = $temp_stmt->fetch();
                                        if ($temp_source) echo $temp_source['source_id']; else echo '#';
                                    ?>">
                                        <?php echo sanitize_output($page['parent_source_public_id'] . ' - ' . $page['parent_source_title']); ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                            <td><?php echo sanitize_output($page['page_number_in_source'] ?? 'N/A'); ?></td>
                            <td><?php echo date("d/m/Y H:i", strtotime($page['uploaded_at'])); ?></td>
                            <td>0</td> <td>0%</td> <td class="action-links">
                                <a href="view_page.php?id=<?php echo $page['page_id']; ?>">Entidades</a>
                                <a href="edit_page.php?id=<?php echo $page['page_id']; ?>">Editar</a>
                                <form action="delete_page.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta página y todas sus menciones asociadas? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="page_id" value="<?php echo $page['page_id']; ?>">
                                    <input type="hidden" name="source_id_filter" value="<?php echo $source_id_filter ?? ''; ?>"> <button type="submit" name="delete_page">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay páginas para mostrar <?php echo $source_id_filter ? 'en esta fuente' : 'en este proyecto'; ?>.</p>
        <?php endif; ?>
    </div>
</body>
</html>