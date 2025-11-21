<?php
/**
 * API REST para gestionar la tabla 'personadirectora'
 *
 * Descripción:
 *   Endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla `personadirectora`.
 *
 * Tabla: personadirectora
 *   - ID_Directora (int, PRIMARY KEY)
 *   - ID_Institucion (int, FOREIGN KEY referencia a institucioneducativa.ID_Institucion)
 *   - Nombre (varchar)
 *   - Apellido (varchar)
 *   - Correo (varchar)
 *   - Numero (varchar)
 *
 * Endpoints soportados:
 *   - GET /PersonaDirectora/index.php                    → Obtiene todas las directoras
 *   - GET /PersonaDirectora/index.php?id=<ID>            → Obtiene una directora por ID
 *   - POST /PersonaDirectora/index.php                   → Crea una nueva directora (ID opcional)
 *   - PUT /PersonaDirectora/index.php?id=<ID>            → Actualiza una directora existente
 *   - DELETE /PersonaDirectora/index.php?id=<ID>         → Elimina una directora
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
 * GET: Obtiene directoras
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las directoras
 *   - Con parámetro ?id=<ID>: Retorna la directora específica o un objeto vacío si no existe
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene una directora específica
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Directora, ID_Institucion, Nombre, Apellido, Correo, Numero FROM personadirectora WHERE ID_Directora = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna la directora o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las directoras
    $result = $conn->query("SELECT ID_Directora, ID_Institucion, Nombre, Apellido, Correo, Numero FROM personadirectora");

    // Retorna todas las directoras como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea una nueva persona directora
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Directora": 5,        // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Institucion": 12,     // Obligatorio (FK a institucioneducativa)
 *     "Nombre": "Ana",         // Obligatorio
 *     "Apellido": "Pérez",     // Obligatorio
 *     "Correo": "a@ejemplo.com", // Opcional
 *     "Numero": "88881234"     // Opcional
 *   }
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id = $body['ID_Directora'] ?? null;              // Opcional
    $id_institucion = $body['ID_Institucion'] ?? null; // Obligatorio
    $nombre = $body['Nombre'] ?? null;                // Obligatorio
    $apellido = $body['Apellido'] ?? null;            // Obligatorio
    $correo = $body['Correo'] ?? null;                // Opcional
    $numero = $body['Numero'] ?? null;                // Opcional

    // Valida campos obligatorios
    if (!$id_institucion || !$nombre || !$apellido) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Institucion, Nombre y Apellido']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID
        $stmt = $conn->prepare("INSERT INTO personadirectora (ID_Directora, ID_Institucion, Nombre, Apellido, Correo, Numero) VALUES (?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string...)
        $stmt->bind_param("iissss", $id, $id_institucion, $nombre, $apellido, $correo, $numero);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Persona directora creada correctamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO personadirectora (ID_Institucion, Nombre, Apellido, Correo, Numero) VALUES (?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string...)
        $stmt->bind_param("issss", $id_institucion, $nombre, $apellido, $correo, $numero);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Persona directora creada correctamente', 'id' => $stmt->insert_id]);
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
    $nombre = $body['Nombre'] ?? null;
    $apellido = $body['Apellido'] ?? null;
    $correo = $body['Correo'] ?? null;
    $numero = $body['Numero'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_institucion && !$nombre && !$apellido && !$correo && !$numero)) {
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
    if ($nombre) {
        $updates[] = "Nombre = ?";
        $params[] = $nombre;
        $types .= "s";
    }
    if ($apellido) {
        $updates[] = "Apellido = ?";
        $params[] = $apellido;
        $types .= "s";
    }
    if ($correo) {
        $updates[] = "Correo = ?";
        $params[] = $correo;
        $types .= "s";
    }
    if ($numero) {
        $updates[] = "Numero = ?";
        $params[] = $numero;
        $types .= "s";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE personadirectora SET " . implode(", ", $updates) . " WHERE ID_Directora = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Persona directora actualizada correctamente']);
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
    $stmt = $conn->prepare("DELETE FROM personadirectora WHERE ID_Directora = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Persona directora eliminada correctamente']);
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
