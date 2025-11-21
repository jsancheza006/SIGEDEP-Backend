<?php
/**
 * API REST para gestionar la tabla 'cambiosinstitucion'
 *
 * Descripción:
 *   Endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla `cambiosinstitucion`.
 *
 * Tabla: cambiosinstitucion
 *   - ID_Cambio (int, PRIMARY KEY)
 *   - ID_Institucion (int, FOREIGN KEY referencia a institucioneducativa.ID_Institucion)
 *   - Year (YEAR)
 *   - Descripcion (text)
 *
 * Endpoints soportados:
 *   - GET /CambiosInstitucion/index.php                    → Obtiene todos los cambios
 *   - GET /CambiosInstitucion/index.php?id=<ID>            → Obtiene un cambio por ID
 *   - POST /CambiosInstitucion/index.php                   → Crea un nuevo cambio (ID opcional)
 *   - PUT /CambiosInstitucion/index.php?id=<ID>            → Actualiza un cambio existente
 *   - DELETE /CambiosInstitucion/index.php?id=<ID>         → Elimina un cambio
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
 * GET: Obtiene cambios de institución
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todos los cambios
 *   - Con parámetro ?id=<ID>: Retorna el cambio específico o un objeto vacío si no existe
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un cambio específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Cambio, ID_Institucion, Year, Descripcion FROM cambiosinstitucion WHERE ID_Cambio = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna el cambio o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todos los cambios
    $result = $conn->query("SELECT ID_Cambio, ID_Institucion, Year, Descripcion FROM cambiosinstitucion");

    // Retorna todos los cambios como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo cambio de institución
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Cambio": 5,            // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Institucion": 12,      // Obligatorio (FK a institucioneducativa)
 *     "Year": 2025,              // Obligatorio (YEAR)
 *     "Descripcion": "..."     // Opcional
 *   }
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id = $body['ID_Cambio'] ?? null;                  // Opcional
    $id_institucion = $body['ID_Institucion'] ?? null; // Obligatorio
    $year = $body['Year'] ?? null;                     // Obligatorio
    $descripcion = $body['Descripcion'] ?? null;       // Opcional

    // Valida campos obligatorios
    if (!$id_institucion || !$year) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Institucion y Year']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID
        $stmt = $conn->prepare("INSERT INTO cambiosinstitucion (ID_Cambio, ID_Institucion, Year, Descripcion) VALUES (?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string...)
        $stmt->bind_param("iiis", $id, $id_institucion, $year, $descripcion);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Cambio creado correctamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO cambiosinstitucion (ID_Institucion, Year, Descripcion) VALUES (?, ?, ?)");

        // Vincula parámetros (i = integer, i = integer, s = string)
        $stmt->bind_param("iis", $id_institucion, $year, $descripcion);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Cambio creado correctamente', 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    }

    $stmt->close();
    exit;
}


/**
 * PUT: Actualiza una institución existente
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON): cualquiera de los campos a actualizar (al menos uno)
 */
if ($method === 'PUT') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = $query['id'] ?? null;

    // Decodifica el cuerpo JSON
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos opcionales del cuerpo
    $id_institucion = $body['ID_Institucion'] ?? null;
    $year = $body['Year'] ?? null;
    $descripcion = $body['Descripcion'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_institucion && !$year && !$descripcion)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID es obligatorio y al menos un campo para actualizar']);
        exit;
    }

    // Convierte el ID a entero
    $id = (int)$id;

    // Construye la consulta UPDATE dinámicamente según los campos proporcionados
    $updates = [];
    $params = [];
    $types = "";

    if ($id_institucion) {
        $updates[] = "ID_Institucion = ?";
        $params[] = $id_institucion;
        $types .= "i";
    }
    if ($year) {
        $updates[] = "Year = ?";
        $params[] = $year;
        $types .= "i";
    }
    if ($descripcion) {
        $updates[] = "Descripcion = ?";
        $params[] = $descripcion;
        $types .= "s";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE cambiosinstitucion SET " . implode(", ", $updates) . " WHERE ID_Cambio = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Cambio actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina una institución
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
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
    $stmt = $conn->prepare("DELETE FROM cambiosinstitucion WHERE ID_Cambio = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Cambio eliminado correctamente']);
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
