<?php
/**
 * API REST para gestionar la tabla 'inspeccion'
 *
 * Descripción:
 *   Endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla `inspeccion`.
 *
 * Tabla: inspeccion
 *   - ID_Inspeccion (int, PRIMARY KEY)
 *   - ID_Institucion (int, FOREIGN KEY referencia a institucioneducativa.ID_Institucion)
 *   - ID_Funcionario (int, FOREIGN KEY referencia a funcionario.ID_Funcionario)
 *   - Fecha (date)
 *   - AccionRealizada (text / varchar)
 *   - Observaciones (text)
 *
 * Endpoints soportados:
 *   - GET /Inspeccion/index.php                    → Obtiene todas las inspecciones
 *   - GET /Inspeccion/index.php?id=<ID>            → Obtiene una inspección por ID
 *   - POST /Inspeccion/index.php                   → Crea una nueva inspección (ID opcional)
 *   - PUT /Inspeccion/index.php?id=<ID>            → Actualiza una inspección existente
 *   - DELETE /Inspeccion/index.php?id=<ID>         → Elimina una inspección
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
 * GET: Obtiene instituciones educativas
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las instituciones
 *   - Con parámetro ?id=<ID>: Retorna la institución específica o un objeto vacío si no existe
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene una institución específica
    if (isset($query['id'])) {
        $id = (int)$query['id'];
        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Inspeccion, ID_Institucion, ID_Funcionario, Fecha, AccionRealizada, Observaciones FROM inspeccion WHERE ID_Inspeccion = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna la inspección o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las instituciones
    $result = $conn->query("SELECT ID_Inspeccion, ID_Institucion, ID_Funcionario, Fecha, AccionRealizada, Observaciones FROM inspeccion");

    // Retorna todas las inspecciones como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea una nueva inspección
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Inspeccion": 5,            // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Institucion": 12,          // Obligatorio (FK a institucioneducativa)
 *     "ID_Funcionario": 3,           // Obligatorio (FK a funcionario)
 *     "Fecha": "2025-11-21",        // Obligatorio (YYYY-MM-DD)
 *     "AccionRealizada": "Inspección de seguridad", // Opcional
 *     "Observaciones": "..."       // Opcional
 *   }
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id = $body['ID_Inspeccion'] ?? null;            // Opcional
    $id_institucion = $body['ID_Institucion'] ?? null; // Obligatorio
    $id_funcionario = $body['ID_Funcionario'] ?? null; // Obligatorio
    $fecha = $body['Fecha'] ?? null;                 // Obligatorio
    $accion = $body['AccionRealizada'] ?? null;      // Opcional
    $observaciones = $body['Observaciones'] ?? null; // Opcional

    // Valida campos obligatorios
    if (!$id_institucion || !$id_funcionario || !$fecha) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Institucion, ID_Funcionario y Fecha']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID
        $stmt = $conn->prepare("INSERT INTO inspeccion (ID_Inspeccion, ID_Institucion, ID_Funcionario, Fecha, AccionRealizada, Observaciones) VALUES (?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string...)
        $stmt->bind_param("iiisss", $id, $id_institucion, $id_funcionario, $fecha, $accion, $observaciones);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Inspección creada correctamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO inspeccion (ID_Institucion, ID_Funcionario, Fecha, AccionRealizada, Observaciones) VALUES (?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string...)
        $stmt->bind_param("iisss", $id_institucion, $id_funcionario, $fecha, $accion, $observaciones);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Inspección creada correctamente', 'id' => $stmt->insert_id]);
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
    $id_funcionario = $body['ID_Funcionario'] ?? null;
    $fecha = $body['Fecha'] ?? null;
    $accion = $body['AccionRealizada'] ?? null;
    $observaciones = $body['Observaciones'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_institucion && !$id_funcionario && !$fecha && !$accion && !$observaciones)) {
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
    if ($id_funcionario) {
        $updates[] = "ID_Funcionario = ?";
        $params[] = $id_funcionario;
        $types .= "i";
    }
    if ($fecha) {
        $updates[] = "Fecha = ?";
        $params[] = $fecha;
        $types .= "s";
    }
    if ($accion) {
        $updates[] = "AccionRealizada = ?";
        $params[] = $accion;
        $types .= "s";
    }
    if ($observaciones) {
        $updates[] = "Observaciones = ?";
        $params[] = $observaciones;
        $types .= "s";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE inspeccion SET " . implode(", ", $updates) . " WHERE ID_Inspeccion = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Inspección actualizada correctamente']);
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
    $stmt = $conn->prepare("DELETE FROM inspeccion WHERE ID_Inspeccion = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Inspección eliminada correctamente']);
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
