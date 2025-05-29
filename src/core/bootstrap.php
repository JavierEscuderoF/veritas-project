<?php
// src/core/bootstrap.php

// 1. Cargar constantes de configuración
require_once __DIR__ . '/config.php';

// 2. Cargar funciones de utilidad, iniciar sesión y configurar errores
require_once __DIR__ . '/functions.php';

// 3. Establecer conexión a la base de datos y crear el objeto $pdo
require_once __DIR__ . '/db_connection.php';

// Ahora $pdo está disponible, y todas las funciones y constantes también.
// Las páginas en public/ solo necesitarán incluir este archivo.
?>