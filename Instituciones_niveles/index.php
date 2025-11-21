<?php
/**
 * API REST para gestionar la tabla 'instituciones_niveles'
 *
 * Descripción:
 *   Endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla `instituciones_niveles`.
 *
 * Tabla: instituciones_niveles
 *   - ID_Nivel (int, FOREIGN KEY referencia a niveles.ID_Nivel) -- parte de PK
 *   - ID_Institucion (int, FOREIGN KEY referencia a institucioneducativa.ID_Institucion) -- parte de PK
 *   - Estado (varchar) -- opcional
 *   - Otro (varchar) -- opcional
 *
 * Endpoints soportados:
 *   - GET /Instituciones_niveles/index.php                                      → Obtiene todas las filas
 *   - GET /Instituciones_niveles/index.php?id_nivel=<ID>&id_institucion=<ID>    → Obtiene un registro por clave compuesta
 *   - POST /Instituciones_niveles/index.php                                     → Crea un nuevo registro (requiere ambos IDs)
 *   - PUT /Instituciones_niveles/index.php?id_nivel=<ID>&id_institucion=<ID>     → Actualiza un registro existente (identificadores originales en query)
 *   - DELETE /Instituciones_niveles/index.php?id_nivel=<ID>&id_institucion=<ID>  → Elimina un registro
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
 * GET: Obtiene filas de instituciones_niveles
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las filas
 *   - Con parámetros ?id_nivel=<ID>&id_institucion=<ID>: Retorna la fila específica o un objeto vacío si no existe
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporcionan ID_Nivel e ID_Institucion, obtiene un registro específico
    if (isset($query['id_nivel']) && isset($query['id_institucion'])) {
        $id_nivel = (int)$query['id_nivel'];
        $id_institucion = (int)$query['id_institucion'];

        // Prepara la consulta con parámetros placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Nivel, ID_Institucion, Estado, Otro FROM instituciones_niveles WHERE ID_Nivel = ? AND ID_Institucion = ?");

        // Vincula los parámetros (i = integer)
        $stmt->bind_param("ii", $id_nivel, $id_institucion);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna el registro o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay IDs, obtiene todos los registros
    $result = $conn->query("SELECT ID_Nivel, ID_Institucion, Estado, Otro FROM instituciones_niveles");

    // Retorna todos los registros como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo registro de instituciones_niveles
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Nivel": 5,          // Obligatorio (FK a niveles)
 *     "ID_Institucion": 12,   // Obligatorio (FK a institucioneducativa)
 *     "Estado": "activo",   // Opcional
 *     "Otro": "texto"        // Opcional
 *   }
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id_nivel = $body['ID_Nivel'] ?? null; // Obligatorio
    $id_institucion = $body['ID_Institucion'] ?? null; // Obligatorio
    $estado = $body['Estado'] ?? null; // Opcional
    $otro = $body['Otro'] ?? null; // Opcional

    // Valida campos obligatorios
    if (!$id_nivel || !$id_institucion) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Nivel y ID_Institucion']);
        exit;
    }

    // Inserta el registro (clave compuesta)
    $stmt = $conn->prepare("INSERT INTO instituciones_niveles (ID_Nivel, ID_Institucion, Estado, Otro) VALUES (?, ?, ?, ?)");

    // Vincula parámetros
    $stmt->bind_param("iiss", $id_nivel, $id_institucion, $estado, $otro);

    // Ejecuta la inserción
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Registro creado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * PUT: Actualiza un registro de instituciones_niveles
 *
 * Parámetros esperados:
 *   URL: ?id_nivel=<ID>&id_institucion=<ID>  (identificadores originales)
 *   Body (JSON): cualquiera de los campos a actualizar (al menos uno). Puede actualizar las claves compuestas también.
 */
if ($method === 'PUT') {
    // Extrae parámetros de la query string (identificadores originales)
    parse_str($_SERVER['QUERY_STRING'], $query);
    $orig_nivel = $query['id_nivel'] ?? null;
    $orig_inst = $query['id_institucion'] ?? null;

    // Decodifica el cuerpo JSON
    $body = json_decode(file_get_contents('php://input'), true);

    // Campos nuevos (opcional)
    $new_nivel = $body['ID_Nivel'] ?? null;
    $new_inst = $body['ID_Institucion'] ?? null;
    $new_estado = $body['Estado'] ?? null;
    $new_otro = $body['Otro'] ?? null;

    // Valida identificadores originales y al menos un campo a actualizar
    if (!$orig_nivel || !$orig_inst) {
        http_response_code(400);
        echo json_encode(['error' => 'id_nivel e id_institucion originales son obligatorios']);
        exit;
    }

    // Construye la consulta UPDATE dinámicamente según los nuevos campos proporcionados
    $updates = [];
    $params = [];
    $types = "";

    if ($new_nivel !== null) {
        $updates[] = "ID_Nivel = ?";
        $params[] = (int)$new_nivel;
        $types .= "i";
    }
    if ($new_inst !== null) {
        $updates[] = "ID_Institucion = ?";
        $params[] = (int)$new_inst;
        $types .= "i";
    }
    if ($new_estado !== null) {
        $updates[] = "Estado = ?";
        $params[] = $new_estado;
        $types .= "s";
    }
    if ($new_otro !== null) {
        $updates[] = "Otro = ?";
        $params[] = $new_otro;
        $types .= "s";
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'Al menos un campo debe ser proporcionado para actualizar']);
        exit;
    }

    // Añade los identificadores originales para WHERE
    $params[] = (int)$orig_nivel;
    $params[] = (int)$orig_inst;
    $types .= "ii";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE instituciones_niveles SET " . implode(", ", $updates) . " WHERE ID_Nivel = ? AND ID_Institucion = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Registro actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina un registro de instituciones_niveles
 *
 * Parámetros esperados:
 *   URL: ?id_nivel=<ID>&id_institucion=<ID>
 */
if ($method === 'DELETE') {
    // Extrae identificadores de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id_nivel = $query['id_nivel'] ?? null;
    $id_inst = $query['id_institucion'] ?? null;

    if (!$id_nivel || !$id_inst) {
        http_response_code(400);
        echo json_encode(['error' => 'id_nivel e id_institucion son obligatorios para eliminar']);
        exit;
    }

    // Convierte a enteros
    $id_nivel = (int)$id_nivel;
    $id_inst = (int)$id_inst;

    // Prepara la consulta DELETE usando la clave compuesta
    $stmt = $conn->prepare("DELETE FROM instituciones_niveles WHERE ID_Nivel = ? AND ID_Institucion = ?");

    // Vincula los parámetros (ii = dos enteros)
    $stmt->bind_param("ii", $id_nivel, $id_inst);

    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Registro eliminado correctamente']);
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

