<?php
// src/public/add_page.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/db_connection.php';
require_once '../core/functions.php';

$active_project_id = get_active_project_id_or_redirect();
$active_project_name = $_SESSION['active_project_name'] ?? 'Proyecto Activo'; // Asume que se cargó en sources.php

$error_message = '';
$success_message = '';

// Definir la ruta base para las subidas de imágenes
// Asegúrate de que esta ruta es relativa al directorio raíz de tu aplicación
// y que el servidor web tiene permisos de escritura.
// La carpeta 'uploads' debe estar dentro de 'public' y ser escribible por www-data.
define('UPLOADS_DIR_BASE', __DIR__ . '/uploads/'); // __DIR__ es el directorio de este script (public/)
define('UPLOADS_URL_BASE', 'uploads/'); // URL base relativa al webroot (public/)

// Obtener fuentes existentes para el desplegable
$existing_sources = [];
try {
    $stmt = $pdo->prepare("SELECT source_id, source_public_id, title FROM Sources WHERE project_id = ? ORDER BY title ASC");
    $stmt->execute([$active_project_id]);
    $existing_sources = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error al cargar las fuentes existentes: " . $e->getMessage();
}

// Variables para repoblar el formulario en caso de error
$source_choice = $_POST['source_choice'] ?? 'existing';
$selected_source_id = $_POST['existing_source_id'] ?? '';
$new_source_title = $_POST['new_source_title'] ?? '';
$new_source_author = $_POST['new_source_author'] ?? '';
$new_source_type = $_POST['new_source_type'] ?? '';
$new_source_repo_name = $_POST['new_source_repo_name'] ?? '';
$new_source_repo_ref = $_POST['new_source_repo_ref'] ?? '';
$new_source_date_text = $_POST['new_source_date_text'] ?? '';
$new_source_notes = $_POST['new_source_notes'] ?? '';

