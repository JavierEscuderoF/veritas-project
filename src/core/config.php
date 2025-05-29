<?php
// src/core/config.php

// -- CONSTANTES DE CONFIGURACIÓN DE LA BASE DE DATOS --
define('DB_HOST', 'db');
define('DB_NAME', 'veritas_db');
define('DB_USER', 'veritas_user');
define('DB_PASS', 'escudero'); // ¡LA MISMA CONTRASEÑA QUE EN docker-compose.yml!
define('DB_CHARSET', 'utf8mb4');

// -- CONSTANTES DE RUTAS DE LA APLICACIÓN --
// __DIR__ es el directorio de este archivo (core), dirname(__DIR__) es src/
define('PROJECT_CORE_PATH', __DIR__);
define('PROJECT_SRC_PATH', dirname(__DIR__)); 
define('PROJECT_PUBLIC_PATH', PROJECT_SRC_PATH . '/public');
define('UPLOADS_FS_BASE', PROJECT_PUBLIC_PATH . '/uploads/'); // Filesystem path for uploads
define('UPLOADS_WEB_BASE', 'uploads/');                  // Web URL path for uploads (relative to public/)

// Construir BASE_URL dinámicamente
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST']; // Esto incluye el puerto si es diferente de 80/443
define('BASE_URL', $protocol . "://" . $host); // Ej: http://192.168.1.160:8080

// -- OTRAS CONFIGURACIONES --
// define('SITE_NAME', 'Veritas Project');
?>