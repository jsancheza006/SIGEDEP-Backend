<?php
/**
 * API REST para gestionar la tabla 'modalidadesacreditadas'
 * 
 * Descripción:
 *   Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla 'modalidadesacreditadas' en la base de datos.
 * 
 * Tabla: modalidadesacreditadas
 *   - ID_Oferta (int, PRIMARY KEY)
 *   - Nombre (varchar)
 * 
 * Endpoints soportados:
 *   - GET /OfertasEduModalidadesAcreEspc/index.php                    → Obtiene todas las modalidades
 *   - GET /OfertasEduModalidadesAcreEspc/index.php?id=<ID>            → Obtiene una modalidad por ID
 *   - POST /OfertasEduModalidadesAcreEspc/index.php                   → Crea una nueva modalidad
 *   - PUT /OfertasEduModalidadesAcreEspc/index.php?id=<ID>            → Actualiza una modalidad existente
 *   - DELETE /OfertasEduModalidadesAcreEspc/index.php?id=<ID>         → Elimina una modalidad
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
 * GET: Obtiene modalidades acreditadas
 * 
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las modalidades
 *   - Con parámetro ?id=<ID>: Retorna la modalidad específica o un objeto vacío si no existe
 * 
 * Respuesta exitosa (GET all):
 *   HTTP 200
 *   [
 *     {"ID_Oferta": 1, "Nombre": "Modalidad Presencial"},
 *     {"ID_Oferta": 2, "Nombre": "Modalidad Virtual"}
 *   ]
 * 
 * Respuesta exitosa (GET one):
 *   HTTP 200
 *   {"ID_Oferta": 1, "Nombre": "Modalidad Presencial"}
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un servicio específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];
        
        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Oferta, Nombre FROM ofertasedu_modalidadesacreditadas WHERE ID_Oferta = ?");
        
        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);
        
        // Ejecuta la consulta preparada
        $stmt->execute();
        
        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();
        
        // Retorna el servicio o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las modalidades
    $result = $conn->query("SELECT ID_Oferta, Nombre FROM ofertasedu_modalidadesacreditadas");
    
    // Retorna todos los servicios como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea una nueva modalidad acreditada
 * 
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Oferta": 5,                      // Opcional: si no se proporciona, se auto-incrementa
 *     "Nombre": "Nombre de la modalidad"   // Obligatorio
 *   }
 * 
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Modalidad creada correctamente",
 *     "id": 5
 *   }
 * 
 * Respuesta de error:
 *   HTTP 400 - Si falta el campo "Nombre"
 *   HTTP 500 - Si hay un error en la base de datos (ej. ID duplicado)
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);
    
    // Obtiene los campos del cuerpo
    $id = $body['ID_Oferta'] ?? null;            // Opcional
    $nombre = $body['Nombre'] ?? null;           // Obligatorio

    // Valida que el campo "Nombre" sea obligatorio
    if (!$nombre) {
        http_response_code(400);
        echo json_encode(['error' => 'El campo Nombre es obligatorio']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO ofertasedu_modalidadesacreditadas (ID_Oferta, Nombre) VALUES (?, ?)");
        
        // Vincula parámetros (i = integer, s = string)
        $stmt->bind_param("is", $id, $nombre);
        
        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Modalidad creada correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO ofertasedu_modalidadesacreditadas (Nombre) VALUES (?)");
        
        // Vincula el parámetro Nombre (s = string)
        $stmt->bind_param("s", $nombre);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Modalidad creada correctamente', 'id' => $stmt->insert_id]);
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
 * PUT: Actualiza una modalidad acreditada existente
 * 
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON):
 *   {
 *     "Nombre": "Nuevo nombre de la modalidad"
 *   }
 * 
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Modalidad actualizada correctamente"
 *   }
 * 
 * Respuesta de error:
 *   HTTP 400 - Si falta "id" o "Nombre"
 *   HTTP 500 - Si hay un error en la base de datos
 */
if ($method === 'PUT') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = $query['id'] ?? null;
    
    // Decodifica el cuerpo JSON
    $body = json_decode(file_get_contents('php://input'), true);
    $nombre = $body['Nombre'] ?? null;

    // Valida que ambos parámetros sean obligatorios
    if (!$id || !$nombre) {
        http_response_code(400);
        echo json_encode(['error' => 'ID y Nombre son obligatorios para actualizar']);
        exit;
    }

    // Convierte el ID a entero
    $id = (int)$id;
    
    // Prepara la consulta UPDATE
    $stmt = $conn->prepare("UPDATE ofertasedu_modalidadesacreditadas SET Nombre = ? WHERE ID_Oferta = ?");
    
    // Vincula parámetros (s = string, i = integer)
    $stmt->bind_param("si", $nombre, $id);
    
    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Modalidad actualizada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina una modalidad acreditada
 * 
 * Parámetros esperados:
 *   URL: ?id=<ID>
 * 
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Modalidad eliminada correctamente"
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
    $stmt = $conn->prepare("DELETE FROM ofertasedu_modalidadesacreditadas WHERE ID_Oferta = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Modalidad eliminada correctamente']);
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
