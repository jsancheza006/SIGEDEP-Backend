<?php
/**
 * db.php
 *
 * Archivo de configuración y conexión a la base de datos MySQL.
 * Este archivo define la variable global `$conn` (instancia `mysqli`) que
 * otros scripts del proyecto incluyen con `include_once("../../db.php")`.
 *
 * Configuración:
 * - Intenta leer las credenciales desde variables de entorno:
 *     - DB_HOST (por defecto: 127.0.0.1)
 *     - DB_PORT (por defecto: 3306)
 *     - DB_NAME (por defecto: "sige_dep")
 *     - DB_USER (por defecto: "root")
 *     - DB_PASS (por defecto: "")
 * - Si lo prefieres, edita los valores por defecto directamente abajo.
 *
 * Nota: ajusta `DB_NAME`, `DB_USER` y `DB_PASS` según tu entorno local.
 */

// Valores por defecto (cambiar si es necesario)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'sigedep';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

// Habilita que mysqli lance excepciones para manejo más simple
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Crea la conexión mysqli (host, usuario, contraseña, base, puerto)
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);

    // Forzar el uso de utf8mb4 (soporte completo de Unicode)
    $conn->set_charset('utf8mb4');

    // Opcional: puedes descomentar la siguiente línea para debug en desarrollo
    // error_log("DB connected: $DB_HOST:$DB_PORT/$DB_NAME as $DB_USER");

} catch (mysqli_sql_exception $e) {
    // Si la conexión falla, devolvemos un JSON simple y terminamos la ejecución.
    // Muchos endpoints incluyen este archivo y esperan recibir JSON.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Error de conexión a la base de datos',
        'message' => $e->getMessage()
    ]);
    // Para evitar que el resto del script continue con una conexión inválida
    exit;
}

// Al incluir este archivo se obtiene la variable $conn lista para usar.
