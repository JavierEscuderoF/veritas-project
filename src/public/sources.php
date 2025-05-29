<?php
// src/public/sources.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/bootstrap.php';

$active_project_id = get_active_project_id_or_redirect(); // Asegura que hay un proyecto activo

// Cargar nombre del proyecto activo si no está en sesión
if (empty($_SESSION['active_project_name'])) {
    try {
        $stmt_project_name = $pdo->prepare("SELECT project_name FROM Projects WHERE project_id = ?");
        $stmt_project_name->execute([$active_project_id]);
        $project_details = $stmt_project_name->fetch();
        if ($project_details) {
            $_SESSION['active_project_name'] = $project_details['project_name'];
        } else {
            // Proyecto no encontrado, algo raro, redirigir
            unset($_SESSION['active_project_id']);
            redirect('projects.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', "Error al cargar el nombre del proyecto: " . $e->getMessage());
        redirect('projects.php'); 
    }
}
$active_project_name = $_SESSION['active_project_name'] ?? 'Desconocido';


// Lógica para eliminar una fuente (si tiene 0 páginas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_source'])) {
    $source_to_delete_id = (int)$_POST['source_id'];

    // Verificar que la fuente pertenece al proyecto activo y no tiene páginas
    try {
        $pdo->beginTransaction();

        $stmt_check = $pdo->prepare("
            SELECT s.source_id, COUNT(sp.page_id) as page_count
            FROM Sources s
            LEFT JOIN Source_Pages sp ON s.source_id = sp.source_id
            WHERE s.source_id = ? AND s.project_id = ?
            GROUP BY s.source_id
        ");
        $stmt_check->execute([$source_to_delete_id, $active_project_id]);
        $source_data = $stmt_check->fetch();

        if ($source_data && $source_data['page_count'] == 0) {
            $stmt_delete = $pdo->prepare("DELETE FROM Sources WHERE source_id = ? AND project_id = ?");
            $stmt_delete->execute([$source_to_delete_id, $active_project_id]);
            set_flash_message('success', "Fuente eliminada con éxito.");
            redirect('sources.php');
        } else {
            set_flash_message('error', "No se puede eliminar la fuente. Asegúrate de que no tenga páginas o que pertenezca al proyecto activo.");
            redirect('sources.php');
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        set_flash_message('error', "Error al eliminar la fuente: " . $e->getMessage());
    }
}


// Obtener lista de fuentes para el proyecto activo
$sources = [];
try {
    $sql = "SELECT s.source_id, s.source_public_id, s.title, s.author, s.source_type, COUNT(sp.page_id) as page_count
            FROM Sources s
            LEFT JOIN Source_Pages sp ON s.source_id = sp.source_id
            WHERE s.project_id = ?
            GROUP BY s.source_id, s.source_public_id, s.title, s.author, s.source_type
            ORDER BY s.title ASC";
    $stmt_sources = $pdo->prepare($sql);
    $stmt_sources->execute([$active_project_id]);
    $sources = $stmt_sources->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', " Error al cargar las fuentes: " . $e->getMessage()); // Anteriormente: Usar .= para no sobrescribir mensajes anteriores 
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuentes del Proyecto - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a, .action-links button { margin-right: 5px; text-decoration: none; padding: 5px 8px; border: 1px solid #ccc; background-color: #f0f0f0; color: #333; cursor: pointer; font-size: 0.9em;}
        .action-links button { border-radius: 3px; }
        .action-links form { display: inline-block; margin: 0; padding: 0;}
        .header-actions { margin-bottom: 20px; }
        .header-actions a { text-decoration: none; padding: 10px 15px; background-color: #007bff; color: white; border-radius: 4px; }
        .header-actions a:hover { background-color: #0056b3; }
        .project-nav a { margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="project-nav">
            <a href="projects.php">Volver a Proyectos</a>
            | <span>Proyecto Activo: <strong><?php echo sanitize_output($active_project_name); ?></strong></span>
        </div>

        <?php display_flash_messages(); ?>

        <h1>Fuentes del Proyecto: "<?php echo sanitize_output($active_project_name); ?>"</h1>

        <div class="header-actions">
            <a href="add_page.php">Añadir Nueva Página/Fuente</a>
            <a href="list_pages.php" style="margin-left:15px; background-color: #28a745;">Ver Todas las Páginas del Proyecto</a>

        </div>

        <h2>Listado de Fuentes</h2>
        <?php if (count($sources) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Público</th>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>Tipo</th>
                        <th>Nº Páginas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td><?php echo sanitize_output($source['source_public_id']); ?></td>
                            <td><?php echo sanitize_output($source['title']); ?></td>
                            <td><?php echo sanitize_output($source['author'] ?? '-'); ?></td>
                            <td><?php echo sanitize_output($source['source_type'] ?? '-'); ?></td>
                            <td><?php echo $source['page_count']; ?></td>
                            <td class="action-links">
                                <a href="list_pages.php?source_id=<?php echo $source['source_id']; ?>">Ver Páginas</a>
                                <a href="edit_source.php?id=<?php echo $source['source_id']; ?>">Editar</a>
                                <?php if ($source['page_count'] == 0): ?>
                                    <form action="sources.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta fuente? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="source_id" value="<?php echo $source['source_id']; ?>">
                                        <button type="submit" name="delete_source">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay fuentes creadas para este proyecto todavía. <a href="add_page.php">Añade la primera página (y fuente)</a>.</p>
        <?php endif; ?>
    </div>
</body>
</html>