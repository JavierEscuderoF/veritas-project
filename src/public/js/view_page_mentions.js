document.addEventListener('DOMContentLoaded', function () {
    const image = document.getElementById('pageImage');
    const canvas = document.getElementById('drawingCanvas');
    const ctx = canvas.getContext('2d');

    const mentionFormContainer = document.getElementById('mentionFormContainer');
    const mentionForm = document.getElementById('mentionForm');
    const mentionText = document.getElementById('mentionText');
    const mentionType = document.getElementById('mentionType');
    const saveMentionButton = document.getElementById('saveMentionButton');
    const cancelMentionButton = document.getElementById('cancelMentionButton');
    const mentionSaveStatus = document.getElementById('mentionSaveStatus');
    const mentionsList = document.getElementById('mentionsList');
    const noMentionsMessage = document.getElementById('noMentionsMessage');
    const imageMarkingContainer = document.querySelector('.image-marking-container');
    const currentPageId = parseInt(imageMarkingContainer.dataset.pageId, 10);
    const currentProjectId = parseInt(imageMarkingContainer.dataset.projectId, 10);

    // Variable global para saber si estamos editando o creando
    let editingMentionId = null;
    let isDrawing = false; // True mientras el botón del ratón está presionado y se está dibujando UN segmento
    let currentRectDef = {}; // Para almacenar startX, startY, width, height del segmento que se está dibujando

    // Array para las partes (rectángulos del canvas) de la mención que se está creando/editando actualmente
    //let currentMentionPartsCanvas = [];
    let currentMentionPartsOriginal = [];

    // Array para las menciones ya guardadas (cargadas de la BD)
    // Cada elemento: { id, text, type, coordinates_original: [{x,y,width,height}, ...] }
    let savedMentions = [];

    const originalWidth = parseInt(image.dataset.originalWidth, 10);
    const originalHeight = parseInt(image.dataset.originalHeight, 10);

    // --- INICIALIZACIÓN DE MENCIONES GUARDADAS ---
    document.querySelectorAll('#mentionsList li[data-mention-id]').forEach(li => {
        try {
            const coordsArray = JSON.parse(li.dataset.coordinates);
            if (Array.isArray(coordsArray)) {
                savedMentions.push({
                    id: li.dataset.mentionId,
                    text: li.querySelector('strong').nextSibling.textContent.trim().replace(/^"|"$/g, ''),
                    type: li.querySelector('em').textContent.replace(/^\(|\)$/g, ''),
                    coordinates_original: coordsArray
                });
            }
        } catch (e) {
            console.error("Error parseando JSON de coordenadas para mención ID:", li.dataset.mentionId, e);
        }
    });
    // console.log('Mentions cargadas:', savedMentions);

    // --- FUNCIONES DE ESCALADO (sin cambios) ---
    function scaleCoordsToCanvas(origX, origY, origW, origH) {
        if (originalWidth === 0 || originalHeight === 0) return {
            x: 0,
            y: 0,
            width: 0,
            height: 0
        };
        const scaleX = canvas.width / originalWidth;
        const scaleY = canvas.height / originalHeight;
        return {
            x: Math.round(origX * scaleX),
            y: Math.round(origY * scaleY),
            width: Math.round(origW * scaleX),
            height: Math.round(origH * scaleY)
        };
    }

    function scaleCoordsToOriginal(canvasX, canvasY, canvasW, canvasH) {
        if (canvas.width === 0 || canvas.height === 0) return {
            x: 0,
            y: 0,
            width: 0,
            height: 0
        };
        const scaleX = originalWidth / canvas.width;
        const scaleY = originalHeight / canvas.height;
        return {
            x: Math.round(canvasX * scaleX),
            y: Math.round(canvasY * scaleY),
            width: Math.round(canvasW * scaleX),
            height: Math.round(canvasH * scaleY)
        };
    }

    // --- FUNCIONES DE DIBUJO ---
    function drawRect(x, y, w, h, color = 'red', lineWidth = 2) {
        ctx.strokeStyle = color;
        ctx.lineWidth = lineWidth;
        ctx.strokeRect(x, y, w, h);
    }

    function redrawAllElements() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // 1. Dibujar menciones ya guardadas (azul)
        savedMentions.forEach(mention => {
            if (Array.isArray(mention.coordinates_original)) {
                mention.coordinates_original.forEach(coordSet => { // coordSet tiene x,y,width,height originales
                    const scaled = scaleCoordsToCanvas(coordSet.x, coordSet.y, coordSet.width, coordSet.height);
                    if (scaled.width > 0 && scaled.height > 0) {
                        drawRect(scaled.x, scaled.y, scaled.width, scaled.height, 'rgba(0, 0, 255, 0.7)', 2);
                    }
                });
            }
        });

        // 2. Dibujar partes de la mención actual en proceso (naranja)
        /*currentMentionPartsCanvas.forEach(part => { // part tiene x,y,width,height del canvas
            drawRect(part.x, part.y, part.width, part.height, 'rgb(255, 166, 0)', 2);
        });*/

        currentMentionPartsOriginal.forEach(part => {
            const scaledPart = scaleCoordsToCanvas(part.x, part.y, part.width, part.height);
            drawRect(scaledPart.x, scaledPart.y, scaledPart.width, scaledPart.height, 'green', 2);
        });

        // 3. Si se está dibujando un nuevo rectángulo activamente (rojo semitransparente)
        if (isDrawing && currentRectDef.width && currentRectDef.height) {
            drawRect(currentRectDef.startX, currentRectDef.startY, currentRectDef.width, currentRectDef.height, 'rgb(255, 0, 0)', 1);
        }
    }

    function resetAndHideMentionForm() {
        //currentMentionPartsCanvas = []; // Limpiar partes de la mención actual
        currentMentionPartsOriginal = [];
        isDrawing = false;
        currentRectDef = {};
        mentionForm.reset(); // Limpiar campos del formulario
        mentionFormContainer.style.display = 'none';
        mentionSaveStatus.textContent = '';
        redrawAllElements(); // Quitar cualquier rectángulo naranja/rojo
    }

    // --- LÓGICA DE CANVAS Y EVENTOS DE DIBUJO ---
    function adaptCanvasToImageSize() {
        if (!image || !canvas) return;
        canvas.width = image.offsetWidth;
        canvas.height = image.offsetHeight;
        redrawAllElements();
    }

    if (image.complete && image.naturalWidth > 0) {
        adaptCanvasToImageSize();
    } else {
        image.onload = adaptCanvasToImageSize;
    }
    window.addEventListener('resize', adaptCanvasToImageSize);

    canvas.addEventListener('mousedown', function (e) {

        isDrawing = true;
        currentRectDef.startX = e.offsetX;
        currentRectDef.startY = e.offsetY;
        currentRectDef.width = 0;
        currentRectDef.height = 0;
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!isDrawing) return;
        currentRectDef.width = e.offsetX - currentRectDef.startX;
        currentRectDef.height = e.offsetY - currentRectDef.startY;
        redrawAllElements();
    });

    canvas.addEventListener('mouseup', function (e) {
        // Si el formulario no está visible, este es el primer rectángulo, así que mostrarlo.
        if (mentionFormContainer.style.display === 'none') {
            mentionFormContainer.style.display = 'block';
            mentionText.focus(); // Opcional: enfocar el primer campo
        }

        if (!isDrawing) return;
        isDrawing = false;

        const x = Math.min(currentRectDef.startX, currentRectDef.startX + currentRectDef.width);
        const y = Math.min(currentRectDef.startY, currentRectDef.startY + currentRectDef.height);
        const finalWidth = Math.abs(currentRectDef.width);
        const finalHeight = Math.abs(currentRectDef.height);

        const originalCoordsPart = scaleCoordsToOriginal(
            x,
            y,
            finalWidth,
            finalHeight
        );

        if (finalWidth >= 5 && finalHeight >= 5) {
            /*currentMentionPartsCanvas.push({
                x: x,
                y: y,
                width: finalWidth,
                height: finalHeight
            });*/
            currentMentionPartsOriginal.push(originalCoordsPart);
        }
        currentRectDef = {}; // Resetear para el próximo posible dibujo
        redrawAllElements(); // Mostrar la parte recién añadida en naranja
    });

    // --- MANEJO DEL FORMULARIO DE MENCIÓN ---
    mentionForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (currentMentionPartsOriginal.length === 0) {
            mentionSaveStatus.textContent = 'Error: Debes dibujar al menos un rectángulo para la mención.';
            mentionSaveStatus.style.color = 'red';
            return;
        }
        mentionSaveStatus.textContent = 'Guardando...';
        mentionSaveStatus.style.color = 'black';

        /*const coordinatesToSaveOriginal = currentMentionPartsCanvas.map(part =>
            scaleCoordsToOriginal(part.x, part.y, part.width, part.height)
        );*/

        const dataToSubmit = {
            page_id: currentPageId,
            coordinates: currentMentionPartsOriginal,
            mention_string_literal: mentionText.value,
            entity_type_suggestion: mentionType.value
        };

        fetch('add_mention.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(dataToSubmit)
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Error del servidor al guardar.')
                    });
                }
                return response.json();
            })
            .then(result => {
                if (result.success && result.mention) {
                    mentionSaveStatus.textContent = ''; // Limpiar estado

                    // Añadir a la lista visual y al array 'savedMentions'
                    savedMentions.push({
                        id: result.mention.mention_id, // El ID numérico
                        public_id: result.mention.mention_public_id, // El ID público "M123"
                        text: result.mention.mention_string_literal,
                        type: result.mention.entity_type_suggestion,
                        coordinates_original: result.mention.coordinates_on_image // Array de coords originales
                    });

                    const listItem = document.createElement('li');
                    listItem.dataset.mentionId = result.mention.mention_id;
                    listItem.dataset.coordinates = JSON.stringify(result.mention.coordinates_on_image); // Guardar el array JSON
                    listItem.innerHTML = `<strong>${sanitize_output(result.mention.mention_public_id)}:</strong> 
                                      "${sanitize_output(result.mention.mention_string_literal.substring(0, 50))}${result.mention.mention_string_literal.length > 50 ? '...' : ''}"
                                      <em>(${sanitize_output(result.mention.entity_type_suggestion || 'Sin tipo')})</em>`;
                    mentionsList.appendChild(listItem);
                    if (noMentionsMessage) noMentionsMessage.style.display = 'none';

                    resetAndHideMentionForm(); // Limpia currentMentionPartsCanvas, oculta form, redibuja
                    set_flash_message_js('success', 'Mención ' + result.mention.mention_public_id + ' guardada.'); // Mostrar mensaje global

                } else {
                    mentionSaveStatus.textContent = 'Error: ' + (result.message || 'Error desconocido.');
                    mentionSaveStatus.style.color = 'red';
                }
            })
            .catch(error => {
                mentionSaveStatus.textContent = 'Error de conexión o JS: ' + error.message;
                mentionSaveStatus.style.color = 'red';
                console.error('Error en fetch:', error);
            });
    });

    cancelMentionButton.addEventListener('click', function () {
        resetAndHideMentionForm();
    });

    // --- HOVER EN LISTA DE MENCIONES (Adaptado para multi-rectángulo) ---
    let currentlyHoveredRects = []; // Para guardar los rects del hover y poder borrarlos

    mentionsList.addEventListener('mouseover', function (e) {
        const listItem = e.target.closest('li[data-mention-id]');
        if (listItem) {
            // Limpiar cualquier hover anterior dibujado directamente (sin redibujar todo)
            currentlyHoveredRects.forEach(r => ctx.clearRect(r.x - 1, r.y - 1, r.w + 2, r.h + 2)); // + un poco más por el lineWidth
            currentlyHoveredRects = [];
            redrawAllElements(); // Mejor redibujar todo para asegurar limpieza

            try {
                const coordsArrayJSON = listItem.dataset.coordinates;
                const coordsArrayOriginal = JSON.parse(coordsArrayJSON);
                if (Array.isArray(coordsArrayOriginal)) {
                    coordsArrayOriginal.forEach(coordSet => {
                        const scaled = scaleCoordsToCanvas(coordSet.x, coordSet.y, coordSet.width, coordSet.height);
                        if (scaled.width > 0 && scaled.height > 0) {
                            drawRect(scaled.x, scaled.y, scaled.width, scaled.height, 'rgba(255, 165, 0, 0.8)', 3); // Naranja para hover
                            currentlyHoveredRects.push({
                                x: scaled.x,
                                y: scaled.y,
                                w: scaled.width,
                                h: scaled.height
                            }); // Guardar para borrar
                        }
                    });
                }
            } catch (err) {
                console.error("Error parseando coordenadas en hover", err);
            }
        }
    });

    mentionsList.addEventListener('mouseout', function (e) {
        const listItem = e.target.closest('li[data-mention-id]');
        if (listItem) {
            // Limpiar los rectángulos de hover dibujados y redibujar el estado base
            currentlyHoveredRects.forEach(r => ctx.clearRect(r.x - 1, r.y - 1, r.w + 2, r.h + 2));
            currentlyHoveredRects = [];
            redrawAllElements();
        }
    });

    mentionsList.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-mention-btn')) {
            const mentionIdToDelete = e.target.dataset.mentionId;
            const mentionPublicId = e.target.dataset.mentionPublicId;

            if (confirm(`¿Estás seguro de que quieres eliminar la mención ${mentionPublicId}?`)) {
                deleteMention(mentionIdToDelete, e.target.closest('li'));
            }
        }
        // Aquí también podrías añadir la lógica para el "Editar" más adelante
    });

    function deleteMention(mentionId, listItemElement) {
        fetch('delete_mention.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ mention_id: mentionId })
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || 'Error del servidor') });
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    // 1. Eliminar de la lista visual
                    listItemElement.remove();
                    // 2. Eliminar del array savedMentions
                    savedMentions = savedMentions.filter(m => m.id !== mentionId); // Cuidado si id es string vs number
                    // 3. Redibujar el canvas
                    redrawAllElements();
                    set_flash_message_js('success', `Mención ${result.deleted_mention_public_id || ''} eliminada.`);
                    if (mentionsList.children.length === 0 && noMentionsMessage) {
                        noMentionsMessage.style.display = 'block';
                    }
                } else {
                    set_flash_message_js('error', 'Error al eliminar la mención: ' + (result.message || 'Desconocido'));
                }
            })
            .catch(error => {
                set_flash_message_js('error', 'Error de conexión: ' + error.message);
                console.error('Error al eliminar mención:', error);
            });
    }

    // Helper JS para mensajes flash (si quieres usarlos desde JS también)
    function set_flash_message_js(type, message) {
        const existingMessages = document.querySelector('.container > .message');
        if (existingMessages) existingMessages.remove();

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.textContent = message;
        document.querySelector('.container > .project-nav').insertAdjacentElement('afterend', messageDiv);
        // Autocerrar después de unos segundos
        setTimeout(() => {
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 500);
        }, 5000);
    }

    // Función para sanitizar salida en JS (muy básica, para el ejemplo)
    function sanitize_output(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }


    // Fin del script DOMContentLoaded
});