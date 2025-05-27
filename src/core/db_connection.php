<?php
// src/core/db_connection.php

// Configuración de la base de datos
$host = 'db'; // Nombre del servicio MySQL en docker-compose
$db   = 'veritas_db';
$user = 'veritas_user';
$pass = 'escudero'; // ¡LA MISMA CONTRASEÑA QUE EN docker-compose.yml!
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa preparaciones nativas
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // En un entorno de producción, loguearías este error y mostrarías un mensaje genérico.
    // Para desarrollo, podemos mostrar el error.
    error_log("Error de conexión a la BD: " . $e->getMessage());
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
    // O simplemente: die("Error de conexión a la BD. Por favor, revisa la configuración y logs.");
}

// No cierres la etiqueta PHP aquí si este archivo solo contiene PHP.