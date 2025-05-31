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
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 95%; margin: auto; display: flex; gap: 20px; }
        .main-content { flex: 3; }
        .sidebar { flex: 1; border-left: 1px solid #ccc; padding-left: 20px; }
        
        .page-header { margin-bottom: 20px; }
        .page-header h1 { margin-bottom: 5px; }
        .page-info { margin-bottom: 15px; font-size: 0.9em; color: #555; }
        
        /* Contenedor para la imagen y el canvas de dibujo */
        .image-marking-container {
            position: relative; /* Para que el canvas se posicione sobre la imagen */
            width: fit-content; /* Ajustar al tamaño de la imagen */
            border: 1px solid #ccc;
            margin-bottom: 20px;
            /* Es importante que las dimensiones de la imagen y el canvas coincidan */
        }
        #pageImage {
            display: block; /* Evitar espacio extra debajo de la imagen */
            max-width: 100%; /* Hacerla responsive */
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
        #mentionFormContainer label { display: block; margin-top: 10px; }
        #mentionFormContainer input[type="text"], #mentionFormContainer textarea { width: calc(100% - 16px); padding: 6px; margin-top:5px; }
        #mentionFormContainer textarea { min-height: 60px; }
        #mentionFormContainer button { margin-top: 10px; padding: 8px 15px; }
        
        .actions a { margin-right: 10px; text-decoration: none; padding: 8px 12px; background-color: #007bff; color: white; border-radius: 4px; display:inline-block; margin-bottom:10px;}
        .actions a:hover { background-color: #0056b3; }
        .project-nav a { margin-right: 15px; margin-left: 15px; }

        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .mentions-list { list-style-type: none; padding: 0; }
        .mentions-list li { background-color: #f9f9f9; border: 1px solid #eee; padding: 8px; margin-bottom: 5px; border-radius: 3px; font-size:0.9em; }
        .mentions-list li strong { color: #0056b3; }
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
                        Identificador en la fuente: <?php echo sanitize_output($page_data['page_number_in_source'] . ' (' . $page_data['page_public_id'] .')' ?? 'N/A'); ?>
                        | <a href="edit_page.php?id=<?php echo $page_data['page_id']; ?>">Editar detalles de la página</a>
                    </div>
                </div>

                <div class="image-marking-container">
                    <img id="pageImage" src="<?php echo sanitize_output($image_web_path); ?>" 
                         alt="Imagen de la página <?php echo sanitize_output($page_data['page_public_id']); ?>"
                         data-original-width="<?php echo $page_data['image_original_width']; ?>"
                         data-original-height="<?php echo $page_data['image_original_height']; ?>">
                    <canvas id="drawingCanvas"></canvas>
                </div>
                
                <div id="mentionFormContainer" style="display:none;">
                    <h3>Añadir nueva mención</h3>
                    <form id="mentionForm">
                        <input type="hidden" id="mentionCoordsX" name="coords_x">
                        <input type="hidden" id="mentionCoordsY" name="coords_y">
                        <input type="hidden" id="mentionCoordsW" name="coords_w">
                        <input type="hidden" id="mentionCoordsH" name="coords_h">
                        
                        <div>
                            <label for="mentionText">Texto literal de la mención:</label>
                            <textarea id="mentionText" name="mention_text" rows="3" required></textarea>
                        </div>
                        <div>
                            <label for="mentionType">Sugerencia de tipo de entidad (opcional):</label>
                            <input type="text" id="mentionType" name="mention_type" placeholder="Ej: Persona, Lugar, Fecha">
                        </div>
                        <button type="submit">Guardar mención</button>
                        <button type="button" id="cancelMention">Cancelar</button>
                    </form>
                    <div id="mentionSaveStatus"></div>
                </div>

            <?php else: ?>
                <p>La página solicitada no pudo ser cargada.</p>
            <?php endif; ?>
        </div> <div class="sidebar">
            <h2>Menciones guardadas</h2>
            <ul id="mentionsList" class="mentions-list">
                <?php if (count($existing_mentions) > 0): ?>
                    <?php foreach ($existing_mentions as $mention): 
    $coords = json_decode($mention['coordinates_on_image'], true);
    // var_dump($coords); // Para depurar en PHP
    $coordX = $coordY = $coordW = $coordH = ''; // Inicializar
    if (is_array($coords)) {
        $coordX = isset($coords['x']) ? $coords['x'] : '';
        $coordY = isset($coords['y']) ? $coords['y'] : '';
        $coordW = isset($coords['width']) ? $coords['width'] : '';
        $coordH = isset($coords['height']) ? $coords['height'] : '';
    }
?>

                        <li data-mention-id="<?php echo $mention['mention_id']; ?>" 
                            data-coords-x="<?php echo $coords['x'] ?? ''; ?>"
                            data-coords-y="<?php echo $coords['y'] ?? ''; ?>"
                            data-coords-w="<?php echo $coords['width'] ?? ''; ?>"
                            data-coords-h="<?php echo $coords['height'] ?? ''; ?>">
                            <strong><?php echo sanitize_output($mention['mention_public_id']); ?>:</strong> 
                            "<?php echo sanitize_output(mb_strimwidth($mention['mention_string_literal'], 0, 50, "...")); ?>"
                            <em>(<?php echo sanitize_output($mention['entity_type_suggestion'] ?? 'Sin tipo'); ?>)</em>
                            </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li id="noMentionsMessage">Todavía no hay menciones guardadas para esta página.</li>
                <?php endif; ?>
            </ul>
        </div> </div> <script>
document.addEventListener('DOMContentLoaded', function() {
    const image = document.getElementById('pageImage');
    const canvas = document.getElementById('drawingCanvas');
    const ctx = canvas.getContext('2d');
    const mentionFormContainer = document.getElementById('mentionFormContainer');
    const mentionForm = document.getElementById('mentionForm');
    const mentionText = document.getElementById('mentionText');
    const mentionType = document.getElementById('mentionType');
    const cancelMentionButton = document.getElementById('cancelMention');
    const mentionSaveStatus = document.getElementById('mentionSaveStatus');
    const mentionsList = document.getElementById('mentionsList');
    const noMentionsMessage = document.getElementById('noMentionsMessage');
    
    
    let isDrawing = false;
    let rect = {}; // Para almacenar las coordenadas del rectángulo actual
    let currentDrawnRect = null; // Para almacenar el último rectángulo completado
    
    // Dimensiones originales de la imagen (desde los atributos data-*)
    const originalWidth = parseInt(image.dataset.originalWidth, 10);
    const originalHeight = parseInt(image.dataset.originalHeight, 10);
    
    // Almacenar las menciones existentes para dibujarlas
    let savedMentions = [];
    document.querySelectorAll('#mentionsList li[data-mention-id]').forEach(li => {
        savedMentions.push({
            id: li.dataset.mentionId,
            text: li.querySelector('strong').nextSibling.textContent.trim().replace(/^"|"$/g, ''), // un poco hacky
            type: li.querySelector('em').textContent.replace(/^\(|\)$/g, ''),
            coords: {
                x: parseFloat(li.dataset.coordsX),
                y: parseFloat(li.dataset.coordsY),
                width: parseFloat(li.dataset.coordsW),
                height: parseFloat(li.dataset.coordsH)
            }
        });
    });
    
    function resizeCanvas() {
        // Hacer que el canvas tenga las mismas dimensiones mostradas de la imagen
        canvas.width = image.offsetWidth;
        canvas.height = image.offsetHeight;
        console.log(`Canvas resized to: ${canvas.width}x${canvas.height}`);
        console.log(`Image original: ${originalWidth}x${originalHeight}`);
        console.log(`Image displayed: ${image.offsetWidth}x${image.offsetHeight}`);
        drawExistingMentions(); // Redibujar menciones al cambiar tamaño
    }
    
    // Función para escalar coordenadas del canvas (mostradas) a coordenadas de imagen original
    function scaleCoordsToOriginal(mentionCanvasX, mentionCanvasY, mentionCanvasWidth, mentionCanvasHeight) {
        // originalWidth y originalHeight son las dimensiones de la imagen completa
        // canvas.width y canvas.height son las dimensiones del canvas (imagen mostrada)
        if (canvas.width === 0 || canvas.height === 0) return { x:0, y:0, width:0, height:0 }; // Evitar división por cero

        const scaleFactorX = originalWidth / canvas.width;
        const scaleFactorY = originalHeight / canvas.height;

        return {
            x: Math.round(mentionCanvasX * scaleFactorX),
            y: Math.round(mentionCanvasY * scaleFactorY),
            width: Math.round(mentionCanvasWidth * scaleFactorX),
            height: Math.round(mentionCanvasHeight * scaleFactorY)
        };
    }
    
    // Función para escalar coordenadas de imagen original a coordenadas del canvas (mostradas)
    function scaleCoordsToCanvas(mentionOriginalX, mentionOriginalY, mentionOriginalWidth, mentionOriginalHeight) {
        // originalWidth y originalHeight son las dimensiones de la imagen completa (variables globales en tu script)
        // canvas.width y canvas.height son las dimensiones del canvas (imagen mostrada)
        if (originalWidth === 0 || originalHeight === 0) return { x:0, y:0, width:0, height:0 }; // Evitar división por cero

        const scaleFactorX = canvas.width / originalWidth;
        const scaleFactorY = canvas.height / originalHeight;
        
        return {
            x: Math.round(mentionOriginalX * scaleFactorX),
            y: Math.round(mentionOriginalY * scaleFactorY),
            width: Math.round(mentionOriginalWidth * scaleFactorX),
            height: Math.round(mentionOriginalHeight * scaleFactorY)
        };
    }
    
    function drawRect(x, y, w, h, color = 'red', lineWidth = 2) {
        ctx.strokeStyle = color;
        ctx.lineWidth = lineWidth;
        ctx.strokeRect(x, y, w, h);
    }
    
    function drawExistingMentions() {
        ctx.clearRect(0, 0, canvas.width, canvas.height); // Limpiar canvas
        savedMentions.forEach(mention => {
                    // Asegurarse de que las coordenadas son números válidos
        const cx = parseFloat(mention.coords.x);
        const cy = parseFloat(mention.coords.y);
        const cw = parseFloat(mention.coords.width);
        const ch = parseFloat(mention.coords.height);

        if (!isNaN(cx) && !isNaN(cy) && !isNaN(cw) && !isNaN(ch) && cw > 0 && ch > 0) {
            const scaled = scaleCoordsToCanvas(cx, cy, cw, ch);
            drawRect(scaled.x, scaled.y, scaled.width, scaled.height, 'rgba(0, 0, 255, 0.7)', 2); // Azul para existentes
        } else {
            console.warn(`ID ${mention.id}: Coordenadas inválidas o dimensiones cero, no se dibuja.`, mention.coords);
        }

        });
    }
    
    // Esperar a que la imagen se cargue para obtener sus dimensiones correctas
    if (image.complete) {
        resizeCanvas();
    } else {
        image.onload = resizeCanvas;
    }
    // También redimensionar el canvas si la ventana cambia de tamaño
    window.addEventListener('resize', resizeCanvas);


    canvas.addEventListener('mousedown', function(e) {
        if (mentionFormContainer.style.display === 'block') return; // No dibujar si el form está abierto

        isDrawing = true;
        // Coordenadas relativas al canvas
        rect.startX = e.offsetX;
        rect.startY = e.offsetY;
    });

    canvas.addEventListener('mousemove', function(e) {
        if (!isDrawing || mentionFormContainer.style.display === 'block') return;

        ctx.clearRect(0, 0, canvas.width, canvas.height); // Limpiar canvas para redibujar
        drawExistingMentions(); // Redibujar menciones existentes
        
        const currentX = e.offsetX;
        const currentY = e.offsetY;
        const width = currentX - rect.startX;
        const height = currentY - rect.startY;
        
        drawRect(rect.startX, rect.startY, width, height, 'rgba(255, 0, 0, 0.5)', 2); // Rojo semi-transparente para el nuevo
    });

    canvas.addEventListener('mouseup', function(e) {
        if (!isDrawing || mentionFormContainer.style.display === 'block') return;
        isDrawing = false;
        
        const currentX = e.offsetX;
        const currentY = e.offsetY;

        // Asegurar que el ancho y alto sean positivos y calcular el x,y superior izquierdo
        const x = Math.min(rect.startX, currentX);
        const y = Math.min(rect.startY, currentY);
        const width = Math.abs(currentX - rect.startX);
        const height = Math.abs(currentY - rect.startY);

        if (width < 5 || height < 5) { // Ignorar rectángulos muy pequeños
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawExistingMentions();
            return;
        }
        
        currentDrawnRect = { x, y, width, height }; // Coordenadas relativas al canvas actual
        console.log("Canvas coords:", currentDrawnRect);

        // Dibujar el rectángulo final y luego mostrar el formulario
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawExistingMentions();
        drawRect(currentDrawnRect.x, currentDrawnRect.y, currentDrawnRect.width, currentDrawnRect.height, 'red', 3); // Rojo más opaco

        // Llenar campos ocultos del formulario con coordenadas del canvas
        document.getElementById('mentionCoordsX').value = currentDrawnRect.x;
        document.getElementById('mentionCoordsY').value = currentDrawnRect.y;
        document.getElementById('mentionCoordsW').value = currentDrawnRect.width;
        document.getElementById('mentionCoordsH').value = currentDrawnRect.height;

        mentionFormContainer.style.display = 'block';
        mentionText.value = ''; // Limpiar campo de texto
        mentionType.value = ''; // Limpiar campo de tipo
        mentionText.focus();
        mentionSaveStatus.textContent = '';
    });

    mentionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        mentionSaveStatus.textContent = 'Guardando...';

        // Escalar las coordenadas del canvas (currentDrawnRect) a las de la imagen original
        const originalCoords = scaleCoordsToOriginal(
            parseFloat(document.getElementById('mentionCoordsX').value),
            parseFloat(document.getElementById('mentionCoordsY').value),
            parseFloat(document.getElementById('mentionCoordsW').value),
            parseFloat(document.getElementById('mentionCoordsH').value)
        );
        console.log("Original Coords to save:", originalCoords);

        const data = {
            page_id: <?php echo $page_data['page_id']; ?>,
            coordinates: originalCoords,
            mention_string_literal: mentionText.value,
            entity_type_suggestion: mentionType.value
        };

        fetch('add_mention.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                mentionSaveStatus.textContent = 'Mención guardada: ' + result.mention.mention_public_id;
                mentionForm.reset();
                mentionFormContainer.style.display = 'none';
                
                // Añadir a la lista visual y al array para redibujar
                const newMention = result.mention;
                // Coordenadas devueltas son las originales, necesitamos las del canvas para currentDrawnRect
                newMention.coordsForDrawing = currentDrawnRect; 
                savedMentions.push({ // Guardar coordenadas originales para consistencia
                    id: newMention.mention_id,
                    text: newMention.mention_string_literal,
                    type: newMention.entity_type_suggestion,
                    coords: newMention.coordinates_on_image 
                });

                const listItem = document.createElement('li');
                listItem.dataset.mentionId = newMention.mention_id;
                // Guardar coordenadas originales en data-attributes
                listItem.dataset.coordsX = newMention.coordinates_on_image.x;
                listItem.dataset.coordsY = newMention.coordinates_on_image.y;
                listItem.dataset.coordsW = newMention.coordinates_on_image.width;
                listItem.dataset.coordsH = newMention.coordinates_on_image.height;

                listItem.innerHTML = `<strong>${sanitize_output(newMention.mention_public_id)}:</strong> 
                                      "${sanitize_output(newMention.mention_string_literal.substring(0,50))}${newMention.mention_string_literal.length > 50 ? '...' : ''}"
                                      <em>(${sanitize_output(newMention.entity_type_suggestion || 'Sin tipo')})</em>`;
                mentionsList.appendChild(listItem);
                if(noMentionsMessage) noMentionsMessage.style.display = 'none';

                // Redibujar todo, incluyendo la nueva mención que ahora es "existente"
                drawExistingMentions();
                currentDrawnRect = null; // Resetear el rectángulo actual
            } else {
                mentionSaveStatus.textContent = 'Error: ' + result.message;
            }
        })
        .catch(error => {
            mentionSaveStatus.textContent = 'Error de red al guardar la mención.';
            console.error('Error:', error);
        });
    });

    cancelMentionButton.addEventListener('click', function() {
        mentionFormContainer.style.display = 'none';
        currentDrawnRect = null;
        // Redibujar para quitar el rectángulo rojo temporal si no se guardó
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawExistingMentions();
    });

    // Función para sanitizar salida en JS (muy básica, para el ejemplo)
    function sanitize_output(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }
    
    // Permitir hacer hover sobre la lista de menciones y resaltar el recuadro en el canvas
    mentionsList.addEventListener('mouseover', function(e) {
        const listItem = e.target.closest('li[data-mention-id]');
        if (listItem) {
            const coords = {
                x: parseFloat(listItem.dataset.coordsX),
                y: parseFloat(listItem.dataset.coordsY),
                width: parseFloat(listItem.dataset.coordsW),
                height: parseFloat(listItem.dataset.coordsH)
            };
            if (!isNaN(coords.x)) {
                const scaled = scaleCoordsToCanvas(coords.x, coords.y, coords.width, coords.height);
                drawRect(scaled.x, scaled.y, scaled.width, scaled.height, 'rgba(255, 165, 0, 0.8)', 3); // Naranja para hover
            }
        }
    });

    mentionsList.addEventListener('mouseout', function(e) {
        const listItem = e.target.closest('li[data-mention-id]');
        if (listItem) {
            // Redibujar todas las menciones existentes para quitar el resaltado hover
            drawExistingMentions();
             // Si había un rectángulo rojo de "nueva mención" (no guardada aun), redibujarlo
            if (currentDrawnRect && mentionFormContainer.style.display === 'block') {
                 drawRect(currentDrawnRect.x, currentDrawnRect.y, currentDrawnRect.width, currentDrawnRect.height, 'red', 3);
            }
        }
    });

});
</script>

</body>

</html>