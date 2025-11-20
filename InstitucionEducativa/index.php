<?php
/**
 * API REST para gestionar la tabla 'institucioneducativa'
 *
 * Descripción:
 *   Endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla `institucioneducativa`.
 *
 * Tabla: institucioneducativa
 *   - ID_Institucion (int, PRIMARY KEY)
 *   - Nombre (varchar)
 *   - Observaciones (text)
 *   - Codigo_saber (varchar)
 *   - CAPAID (varchar)
 *   - CIRC (varchar)
 *   - Resumen_niveles (text)
 *   - In_situ_Oficios (varchar)
 *   - Correo (varchar)
 *   - Numero (varchar)
 *
 * Endpoints soportados:
 *   - GET /InstitucionEducativa/index.php                    → Obtiene todas las instituciones
 *   - GET /InstitucionEducativa/index.php?id=<ID>            → Obtiene una institución por ID
 *   - POST /InstitucionEducativa/index.php                   → Crea una nueva institución (ID opcional)
 *   - PUT /InstitucionEducativa/index.php?id=<ID>            → Actualiza una institución existente
 *   - DELETE /InstitucionEducativa/index.php?id=<ID>         → Elimina una institución
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
        $stmt = $conn->prepare("SELECT ID_Institucion, Nombre, Observaciones, Codigo_saber, CAPAID, CIRC, Resumen_niveles, In_situ_Oficios, Correo, Numero FROM institucioneducativa WHERE ID_Institucion = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna la institución o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todas las instituciones
    $result = $conn->query("SELECT ID_Institucion, Nombre, Observaciones, Codigo_saber, CAPAID, CIRC, Resumen_niveles, In_situ_Oficios, Correo, Numero FROM institucioneducativa");

    // Retorna todas las instituciones como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea una nueva institución educativa
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Institucion": 5,                // Opcional: si no se proporciona, se auto-incrementa
 *     "Nombre": "Nombre de la institución", // Obligatorio
 *     "Observaciones": "...",
 *     "Codigo_saber": "...",
 *     "CAPAID": "...",
 *     "CIRC": "...",
 *     "Resumen_niveles": "...",
 *     "In_situ_Oficios": "...",
 *     "Correo": "...",
 *     "Numero": "..."
 *   }
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id = $body['ID_Institucion'] ?? null;            // Opcional
    $nombre = $body['Nombre'] ?? null;                // Recomendado
    $observaciones = $body['Observaciones'] ?? null;
    $codigo = $body['Codigo_saber'] ?? null;
    $capaid = $body['CAPAID'] ?? null;
    $circ = $body['CIRC'] ?? null;
    $resumen = $body['Resumen_niveles'] ?? null;
    $in_situ = $body['In_situ_Oficios'] ?? null;
    $correo = $body['Correo'] ?? null;
    $numero = $body['Numero'] ?? null;

    // Valida que el campo "Nombre" sea obligatorio
    if (!$nombre) {
        http_response_code(400);
        echo json_encode(['error' => 'El campo Nombre es obligatorio']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID
        $stmt = $conn->prepare("INSERT INTO institucioneducativa (ID_Institucion, Nombre, Observaciones, Codigo_saber, CAPAID, CIRC, Resumen_niveles, In_situ_Oficios, Correo, Numero) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string...)
        $stmt->bind_param("isssssssss", $id, $nombre, $observaciones, $codigo, $capaid, $circ, $resumen, $in_situ, $correo, $numero);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Institución creada correctamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO institucioneducativa (Nombre, Observaciones, Codigo_saber, CAPAID, CIRC, Resumen_niveles, In_situ_Oficios, Correo, Numero) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (s = string)
        $stmt->bind_param("sssssssss", $nombre, $observaciones, $codigo, $capaid, $circ, $resumen, $in_situ, $correo, $numero);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Institución creada correctamente', 'id' => $stmt->insert_id]);
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
    $nombre = $body['Nombre'] ?? null;
    $observaciones = $body['Observaciones'] ?? null;
    $codigo = $body['Codigo_saber'] ?? null;
    $capaid = $body['CAPAID'] ?? null;
    $circ = $body['CIRC'] ?? null;
    $resumen = $body['Resumen_niveles'] ?? null;
    $in_situ = $body['In_situ_Oficios'] ?? null;
    $correo = $body['Correo'] ?? null;
    $numero = $body['Numero'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$nombre && !$observaciones && !$codigo && !$capaid && !$circ && !$resumen && !$in_situ && !$correo && !$numero)) {
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

    if ($nombre) {
        $updates[] = "Nombre = ?";
        $params[] = $nombre;
        $types .= "s";
    }
    if ($observaciones) {
        $updates[] = "Observaciones = ?";
        $params[] = $observaciones;
        $types .= "s";
    }
    if ($codigo) {
        $updates[] = "Codigo_saber = ?";
        $params[] = $codigo;
        $types .= "s";
    }
    if ($capaid) {
        $updates[] = "CAPAID = ?";
        $params[] = $capaid;
        $types .= "s";
    }
    if ($circ) {
        $updates[] = "CIRC = ?";
        $params[] = $circ;
        $types .= "s";
    }
    if ($resumen) {
        $updates[] = "Resumen_niveles = ?";
        $params[] = $resumen;
        $types .= "s";
    }
    if ($in_situ) {
        $updates[] = "In_situ_Oficios = ?";
        $params[] = $in_situ;
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
    $query_str = "UPDATE institucioneducativa SET " . implode(", ", $updates) . " WHERE ID_Institucion = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Institución actualizada correctamente']);
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
    $stmt = $conn->prepare("DELETE FROM institucioneducativa WHERE ID_Institucion = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Institución eliminada correctamente']);
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