$page_number_in_source = $_POST['page_number_in_source'] ?? '';
$page_description = $_POST['page_description'] ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page'])) {
    // Recoger datos del formulario
    $source_choice = $_POST['source_choice'] ?? 'existing';

    $current_source_id = null;
    $new_source_created = false;

    $pdo->beginTransaction(); // Iniciar transacción

    try {
        // --- Manejo de la Fuente ---
        if ($source_choice === 'new') {
            $new_source_title = trim($_POST['new_source_title'] ?? '');
            if (empty($new_source_title)) {
                throw new Exception("El título de la nueva fuente es obligatorio.");
            }
            // Recoger otros campos de la nueva fuente
            $new_source_author = trim($_POST['new_source_author'] ?? null);
            $new_source_type = trim($_POST['new_source_type'] ?? null);
            $new_source_repo_name = trim($_POST['new_source_repo_name'] ?? null);
            $new_source_repo_ref = trim($_POST['new_source_repo_ref'] ?? null);
            $new_source_date_text = trim($_POST['new_source_date_text'] ?? null);
            $new_source_notes = trim($_POST['new_source_notes'] ?? null);


            $sql_insert_source = "INSERT INTO Sources (project_id, title, author, source_type, repository_name, repository_ref_code, source_date_text, source_notes, source_public_id)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"; // source_public_id temporal
            $stmt_insert_source = $pdo->prepare($sql_insert_source);
            // Usar un placeholder temporal para source_public_id, se actualizará después
            $stmt_insert_source->execute([
                $active_project_id, $new_source_title, $new_source_author, $new_source_type,
                $new_source_repo_name, $new_source_repo_ref, $new_source_date_text, $new_source_notes,
                'TEMP_S_ID'
            ]);
            $current_source_id = $pdo->lastInsertId();
            $new_source_created = true;

            // Generar y actualizar source_public_id
            $source_public_id = generate_public_id('S', (int)$current_source_id);
            $stmt_update_source_public_id = $pdo->prepare("UPDATE Sources SET source_public_id = ? WHERE source_id = ?");
            $stmt_update_source_public_id->execute([$source_public_id, $current_source_id]);

        } elseif ($source_choice === 'existing') {
            $current_source_id = (int)($_POST['existing_source_id'] ?? 0);
            if ($current_source_id <= 0) {
                throw new Exception("Debes seleccionar una fuente existente válida.");
            }
            // Verificar que la fuente seleccionada pertenece al proyecto activo
            $stmt_check_source = $pdo->prepare("SELECT source_id FROM Sources WHERE source_id = ? AND project_id = ?");
            $stmt_check_source->execute([$current_source_id, $active_project_id]);
            if (!$stmt_check_source->fetch()) {
                throw new Exception("La fuente seleccionada no es válida o no pertenece a este proyecto.");
            }
        } else {
            throw new Exception("Opción de fuente no válida.");
        }

        // --- Manejo de la Página ---
        $page_number_in_source = trim($_POST['page_number_in_source'] ?? null);
        $page_description = trim($_POST['page_description'] ?? null);

        // Manejo de la subida de imagen
        if (!isset($_FILES['page_image']) || $_FILES['page_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error en la subida de la imagen o no se ha seleccionado ninguna. Código de error: " . ($_FILES['page_image']['error'] ?? 'No file'));
        }

        $image_file = $_FILES['page_image'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_mime_type = mime_content_type($image_file['tmp_name']);

        if (!in_array($file_mime_type, $allowed_mime_types)) {
            throw new Exception("Tipo de archivo no permitido. Solo se aceptan JPEG, PNG, GIF, WEBP.");
        }

        list($img_width, $img_height) = getimagesize($image_file['tmp_name']);
        if ($img_width === false || $img_height === false) {
            throw new Exception("No se pudo obtener las dimensiones de la imagen.");
        }

        // Insertar la página (con image_filename temporal)
        $sql_insert_page = "INSERT INTO Source_Pages (source_id, project_id, page_number_in_source, page_description, image_mime_type, image_original_width, image_original_height, page_public_id, image_filename)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"; // page_public_id e image_filename temporales
        $stmt_insert_page = $pdo->prepare($sql_insert_page);
        // Usar placeholders temporales, se actualizarán después
        $stmt_insert_page->execute([
            $current_source_id, $active_project_id, $page_number_in_source, $page_description,
            $file_mime_type, $img_width, $img_height, 'TEMP_P_ID', 'TEMP_FILENAME'
        ]);
        $page_id = $pdo->lastInsertId();

        // Generar page_public_id y nombre de archivo final
        $page_public_id = generate_public_id('P', (int)$page_id);
        $file_extension = pathinfo($image_file['name'], PATHINFO_EXTENSION);
        $final_image_filename = $page_public_id . '.' . strtolower($file_extension);

        // Crear directorio para el proyecto si no existe
        $project_upload_dir = UPLOADS_DIR_BASE . 'project_' . $active_project_id . '/';
        if (!is_dir($project_upload_dir)) {
            if (!mkdir($project_upload_dir, 0775, true)) { // Permisos 0775, recursivo
                 throw new Exception("No se pudo crear el directorio de subida para el proyecto: " . $project_upload_dir);
            }
        }
        $destination_path = $project_upload_dir . $final_image_filename;

        if (!move_uploaded_file($image_file['tmp_name'], $destination_path)) {
            throw new Exception("Error al mover el archivo subido a su destino final.");
        }
        // Asegurar permisos correctos para el archivo movido si es necesario (generalmente heredados)
        // chmod($destination_path, 0664);

        // Actualizar la página con el page_public_id y el nombre de archivo final
        $stmt_update_page = $pdo->prepare("UPDATE Source_Pages SET page_public_id = ?, image_filename = ? WHERE page_id = ?");
        $stmt_update_page->execute([$page_public_id, $final_image_filename, $page_id]);

        $pdo->commit(); // Confirmar transacción
        $_SESSION['success_message'] = "Página '" . sanitize_output($page_public_id) . "' añadida con éxito.";
        if ($new_source_created) {
            $_SESSION['success_message'] .= " Nueva fuente '" . sanitize_output($source_public_id) . "' creada.";
        }
        redirect('view_page.php?id=' . $page_id);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack(); // Revertir transacción en caso de error
        }
        $error_message = "Error al añadir la página: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Nueva Página - Veritas</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 700px; margin: auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        form div, fieldset div { margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="file"], textarea, select { width: calc(100% - 18px); padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        textarea { resize: vertical; min-height: 60px; }
        input[type="submit"] { padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        fieldset { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        legend { font-weight: bold; padding: 0 10px; }
        .project-nav a { margin-right: 15px; }
    </style>
    <script>
        // Script para mostrar/ocultar campos de nueva fuente
        function toggleNewSourceFields() {
            var choice = document.querySelector('input[name="source_choice"]:checked').value;
            document.getElementById('existing_source_fields').style.display = (choice === 'existing') ? 'block' : 'none';
            document.getElementById('new_source_fields').style.display = (choice === 'new') ? 'block' : 'none';
        }
        window.onload = toggleNewSourceFields; // Ejecutar al cargar la página
    </script>
</head>
<body>
    <div class="container">
        <div class="project-nav">
            <a href="sources.php">Volver a Fuentes (Proyecto: <?php echo sanitize_output($active_project_name); ?>)</a>
        </div>
        <h1>Añadir Nueva Página</h1>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo sanitize_output($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo sanitize_output($error_message); ?></div>
        <?php endif; ?>

        <form action="add_page.php" method="POST" enctype="multipart/form-data">
            <fieldset>
                <legend>Información de la Fuente</legend>
                <div>
                    <input type="radio" id="source_choice_existing" name="source_choice" value="existing" <?php echo ($source_choice === 'existing' ? 'checked' : ''); ?> onclick="toggleNewSourceFields()">
                    <label for="source_choice_existing" style="display:inline; font-weight:normal;">Usar Fuente Existente</label>
                </div>
                <div>
                    <input type="radio" id="source_choice_new" name="source_choice" value="new" <?php echo ($source_choice === 'new' ? 'checked' : ''); ?> onclick="toggleNewSourceFields()">
                    <label for="source_choice_new" style="display:inline; font-weight:normal;">Crear Nueva Fuente</label>
                </div>

                <div id="existing_source_fields">
                    <label for="existing_source_id">Seleccionar Fuente:</label>
                    <select id="existing_source_id" name="existing_source_id">
                        <option value="">-- Elija una fuente --</option>
                        <?php foreach ($existing_sources as $source): ?>
                            <option value="<?php echo $source['source_id']; ?>" <?php echo ($selected_source_id == $source['source_id'] ? 'selected' : ''); ?>>
                                <?php echo sanitize_output($source['source_public_id'] . ' - ' . $source['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="new_source_fields" style="display:none;">
                    <label for="new_source_title">Título de la Nueva Fuente:</label>
                    <input type="text" id="new_source_title" name="new_source_title" value="<?php echo sanitize_output($new_source_title); ?>">
                    
                    <label for="new_source_author">Autor:</label>
                    <input type="text" id="new_source_author" name="new_source_author" value="<?php echo sanitize_output($new_source_author); ?>">

                    <label for="new_source_type">Tipo de Fuente:</label>
                    <input type="text" id="new_source_type" name="new_source_type" value="<?php echo sanitize_output($new_source_type); ?>" placeholder="Ej: Registro Parroquial, Protocolo Notarial">
                    
                    <label for="new_source_repo_name">Nombre del Repositorio:</label>
                    <input type="text" id="new_source_repo_name" name="new_source_repo_name" value="<?php echo sanitize_output($new_source_repo_name); ?>">

                    <label for="new_source_repo_ref">Referencia en Repositorio:</label>
                    <input type="text" id="new_source_repo_ref" name="new_source_repo_ref" value="<?php echo sanitize_output($new_source_repo_ref); ?>">
                    
                    <label for="new_source_date_text">Fecha de la Fuente (texto):</label>
                    <input type="text" id="new_source_date_text" name="new_source_date_text" value="<?php echo sanitize_output($new_source_date_text); ?>" placeholder="Ej: ca. 1880, Siglo XVII">

                    <label for="new_source_notes">Notas de la Fuente:</label>
                    <textarea id="new_source_notes" name="new_source_notes"><?php echo sanitize_output($new_source_notes); ?></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>Información de la Página</legend>
                <div>
                    <label for="page_number_in_source">Número/Identificador de Página en Fuente:</label>
                    <input type="text" id="page_number_in_source" name="page_number_in_source" value="<?php echo sanitize_output($page_number_in_source); ?>" placeholder="Ej: f. 12r, p. 5">
                </div>
                <div>
                    <label for="page_image">Archivo de Imagen de la Página:</label>
                    <input type="file" id="page_image" name="page_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                </div>
                <div>
                    <label for="page_description">Descripción de la Página (opcional):</label>
                    <textarea id="page_description" name="page_description"><?php echo sanitize_output($page_description); ?></textarea>
                </div>
            </fieldset>
            
            <div>
                <input type="submit" name="add_page" value="Añadir Página">
            </div>
        </form>
    </div>
</body>
</html>