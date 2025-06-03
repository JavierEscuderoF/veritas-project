<?php
// src/public/view_page.php
require_once '../core/bootstrap.php'; // Carga config, functions, y db_connection ($pdo)

$active_project_id = get_active_project_id_or_redirect();
$active_project_name = get_active_project_name($pdo, $active_project_id);

$page_id_to_view = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$page_data = null;
$source_data = null;
$image_web_path = '';
$existing_mentions = []; // Para cargar menciones existentes

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

    // Cargar menciones existentes para esta página
    $stmt_mentions = $pdo->prepare("SELECT * FROM Source_Mentions WHERE page_id = ? ORDER BY created_at ASC");
    $stmt_mentions->execute([$page_id_to_view]);
    $existing_mentions = $stmt_mentions->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', "Error al cargar la página o sus menciones: " . $e->getMessage());
    error_log("Error en view_page.php: " . $e->getMessage());
    redirect('list_pages.php');
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver página <?php echo $page_data ? sanitize_output($page_data['page_public_id']) : ''; ?> - Veritas</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }

        .container {
            max-width: 95%;
            margin: auto;
            display: flex;
            gap: 20px;
        }

        .main-content {
            flex: 3;
        }

        .sidebar {
            flex: 1;
            border-left: 1px solid #ccc;
            padding-left: 20px;
        }

        .page-header {
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin-bottom: 5px;
        }

        .page-info {
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #555;
        }

        /* Contenedor para la imagen y el canvas de dibujo */
        .image-marking-container {
            position: relative;
            /* Para que el canvas se posicione sobre la imagen */
            width: fit-content;
            /* Ajustar al tamaño de la imagen */
            border: 1px solid #ccc;
            margin-bottom: 20px;
            /* Es importante que las dimensiones de la imagen y el canvas coincidan */
        }

        #pageImage {
            display: block;
            /* Evitar espacio extra debajo de la imagen */
            max-width: 100%;
            /* Hacerla responsive */
            height: auto;
        }

        #drawingCanvas {
            position: absolute;
            top: 0;
            left: 0;
            cursor: crosshair;
            /* Las dimensiones se ajustarán con JS */
        }

        /* Formulario para añadir mención (inicialmente oculto) */
        #mentionFormContainer {
            border: 1px solid #007bff;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            background-color: #f0f8ff;
        }

        #mentionFormContainer label {
            display: block;
            margin-top: 10px;
        }

        #mentionFormContainer input[type="text"],
        #mentionFormContainer textarea {
            width: calc(100% - 16px);
            padding: 6px;
            margin-top: 5px;
        }

        #mentionFormContainer textarea {
            min-height: 60px;
        }

        #mentionFormContainer button {
            margin-top: 10px;
            padding: 8px 15px;
        }

        .actions a {
            margin-right: 10px;
            text-decoration: none;
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .actions a:hover {
            background-color: #0056b3;
        }

        .project-nav a {
            margin-right: 15px;
            margin-left: 15px;
        }

        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .mentions-list {
            list-style-type: none;
            padding: 0;
        }

        .mentions-list li {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 3px;
            font-size: 0.9em;
        }

        .mentions-list li strong {
            color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="main-content">
            <div class="project-nav">
                <a href="projects.php">Cambiar proyecto</a> |
                <a href="sources.php">Fuentes del proyecto «<?php echo sanitize_output($active_project_name); ?>»</a>
                <?php if ($page_data): ?>
                    | <a href="list_pages.php?source_id=<?php echo $page_data['source_id']; ?>">Páginas de la fuente «<?php echo sanitize_output($page_data['source_title'] . ' (' . $page_data['source_public_id'] . ')'); ?>»</a>
                <?php endif; ?>
            </div>

            <?php display_flash_messages(); ?>

            <?php if ($page_data): ?>
                <div class="page-header">
                    <h1>Marcar menciones en la página</h1>
                    <div class="page-info">
                        Fuente: <?php echo sanitize_output($page_data['source_title'] . ' (' . $page_data['source_public_id'] . ')'); ?><br>
                        Identificador en la fuente: <?php echo sanitize_output($page_data['page_number_in_source'] . ' (' . $page_data['page_public_id'] . ')' ?? 'N/A'); ?>
                        | <a href="edit_page.php?id=<?php echo $page_data['page_id']; ?>">Editar detalles de la página</a>
                    </div>
                </div>

                <div class="image-marking-container"
                    data-page-id="<?php echo $page_data['page_id']; ?>"
                    data-project-id="<?php echo $page_data['project_id']; ?>">
                    <img id="pageImage" src="<?php echo sanitize_output($image_web_path); ?>"
                        alt="Imagen de la página <?php echo sanitize_output($page_data['page_public_id']); ?>"
                        data-original-width="<?php echo $page_data['image_original_width']; ?>"
                        data-original-height="<?php echo $page_data['image_original_height']; ?>">
                    <canvas id="drawingCanvas"></canvas>
                </div>

                <div id="mentionFormContainer" style="display:none; border: 1px solid #007bff; padding: 15px; margin-top: 20px; border-radius: 5px; background-color: #f0f8ff;">
                    <h3>Añadir o editar Mención</h3>
                    <form id="mentionForm">
                        <div>
                            <label for="mentionText">Texto literal de la mención:</label>
                            <textarea id="mentionText" name="mention_text" rows="3" required></textarea>
                        </div>
                        <div>
                            <label for="mentionType">Sugerencia de tipo de entidad (opcional):</label>
                            <input type="text" id="mentionType" name="mention_type" placeholder="Ej: Persona, Lugar, Fecha">
                        </div>
                        <button type="submit" id="saveMentionButton">Guardar</button>
                        <button type="button" id="cancelMentionButton">Cancelar</button>
                    </form>
                    <div id="mentionSaveStatus" style="margin-top:10px;"></div>
                </div>
            <?php else: ?>
                <p>La página solicitada no pudo ser cargada.</p>
            <?php endif; ?>
        </div>
        <div class="sidebar">
            <h2>Menciones guardadas</h2>
            <ul id="mentionsList" class="mentions-list">
                <?php if (count($existing_mentions) > 0): ?>
                    <?php foreach ($existing_mentions as $mention): ?>
                        <li data-mention-id="<?php echo $mention['mention_id']; ?>"
                            data-coordinates='<?php echo sanitize_output($mention['coordinates_on_image']); ?>'>
                            <strong><?php echo sanitize_output($mention['mention_public_id']); ?>:</strong>
                            "<?php echo sanitize_output(mb_strimwidth($mention['mention_string_literal'], 0, 50, "...")); ?>"
                            <em>(<?php echo sanitize_output($mention['entity_type_suggestion'] ?? 'Sin tipo'); ?>)</em>
                            <button class="edit-mention-btn"
                                data-mention-id="<?php echo $mention['mention_id']; ?>"
                                style="margin-left: 5px; color: blue; background:none; border:none; cursor:pointer; font-size:0.8em;">✎</button>
                            <button class="delete-mention-btn" data-mention-id="<?php echo $mention['mention_id']; ?>" data-mention-public-id="<?php echo sanitize_output($mention['mention_public_id']); ?>" style="margin-left: 10px; color: red; background: none; border: none; cursor: pointer; font-size:0.8em;">✕</button>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li id="noMentionsMessage">Todavía no hay menciones guardadas para esta página.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <script src="js/view_page_mentions.js" defer></script>
</body>

</html>