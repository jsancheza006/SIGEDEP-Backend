<?php
/**
 * API REST para gestionar la tabla 'resoluciones'
 * 
 * Descripción:
 *   Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla 'resoluciones' en la base de datos.
 * 
 * Tabla: resoluciones
 *   - ID_Resolucion (int, PRIMARY KEY)
 *   - Resolucion (varchar)
 * 
 * Endpoints soportados:
 *   - GET /ResolucionesEspc/index.php                    → Obtiene todas las resoluciones
 *   - GET /ResolucionesEspc/index.php?id=<ID>            → Obtiene una resolución por ID
 *   - POST /ResolucionesEspc/index.php                   → Crea una nueva resolución
 *   - PUT /ResolucionesEspc/index.php?id=<ID>            → Actualiza una resolución existente
 *   - DELETE /ResolucionesEspc/index.php?id=<ID>         → Elimina una resolución
 * 
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
 * GET: Obtiene resoluciones
 * 
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las resoluciones
 *   - Con parámetro ?id=<ID>: Retorna la resolución específica o un objeto vacío si no existe
 * 
 * Respuesta exitosa (GET all):
 *   HTTP 200
 *   [
 *     {"ID_Resolucion": 1, "Resolucion": "Resolución A"},
 *     {"ID_Resolucion": 2, "Resolucion": "Resolución B"}
 *   ]
 * 
 * Respuesta exitosa (GET one):
 *   HTTP 200
 *   {"ID_Resolucion": 1, "Resolucion": "Resolución A"}
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene una resolución específica
    if (isset($query['id'])) {
        $id = (int)$query['id'];
        
        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Resolucion, Resolucion FROM resoluciones WHERE ID_Resolucion = ?");
        
        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);
        
        // Ejecuta la consulta preparada
        $stmt->execute();
        
        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();
        
        // Retorna la resolución o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las resoluciones
    $result = $conn->query("SELECT ID_Resolucion, Resolucion FROM resoluciones");
    
    // Retorna todas las resoluciones como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea una nueva resolución
 * 
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Resolucion": 5,                // Opcional: si no se proporciona, se auto-incrementa
 *     "Resolucion": "Texto de la resolución"   // Obligatorio
 *   }
 * 
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Resolución creada correctamente",
 *     "id": 5
 *   }
 * 
 * Respuesta de error:
 *   HTTP 400 - Si falta el campo "Resolucion"
 *   HTTP 500 - Si hay un error en la base de datos (ej. ID duplicado)
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);
    
    // Obtiene los campos del cuerpo
    $id = $body['ID_Resolucion'] ?? null;        // Opcional
    $resolucion = $body['Resolucion'] ?? null;     // Obligatorio

    // Valida que el campo "Resolucion" sea obligatorio
    if (!$resolucion) {
        http_response_code(400);
        echo json_encode(['error' => 'El campo Resolucion es obligatorio']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO resoluciones (ID_Resolucion, Resolucion) VALUES (?, ?)");

        // Vincula parámetros (i = integer, s = string)
        $stmt->bind_param("is", $id, $resolucion);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Resolución creada correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO resoluciones (Resolucion) VALUES (?)");

        // Vincula el parámetro Resolucion (s = string)
        $stmt->bind_param("s", $resolucion);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Resolución creada correctamente', 'id' => $stmt->insert_id]);
        } else {
            // Si hay error en la BD, retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    }

    $stmt->close();
    exit;
}


/**
 * PUT: Actualiza una resolución existente
 * 
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON):
 *   {
 *     "Resolucion": "Nuevo texto de la resolución"
 *   }
 * 
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Resolución actualizada correctamente"
 *   }
 * 
 * Respuesta de error:
 *   HTTP 400 - Si falta "id" o "Resolucion"
 *   HTTP 500 - Si hay un error en la base de datos
 */
if ($method === 'PUT') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = $query['id'] ?? null;
    
    // Decodifica el cuerpo JSON
    $body = json_decode(file_get_contents('php://input'), true);
    $resolucion = $body['Resolucion'] ?? null;

    // Valida que ambos parámetros sean obligatorios
    if (!$id || !$resolucion) {
        http_response_code(400);
        echo json_encode(['error' => 'ID y Resolucion son obligatorios para actualizar']);
        exit;
    }

    // Convierte el ID a entero
    $id = (int)$id;
    
    // Prepara la consulta UPDATE
    $stmt = $conn->prepare("UPDATE resoluciones SET Resolucion = ? WHERE ID_Resolucion = ?");
    
    // Vincula parámetros (s = string, i = integer)
    $stmt->bind_param("si", $resolucion, $id);
    
    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Resolución actualizada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina una resolución
 * 
 * Parámetros esperados:
 *   URL: ?id=<ID>
 * 
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Resolución eliminada correctamente"
 *   }
 * 
 * Respuesta de error:
 *   HTTP 400 - Si no se proporciona el ID
 *   HTTP 500 - Si hay un error en la base de datos
 */
if ($method === 'DELETE') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = $query['id'] ?? null;

    // Valida que el ID sea obligatorio
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID es obligatorio para eliminar']);
        exit;
    }

    // Convierte el ID a entero
    $id = (int)$id;
    
    // Prepara la consulta DELETE
    $stmt = $conn->prepare("DELETE FROM resoluciones WHERE ID_Resolucion = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Resolución eliminada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

/**
 * Manejo de métodos no soportados
 * 
 * Si se recibe una solicitud con un método HTTP no soportado (ej. TRACE, HEAD, CONNECT),
 * retorna un código 405 Method Not Allowed
 */
http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
