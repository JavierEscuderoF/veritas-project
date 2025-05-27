<?php
// src/public/projects.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/db_connection.php';
require_once '../core/functions.php';

// Lógica para crear un nuevo proyecto
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $project_name = trim($_POST['project_name'] ?? '');
    $project_description = trim($_POST['project_description'] ?? '');

    if (empty($project_name)) {
        $error_message = "El nombre del proyecto es obligatorio.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Projects (project_name, project_description) VALUES (?, ?)");
            $stmt->execute([$project_name, $project_description]);
            $success_message = "Proyecto '" . sanitize_output($project_name) . "' creado con éxito.";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Error de entrada duplicada (por project_name UNIQUE)
                $error_message = "Ya existe un proyecto con este nombre.";
            } else {
                $error_message = "Error al crear el proyecto: " . $e->getMessage();
            }
        }
    }
}

// Lógica para seleccionar un proyecto activo
if (isset($_GET['select_project_id'])) {
    $selected_project_id = (int)$_GET['select_project_id'];
    // Verificar que el proyecto existe
    $stmt = $pdo->prepare("SELECT project_id FROM Projects WHERE project_id = ?");
    $stmt->execute([$selected_project_id]);
    if ($stmt->fetch()) {
        $_SESSION['active_project_id'] = $selected_project_id;
        $_SESSION['active_project_name'] = ''; // Se cargará en la siguiente página
        redirect('sources.php'); // O a un dashboard del proyecto
    } else {
        $error_message = "El proyecto seleccionado no es válido.";
    }
}

// Obtener lista de proyectos existentes
$projects = [];
try {
    $stmt = $pdo->query("SELECT project_id, project_name, project_description FROM Projects ORDER BY project_name ASC");
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error al cargar los proyectos: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proyectos - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        form div { margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], textarea { width: calc(100% - 12px); padding: 6px; }
        input[type="submit"] { padding: 8px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        input[type="submit"]:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mis proyectos de investigación</h1>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo sanitize_output($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo sanitize_output($error_message); ?></div>
        <?php endif; ?>

        <h2>Proyectos existentes</h2>
        <?php if (count($projects) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre del Proyecto</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo sanitize_output($project['project_name']); ?></td>
                            <td><?php echo nl2br(sanitize_output($project['project_description'] ?? '')); ?></td>
                            <td class="action-links">
                                <a href="projects.php?select_project_id=<?php echo $project['project_id']; ?>">Seleccionar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay proyectos creados todavía.</p>
        <?php endif; ?>

        <hr>

        <h2>Crear nuevo proyecto</h2>
        <form action="projects.php" method="POST">
            <div>
                <label for="project_name">Nombre del Proyecto:</label>
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