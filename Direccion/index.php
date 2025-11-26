<?php
/**n+ * API REST para gestionar la tabla 'direccion'
 *
 * Descripción:
 *   Endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla `direccion`.
 *
 * Tabla: direccion
 *   - ID_Direccion (int, PRIMARY KEY)
 *   - ID_Provincia (int, FOREIGN KEY referencia a provincia.ID_Provincia)
 *   - ID_Distrito (int, FOREIGN KEY referencia a distrito.ID_Distrito)
 *   - ID_Canton (int, FOREIGN KEY referencia a canton.ID_Canton)
 *   - Avenida (varchar)
 *   - Calle (varchar)
 *   - Circuito (varchar)
 *   - Otro (varchar)
 *
 * Endpoints soportados:
 *   - GET /Direccion/index.php                    → Obtiene todas las direcciones
 *   - GET /Direccion/index.php?id=<ID>            → Obtiene una dirección por ID
 *   - POST /Direccion/index.php                   → Crea una nueva dirección (ID opcional)
 *   - PUT /Direccion/index.php?id=<ID>            → Actualiza una dirección existente
 *   - DELETE /Direccion/index.php?id=<ID>         → Elimina una dirección
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
 * GET: Obtiene direcciones
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todas las direcciones
 *   - Con parámetro ?id=<ID>: Retorna la dirección específica o un objeto vacío si no existe
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene una dirección específica
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Direccion, ID_Provincia, ID_Distrito, ID_Canton, Avenida, Calle, Circuito, Otro FROM direccion WHERE ID_Direccion = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna la dirección o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las direcciones
    $result = $conn->query("SELECT ID_Direccion, ID_Provincia, ID_Distrito, ID_Canton, Avenida, Calle, Circuito, Otro FROM direccion");

    // Retorna todas las direcciones como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea una nueva dirección
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Direccion": 5,       // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Provincia": 1,       // Obligatorio (FK a provincia)
 *     "ID_Distrito": 10101,        // Obligatorio (FK a distrito)
 *     "ID_Canton": 101,          // Obligatorio (FK a canton)
 *     "Avenida": "Av. 1",    // Opcional
 *     "Calle": "Calle 2",     // Opcional
 *     "Circuito": "C-3",     // Opcional
 *     "Otro": "Referencia"    // Opcional
 *   }
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id = $body['ID_Direccion'] ?? null;          // Opcional
    $id_provincia = $body['ID_Provincia'] ?? null; // Obligatorio
    $id_distrito = $body['ID_Distrito'] ?? null;   // Obligatorio
    $id_canton = $body['ID_Canton'] ?? null;       // Obligatorio
    $avenida = $body['Avenida'] ?? null;           // Opcional
    $calle = $body['Calle'] ?? null;               // Opcional
    $circuito = $body['Circuito'] ?? null;         // Opcional
    $otro = $body['Otro'] ?? null;                 // Opcional

    // Valida campos obligatorios
    if (!$id_provincia || !$id_distrito || !$id_canton) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Provincia, ID_Distrito y ID_Canton']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID
        $stmt = $conn->prepare("INSERT INTO direccion (ID_Direccion, ID_Provincia, ID_Distrito, ID_Canton, Avenida, Calle, Circuito, Otro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros
        $stmt->bind_param("iiiissss", $id, $id_provincia, $id_distrito, $id_canton, $avenida, $calle, $circuito, $otro);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Dirección creada correctamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO direccion (ID_Provincia, ID_Distrito, ID_Canton, Avenida, Calle, Circuito, Otro) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros
        $stmt->bind_param("iiissss", $id_provincia, $id_distrito, $id_canton, $avenida, $calle, $circuito, $otro);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Dirección creada correctamente', 'id' => $stmt->insert_id]);
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
    $id_provincia = $body['ID_Provincia'] ?? null;
    $id_distrito = $body['ID_Distrito'] ?? null;
    $id_canton = $body['ID_Canton'] ?? null;
    $avenida = $body['Avenida'] ?? null;
    $calle = $body['Calle'] ?? null;
    $circuito = $body['Circuito'] ?? null;
    $otro = $body['Otro'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_provincia && !$id_distrito && !$id_canton && !$avenida && !$calle && !$circuito && !$otro)) {
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

    if ($id_provincia) {
        $updates[] = "ID_Provincia = ?";
        $params[] = $id_provincia;
        $types .= "i";
    }
    if ($id_distrito) {
        $updates[] = "ID_Distrito = ?";
        $params[] = $id_distrito;
        $types .= "i";
    }
    if ($id_canton) {
        $updates[] = "ID_Canton = ?";
        $params[] = $id_canton;
        $types .= "i";
    }
    if ($avenida) {
        $updates[] = "Avenida = ?";
        $params[] = $avenida;
        $types .= "s";
    }
    if ($calle) {
        $updates[] = "Calle = ?";
        $params[] = $calle;
        $types .= "s";
    }
    if ($circuito) {
        $updates[] = "Circuito = ?";
        $params[] = $circuito;
        $types .= "s";
    }
    if ($otro) {
        $updates[] = "Otro = ?";
        $params[] = $otro;
        $types .= "s";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE direccion SET " . implode(", ", $updates) . " WHERE ID_Direccion = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Dirección actualizada correctamente']);
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
    $stmt = $conn->prepare("DELETE FROM direccion WHERE ID_Direccion = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Dirección eliminada correctamente']);
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
