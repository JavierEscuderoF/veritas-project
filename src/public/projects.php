<?php
// src/public/projects.php
require_once '../core/bootstrap.php'; // Carga config, functions, y db_connection ($pdo)

// Lógica para crear un nuevo proyecto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $project_name = trim($_POST['project_name'] ?? '');
    $project_description = trim($_POST['project_description'] ?? '');

    if (empty($project_name)) {
        set_flash_message('error', "El nombre del proyecto es obligatorio.");
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Projects (project_name, project_description) VALUES (?, ?)");
            $stmt->execute([$project_name, $project_description]);
            set_flash_message('success', "Proyecto «" . sanitize_output($project_name) . "» creado con éxito.");
            // No redirigir aquí para que se vea el mensaje y la lista actualizada
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                set_flash_message('error', "Ya existe un proyecto con este nombre.");
            } else {
                set_flash_message('error', "Error al crear el proyecto: " . $e->getMessage());
                error_log("Error en projects.php (creación): " . $e->getMessage());
            }
        }
    }
    // Redirigir para limpiar el POST y mostrar el mensaje flash
    redirect('projects.php');
}

// Lógica para seleccionar un proyecto activo
if (isset($_GET['select_project_id'])) {
    $selected_project_id = (int)$_GET['select_project_id'];
    try {
        $stmt = $pdo->prepare("SELECT project_id, project_name FROM Projects WHERE project_id = ?");
        $stmt->execute([$selected_project_id]);
        $project = $stmt->fetch();
        if ($project) {
            $_SESSION['active_project_id'] = $project['project_id'];
            $_SESSION['active_project_name'] = $project['project_name']; // Guardar nombre directamente
            $_SESSION['active_project_id_for_name'] = $project['project_id']; // Para la función get_active_project_name
            redirect('sources.php');
        } else {
            set_flash_message('error', "El proyecto seleccionado no es válido.");
        }
    } catch (PDOException $e) {
        set_flash_message('error', "Error al seleccionar el proyecto: " . $e->getMessage());
        error_log("Error en projects.php (selección): " . $e->getMessage());
    }
}

// Obtener lista de proyectos existentes con contador de fuentes
$projects_with_counts = [];
try {
    $sql = "SELECT p.project_id, p.project_name, p.project_description, COUNT(s.source_id) as source_count
            FROM Projects p
            LEFT JOIN Sources s ON p.project_id = s.project_id
            GROUP BY p.project_id, p.project_name, p.project_description
            ORDER BY source_count DESC";
    $stmt = $pdo->query($sql);
    $projects_with_counts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Guardar el mensaje en sesión para mostrarlo después de una posible redirección inicial
    // o si esta página se carga directamente con un error.
    // Como no hay redirección forzada aquí, podemos mostrarlo directamente.
    $page_error_message = "Error al cargar los proyectos: " . $e->getMessage();
    error_log("Error en projects.php (carga lista): " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyectos - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a, .action-links button { margin-right: 5px; text-decoration: none; padding: 5px 8px; border: 1px solid #ccc; background-color: #f0f0f0; color: #333; cursor: pointer; font-size:0.9em; border-radius: 3px; }
        .action-links form { display: inline-block; margin:0; padding:0; }
        form div { margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], textarea { width: calc(100% - 12px); padding: 6px; }
        input[type="submit"] { padding: 8px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        input[type="submit"]:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de proyectos de investigación</h1>
        
        <?php display_flash_messages(); ?>
        <?php if (!empty($page_error_message)): ?>
            <div class="message error"><?php echo sanitize_output($page_error_message); ?></div>
        <?php endif; ?>

        <h2>Proyectos existentes</h2>
        <?php if (count($projects_with_counts) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre del proyecto</th>
                        <th>Descripción</th>
                        <th>Nº de fuentes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects_with_counts as $project): ?>
                        <tr>
                            <td><?php echo sanitize_output($project['project_name']); ?></td>
                            <td><?php echo nl2br(sanitize_output($project['project_description'] ?? '')); ?></td>
                            <td><?php echo $project['source_count']; ?></td>
                            <td class="action-links">
                                <a href="projects.php?select_project_id=<?php echo $project['project_id']; ?>">Seleccionar</a>
                                <a href="edit_project.php?id=<?php echo $project['project_id']; ?>">Editar</a>
                                <?php if ($project['source_count'] == 0): ?>
                                    <form action="delete_project.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este proyecto? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                        <button type="submit" name="delete_project">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay proyectos creados todavía.</p>
        <?php endif; ?>

        <hr>

        <h2>Crear un nuevo proyecto</h2>
        <form action="projects.php" method="POST">
            <div>
                <label for="project_name">Nombre del proyecto:</label>
                <input type="text" id="project_name" name="project_name" required>
            </div>
            <div>
                <label for="project_description">Descripción (opcional):</label>
                <textarea id="project_description" name="project_description" rows="3"></textarea>
            </div>
            <div>
                <input type="submit" name="create_project" value="Crear Proyecto">
            </div>
        </form>
    </div>
</body>
</html>