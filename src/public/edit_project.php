<?php
// src/public/edit_project.php
require_once '../core/bootstrap.php';

$project_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$project_data = null;

if (!$project_id_to_edit) {
    set_flash_message('error', "ID de proyecto no válido.");
    redirect('projects.php');
}

// Cargar datos del proyecto para editar
try {
    $stmt = $pdo->prepare("SELECT * FROM Projects WHERE project_id = ?");
    $stmt->execute([$project_id_to_edit]);
    $project_data = $stmt->fetch();

    if (!$project_data) {
        set_flash_message('error', "Proyecto no encontrado.");
        redirect('projects.php');
    }
} catch (PDOException $e) {
    set_flash_message('error', "Error al cargar el proyecto: " . $e->getMessage());
    error_log("Error en edit_project.php (carga): " . $e->getMessage());
    redirect('projects.php');
}

// Manejar la actualización del proyecto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_name = trim($_POST['project_name'] ?? '');
    $project_description = trim($_POST['project_description'] ?? $project_data['project_description']);

    if (empty($project_name)) {
        set_flash_message('error', "El nombre del proyecto es obligatorio.");
        // Para repoblar el formulario con los datos intentados si hay error de validación
        $project_data['project_description'] = $project_description;
    } else {
        try {
            $stmt_update = $pdo->prepare("UPDATE Projects SET project_name = ?, project_description = ? WHERE project_id = ?");
            $stmt_update->execute([$project_name, $project_description, $project_id_to_edit]);

            set_flash_message('success', "Proyecto «" . sanitize_output($project_name) . "» actualizado con éxito.");
            redirect('projects.php');

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Error de project_name UNIQUE
                set_flash_message('error', "Ya existe otro proyecto con este nombre.");
            } else {
                set_flash_message('error', "Error al actualizar el proyecto: " . $e->getMessage());
                error_log("Error en edit_project.php (actualización): " . $e->getMessage());
            }
            // Repoblar con los datos que el usuario intentó enviar
            $project_data['project_name'] = $project_name;
            $project_data['project_description'] = $project_description;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar proyecto - Veritas</title>
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
            <a href="projects.php">Volver a proyectos</a>
        </div>
        <h1>Editar proyecto «<?php echo $project_data ? sanitize_output($project_data['project_name']) : 'Proyecto no encontrado'; ?>»</h1>

        <?php display_flash_messages(); ?>

        <?php if ($project_data): ?>
            <form action="edit_project.php?id=<?php echo $project_data['project_id']; ?>" method="POST">
                <div>
                    <label for="project_name">Nombre del proyecto:</label>
                    <input type="text" id="project_name" name="project_name" value="<?php echo sanitize_output($project_data['project_name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="project_description">Descripción (opcional):</label>
                    <textarea id="project_description" name="project_description" rows="4"><?php echo sanitize_output($project_data['project_description'] ?? ''); ?></textarea>
                </div>
                <div>
                    <input type="submit" name="update_project" value="Actualizar">
                    <a href="projects.php" class="cancel-link">Cancelar</a>
                </div>
            </form>
        <?php elseif(!isset($_SESSION['error_message'])): ?>
            <p>No se pudo cargar la información del proyecto para editar.</p>
        <?php endif; ?>
    </div>
</body>
</html>