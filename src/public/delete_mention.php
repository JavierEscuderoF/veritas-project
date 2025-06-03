<?php
// src/public/delete_mention.php
require_once '../core/bootstrap.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Solicitud no válida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_project_id = get_active_project_id_or_redirect(); // Valida sesión

    $input_data = json_decode(file_get_contents('php://input'), true);
    $mention_id = filter_var($input_data['mention_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$mention_id) {
        $response['message'] = 'ID de mención no proporcionado o no válido.';
        echo json_encode($response);
        exit;
    }

    try {
        // Opcional: verificar que la mención pertenece al proyecto activo antes de borrar
        $stmt_check = $pdo->prepare("SELECT mention_id, mention_public_id FROM Source_Mentions WHERE mention_id = ? AND project_id = ?");
        $stmt_check->execute([$mention_id, $active_project_id]);
        $mention_to_delete = $stmt_check->fetch();

        if (!$mention_to_delete) {
            $response['message'] = 'Mención no encontrada o no pertenece a este proyecto.';
            http_response_code(403); // Forbidden
            echo json_encode($response);
            exit;
        }

        $stmt_delete = $pdo->prepare("DELETE FROM Source_Mentions WHERE mention_id = ?");
        $stmt_delete->execute([$mention_id]);

        if ($stmt_delete->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Mención eliminada con éxito.';
            $response['deleted_mention_public_id'] = $mention_to_delete['mention_public_id'];
        } else {
            $response['message'] = 'No se pudo eliminar la mención o ya estaba eliminada.';
            // Podría ser 404 si no se encuentra después de la verificación, pero rowCount 0 es suficiente
        }

    } catch (PDOException $e) {
        $response['message'] = "Error de base de datos: " . $e->getMessage();
        error_log("Error en delete_mention.php: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    $response['message'] = 'Método no permitido.';
    http_response_code(405);
}

echo json_encode($response);
exit;
?>