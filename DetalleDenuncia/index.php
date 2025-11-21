<?php
/**
 * API REST para gestionar la tabla 'detalledenuncia'
 *
 * Descripción:
 *   Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla 'detalledenuncia' en la base de datos.
 *
 * Tabla: detalledenuncia
 *   - ID_DetalleDen (int, PRIMARY KEY)
 *   - ID_Denuncia (int, FOREIGN KEY referencia a denuncia.ID_Denuncia)
 *   - Fecha_recibida (date)
 *   - Fecha_respuesta (date)
 *   - Fecha_cierre (date)
 *   - Estado (varchar)
 *   - Observaciones (text)
 *
 * Endpoints soportados:
 *   - GET /DetalleDenuncia/index.php                    → Obtiene todos los registros de detalle de denuncia
 *   - GET /DetalleDenuncia/index.php?id=<ID>            → Obtiene un registro de detalle de denuncia por ID
 *   - POST /DetalleDenuncia/index.php                   → Crea un nuevo registro de detalle de denuncia
 *   - PUT /DetalleDenuncia/index.php?id=<ID>            → Actualiza un registro de detalle de denuncia existente
 *   - DELETE /DetalleDenuncia/index.php?id=<ID>         → Elimina un registro de detalle de denuncia
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
 * GET: Obtiene registros de detalle de denuncia
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todos los registros de detalle de denuncia
 *   - Con parámetro ?id=<ID>: Retorna el registro específico o un objeto vacío si no existe
 *
 * Respuesta exitosa (GET all):
 *   HTTP 200
 *   [
 *     {"ID_DetalleDen": 1, "ID_Denuncia": 5, "Fecha_recibida": "2025-11-21", "Fecha_respuesta": "2025-11-25", "Fecha_cierre": "2025-11-30", "Estado": "Cerrado", "Observaciones": "..."}
 *   ]
 *
 * Respuesta exitosa (GET one):
 *   HTTP 200
 *   {"ID_DetalleDen": 1, "ID_Denuncia": 5, "Fecha_recibida": "2025-11-21", "Fecha_respuesta": "2025-11-25", "Fecha_cierre": "2025-11-30", "Estado": "Cerrado", "Observaciones": "..."}
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un registro de detalle de denuncia específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_DetalleDen, ID_Denuncia, Fecha_recibida, Fecha_respuesta, Fecha_cierre, Estado, Observaciones FROM detalledenuncia WHERE ID_DetalleDen = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna el registro o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todos los registros de detalle de denuncia
    $result = $conn->query("SELECT ID_DetalleDen, ID_Denuncia, Fecha_recibida, Fecha_respuesta, Fecha_cierre, Estado, Observaciones FROM detalledenuncia");

    // Retorna todos los registros como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo registro de detalle de denuncia
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_DetalleDen": 10,            // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Denuncia": 5,               // Obligatorio (FK a denuncia)
 *     "Fecha_recibida": "2025-11-21", // Opcional (YYYY-MM-DD)
 *     "Fecha_respuesta": "2025-11-25", // Opcional (YYYY-MM-DD)
 *     "Fecha_cierre": "2025-11-30",   // Opcional (YYYY-MM-DD)
 *     "Estado": "Abierta",           // Obligatorio
 *     "Observaciones": "..."         // Opcional
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Detalle de denuncia creado correctamente",
 *     "id": 10
 *   }
 *
 * Respuesta de error:
 *   HTTP 400 - Si falta algún campo obligatorio
 *   HTTP 500 - Si hay un error en la base de datos (ej. ID duplicado, FK inválida)
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos del cuerpo
    $id = $body['ID_DetalleDen'] ?? null;                 // Opcional
    $id_denuncia = $body['ID_Denuncia'] ?? null;         // Obligatorio
    $fecha_recibida = $body['Fecha_recibida'] ?? null;   // Opcional
    $fecha_respuesta = $body['Fecha_respuesta'] ?? null; // Opcional
    $fecha_cierre = $body['Fecha_cierre'] ?? null;       // Opcional
    $estado = $body['Estado'] ?? null;                   // Obligatorio
    $observaciones = $body['Observaciones'] ?? null;     // Opcional

    // Valida que los campos obligatorios estén presentes
    // Nota: ninguna de las fechas es obligatoria, solo ID_Denuncia y Estado son requeridos
    if (!$id_denuncia || !$estado) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Denuncia y Estado']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO detalledenuncia (ID_DetalleDen, ID_Denuncia, Fecha_recibida, Fecha_respuesta, Fecha_cierre, Estado, Observaciones) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i, i, s, s, s, s, s)
        $stmt->bind_param("iisssss", $id, $id_denuncia, $fecha_recibida, $fecha_respuesta, $fecha_cierre, $estado, $observaciones);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Detalle de denuncia creado correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado, FK inválida), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO detalledenuncia (ID_Denuncia, Fecha_recibida, Fecha_respuesta, Fecha_cierre, Estado, Observaciones) VALUES (?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i, s, s, s, s, s)
        $stmt->bind_param("isssss", $id_denuncia, $fecha_recibida, $fecha_respuesta, $fecha_cierre, $estado, $observaciones);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Detalle de denuncia creado correctamente', 'id' => $stmt->insert_id]);
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
 * PUT: Actualiza un registro de detalle de denuncia existente
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON) - cualquiera de los campos a actualizar (al menos uno):
 *   {
 *     "ID_Denuncia": 5,
 *     "Fecha_recibida": "2025-11-21",
 *     "Fecha_respuesta": "2025-11-25",
 *     "Fecha_cierre": "2025-11-30",
 *     "Estado": "Cerrado",
 *     "Observaciones": "Notas"
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Detalle de denuncia actualizado correctamente"
 *   }
 *
 * Respuesta de error:
 *   HTTP 400 - Si falta "id" o no hay campos para actualizar
 *   HTTP 500 - Si hay un error en la base de datos
 */
