<?php
// Test de PHP
echo "<h1>¡Hola desde mi Proyecto de Investigación en Docker!</h1>";
// phpinfo();

// Test de conexión a MySQL (descomentar después de que la BD esté lista)
/*
$host = 'db'; // Nombre del servicio MySQL en docker-compose.yml
$db   = 'veritas_db';
$user = 'veritas_user';
$pass = 'escudero'; // La misma que pusiste en docker-compose.yml
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     echo "<h2>Conexión a MySQL exitosa!</h2>";
} catch (\PDOException $e) {
     echo "<h2>Error de conexión a MySQL:</h2>";
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}*/

?>