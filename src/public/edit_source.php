<?php
// src/public/edit_source.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/bootstrap.php';

$active_project_id = get_active_project_id_or_redirect();
$active_project_name = $_SESSION['active_project_name'] ?? 'Proyecto Activo';

$source_data = null;

$source_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$source_id_to_edit) {
    set_flash_message('error', "ID de fuente no válido.");
    redirect('sources.php');
}

// Cargar datos de la fuente para editar
try {
    $stmt = $pdo->prepare("SELECT * FROM Sources WHERE source_id = ? AND project_id = ?");
    $stmt->execute([$source_id_to_edit, $active_project_id]);
    $source_data = $stmt->fetch();

    if (!$source_data) {
        set_flash_message('error', "Fuente no encontrada o no pertenece a este proyecto.");
        redirect('sources.php');
    }
} catch (PDOException $e) {
    set_flash_message('error', "Error al cargar la fuente: " . $e->getMessage());
    // No redirigir aquí para que se muestre el error en la página
}


// Manejar la actualización de la fuente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_source'])) {
    if (!$source_data) { // Si hubo error cargando la fuente, no procesar el POST
        redirect('sources.php'); // O mostrar error en la página
    }

    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? null);
    $source_type = trim($_POST['source_type'] ?? null);
    $repository_name = trim($_POST['repository_name'] ?? null);
    $repository_ref_code = trim($_POST['repository_ref_code'] ?? null);
    $source_date_text = trim($_POST['source_date_text'] ?? null);
    $source_notes = trim($_POST['source_notes'] ?? null);

    if (empty($title)) {
        set_flash_message('error', "El título de la fuente es obligatorio.");
    } else {
        try {
            $sql = "UPDATE Sources SET 
                        title = ?, author = ?, source_type = ?, 
                        repository_name = ?, repository_ref_code = ?, 
                        source_date_text = ?, source_notes = ?
                    WHERE source_id = ? AND project_id = ?";
            $stmt_update = $pdo->prepare($sql);
            $stmt_update->execute([
                $title, $author, $source_type,
                $repository_name, $repository_ref_code,
                $source_date_text, $source_notes,
                $source_id_to_edit, $active_project_id
            ]);

            set_flash_message('success', "Fuente «" . sanitize_output($title) . "» actualizada con éxito.");
            redirect('sources.php');

        } catch (PDOException $e) {
            set_flash_message('error', "Error al actualizar la fuente: " . $e->getMessage());
            // Recargar datos por si el usuario quiere reintentar con los valores previos al fallo
            $stmt = $pdo->prepare("SELECT * FROM Sources WHERE source_id = ? AND project_id = ?");
            $stmt->execute([$source_id_to_edit, $active_project_id]);
            $source_data = $stmt->fetch(); // Recargar por si la transacción falló
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar fuente - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 700px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        form div { margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: calc(100% - 18px); padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        textarea { resize: vertical; min-height: 60px; }
        input[type="submit"], .cancel-link { padding: 10px 20px; text-decoration: none; border: none; cursor: pointer; border-radius: 4px; margin-right:10px; }
        input[type="submit"] { background-color: #007bff; color: white; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .cancel-link { background-color: #6c757d; color:white; }
        .cancel-link:hover { background-color: #5a6268;}
        .project-nav a { margin-right: 15px; }
    </style>
</head>
<body>

    <div class="container">
        <div class="project-nav">
            <a href="sources.php">Volver a fuentes del proyecto «<?php echo sanitize_output($active_project_name); ?>»</a>
        </div>
        
        <h1>Editar fuente «<?php echo $source_data ? sanitize_output($source_data['title'] . '» (' . $source_data['source_public_id']) . ')' : 'Fuente no encontrada'; ?></h1>
        
        <?php display_flash_messages(); ?>

        <?php if ($source_data): ?>
            <form action="edit_source.php?id=<?php echo $source_data['source_id']; ?>" method="POST">
                <div>
                    <label for="title">Título de la fuente:</label>
                    <input type="text" id="title" name="title" value="<?php echo sanitize_output($source_data['title'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="author">Autor:</label>
                    <input type="text" id="author" name="author" value="<?php echo sanitize_output($source_data['author'] ?? ''); ?>">
                </div>
                <div>
                    <label for="source_type">Tipo de fuente:</label>
                    <input type="text" id="source_type" name="source_type" value="<?php echo sanitize_output($source_data['source_type'] ?? ''); ?>" placeholder="Ej: Registro Parroquial">
                </div>
                <div>
                    <label for="repository_name">Nombre del repositorio:</label>
                    <input type="text" id="repository_name" name="repository_name" value="<?php echo sanitize_output($source_data['repository_name'] ?? ''); ?>">
                </div>
                <div>
                    <label for="repository_ref_code">Referencia en repositorio:</label>
                    <input type="text" id="repository_ref_code" name="repository_ref_code" value="<?php echo sanitize_output($source_data['repository_ref_code'] ?? ''); ?>">
                </div>
                <div>
                    <label for="source_date_text">Fecha de la fuente (texto):</label>
                    <input type="text" id="source_date_text" name="source_date_text" value="<?php echo sanitize_output($source_data['source_date_text'] ?? ''); ?>" placeholder="Ej: ca. 1880, Siglo XVII">
                </div>
                <div>
                    <label for="source_notes">Notas de la fuente:</label>
                    <textarea id="source_notes" name="source_notes" rows="4"><?php echo sanitize_output($source_data['source_notes'] ?? ''); ?></textarea>
                </div>
                <div>
                    <input type="submit" name="update_source" value="Actualizar">
                    <a href="sources.php" class="cancel-link">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>