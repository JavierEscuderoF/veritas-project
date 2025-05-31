<?php
// src/public/add_mention.php
require_once '../core/bootstrap.php'; // Carga config, functions, y db_connection ($pdo)

// Este script espera una solicitud POST con datos JSON
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Solicitud no válida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_project_id = get_active_project_id_or_redirect(); // La redirección no funcionará bien para AJAX, pero valida la sesión

    // Leer el cuerpo de la solicitud JSON
    $input_data = json_decode(file_get_contents('php://input'), true);

    $page_id = filter_var($input_data['page_id'] ?? null, FILTER_VALIDATE_INT);
    $coordinates = $input_data['coordinates'] ?? null; // Se espera un array/objeto: x, y, width, height
    $mention_string_literal = trim($input_data['mention_string_literal'] ?? '');
    $entity_type_suggestion = trim($input_data['entity_type_suggestion'] ?? null);

    if (!$page_id || !$coordinates || $mention_string_literal === '') {
        $response['message'] = 'Datos incompletos: page_id, coordinates y texto literal son obligatorios.';
        echo json_encode($response);
        exit;
    }

    // Validar coordenadas (básico)
    if (!isset($coordinates['x'], $coordinates['y'], $coordinates['width'], $coordinates['height']) ||
        !is_numeric($coordinates['x']) || !is_numeric($coordinates['y']) ||
        !is_numeric($coordinates['width']) || !is_numeric($coordinates['height'])) {
        $response['message'] = 'Formato de coordenadas no válido.';
        echo json_encode($response);
        exit;
    }
    $coordinates_json = json_encode($coordinates);


    // Verificar que la página pertenece al proyecto activo
    try {
        $stmt_check_page = $pdo->prepare("SELECT page_id FROM Source_Pages WHERE page_id = ? AND project_id = ?");
        $stmt_check_page->execute([$page_id, $active_project_id]);
        if (!$stmt_check_page->fetch()) {
            $response['message'] = 'La página no pertenece al proyecto activo o no existe.';
            echo json_encode($response);
            exit;
        }

        $pdo->beginTransaction();

        // Insertar la mención (con mention_public_id temporal)
        $sql_insert = "INSERT INTO Source_Mentions 
                        (page_id, project_id, coordinates_on_image, mention_string_literal, entity_type_suggestion, mention_public_id)
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $page_id,
            $active_project_id,
            $coordinates_json,
            $mention_string_literal,
            empty($entity_type_suggestion) ? null : $entity_type_suggestion,
            'TEMP_M_ID' // Placeholder
        ]);
        $mention_id = $pdo->lastInsertId();

        // Generar y actualizar mention_public_id
        $mention_public_id = generate_public_id('M', (int)$mention_id);
        $stmt_update_public_id = $pdo->prepare("UPDATE Source_Mentions SET mention_public_id = ? WHERE mention_id = ?");
        $stmt_update_public_id->execute([$mention_public_id, $mention_id]);

        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Mención guardada con éxito.';
        $response['mention'] = [ // Devolver los datos de la mención guardada puede ser útil para el frontend
            'mention_id' => $mention_id,
            'mention_public_id' => $mention_public_id,
            'page_id' => $page_id,
            'coordinates_on_image' => $coordinates, // Devolver el array, no el JSON string
            'mention_string_literal' => $mention_string_literal,
            'entity_type_suggestion' => $entity_type_suggestion
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['message'] = "Error de base de datos al guardar la mención: " . $e->getMessage();
        error_log("Error en add_mention.php: " . $e->getMessage());
    } catch (Exception $e) { // Para otras excepciones
        $response['message'] = "Error general al guardar la mención: " . $e->getMessage();
        error_log("Error en add_mention.php: " . $e->getMessage());
    }

} else {
    $response['message'] = 'Método no permitido.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
exit;