if ($method === 'PUT') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = $query['id'] ?? null;

    // Decodifica el cuerpo JSON
    $body = json_decode(file_get_contents('php://input'), true);

    // Obtiene los campos opcionales del cuerpo
    $id_denuncia = $body['ID_Denuncia'] ?? null;
    $fecha_recibida = $body['Fecha_recibida'] ?? null;
    $fecha_respuesta = $body['Fecha_respuesta'] ?? null;
    $fecha_cierre = $body['Fecha_cierre'] ?? null;
    $estado = $body['Estado'] ?? null;
    $observaciones = $body['Observaciones'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_denuncia && !$fecha_recibida && !$fecha_respuesta && !$fecha_cierre && !$estado && !$observaciones)) {
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

    if ($id_denuncia) {
        $updates[] = "ID_Denuncia = ?";
        $params[] = $id_denuncia;
        $types .= "i";
    }
    if ($fecha_recibida) {
        $updates[] = "Fecha_recibida = ?";
        $params[] = $fecha_recibida;
        $types .= "s";
    }
    if ($fecha_respuesta) {
        $updates[] = "Fecha_respuesta = ?";
        $params[] = $fecha_respuesta;
        $types .= "s";
    }
    if ($fecha_cierre) {
        $updates[] = "Fecha_cierre = ?";
        $params[] = $fecha_cierre;
        $types .= "s";
    }
    if ($estado) {
        $updates[] = "Estado = ?";
        $params[] = $estado;
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
    $query_str = "UPDATE detalledenuncia SET " . implode(", ", $updates) . " WHERE ID_DetalleDen = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Detalle de denuncia actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina un registro de detalle de denuncia
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Detalle de denuncia eliminado correctamente"
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
    $stmt = $conn->prepare("DELETE FROM detalledenuncia WHERE ID_DetalleDen = ?");

    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);

    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Detalle de denuncia eliminado correctamente']);
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
