<?php
// src/core/db_connection.php

// config.php ya debería estar incluido por bootstrap.php antes que este archivo,
// por lo que las constantes DB_* están disponibles.

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log("CRITICAL DB Connection Error: " . $e->getMessage() . " (DSN: $dsn, User: " . DB_USER . ")");
    // No mostrar detalles sensibles al usuario en producción
    die("Error crítico de conexión a la Base de Datos. El administrador ha sido notificado. Por favor, inténtalo más tarde.");
}
?>