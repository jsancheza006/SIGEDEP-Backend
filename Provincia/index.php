<?php
/**
 * API REST para gestionar la tabla 'provincia'
 *
 * Descripción:
 *   Endpoint REST de solo lectura para obtener datos de la tabla `provincia`.
 *
 * Tabla: provincia
 *   - ID_Provincia (int, PRIMARY KEY)
 *   - Nombre (varchar)
 *
 * Endpoints soportados:
 *   - GET /Provincia/index.php                    → Obtiene todas las provincias
 *   - GET /Provincia/index.php?id=<ID>            → Obtiene una provincia por ID
 */

/**
 * handleCORS()
 * 
 * Configura los headers CORS para permitir solicitudes desde cualquier origen.
 * También responde a las solicitudes preflight (OPTIONS) con los permisos necesarios.
 * 
 * @return void
 */
function handleCORS() {
    // Permite solicitudes desde cualquier origen
    header('Access-Control-Allow-Origin: *');
    
    // Métodos HTTP permitidos
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    
    // Headers permitidos en las solicitudes
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    
    // Tiempo de validez de la política CORS (20 días en segundos)
    header('Access-Control-Max-Age: 1728000');

    // Maneja las solicitudes preflight (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        http_response_code(200);
        exit();
    }
}

// Ejecuta la función de manejo CORS
handleCORS();

// Incluye el archivo de configuración de base de datos
// Presupone que $conn es una conexión mysqli válida
include_once "../db.php";
//include_once(__DIR__ . "/../../db.php");

// Define el tipo de contenido de todas las respuestas como JSON
header('Content-Type: application/json');


// Obtiene el método HTTP de la solicitud (GET, POST, PUT, DELETE, etc.)
$method = $_SERVER['REQUEST_METHOD'];

/**
 * GET: Obtiene provincias
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las provincias
 *   - Con parámetro ?id=<ID>: Retorna la provincia específica o un objeto vacío si no existe
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene una provincia específica
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Provincia, Nombre FROM provincia WHERE ID_Provincia = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna la provincia o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las provincias
    $result = $conn->query("SELECT ID_Provincia, Nombre FROM provincia");

    // Retorna todas las provincias como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}





/**
 * Manejo de métodos no soportados
 *
 * Solo se soporta GET. Otros métodos retornan 405 Method Not Allowed
 */
http_response_code(405);
echo json_encode(['error' => 'Solo GET está permitido']);
