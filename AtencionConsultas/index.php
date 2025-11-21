<?php
/**
 * API REST para gestionar la tabla 'atencionconsulta'
 *
 * Descripción:
 *   Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla 'atencionconsulta' en la base de datos.
 *
 * Tabla: atencionconsulta
 *   - ID_AtencionConsultas (int, PRIMARY KEY)
 *   - ID_Usuario (int, FOREIGN KEY referencia a denunciante_usuario.ID_Usuario)
 *   - ID_Funcionario (int, FOREIGN KEY referencia a funcionario.ID_Funcionario)
 *   - Fecha (date)
 *   - Via (varchar)
 *   - Recomendaciones (text)
 *
 * Endpoints soportados:
 *   - GET /AtencionConsultas/index.php                    → Obtiene todos los registros de atención
 *   - GET /AtencionConsultas/index.php?id=<ID>            → Obtiene un registro de atención por ID
 *   - POST /AtencionConsultas/index.php                   → Crea un nuevo registro de atención
 *   - PUT /AtencionConsultas/index.php?id=<ID>            → Actualiza un registro de atención existente
 *   - DELETE /AtencionConsultas/index.php?id=<ID>         → Elimina un registro de atención
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
 * GET: Obtiene registros de atención consulta
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todos los registros de atención
 *   - Con parámetro ?id=<ID>: Retorna el registro específico o un objeto vacío si no existe
 *
 * Respuesta exitosa (GET all):
 *   HTTP 200
 *   [
 *     {"ID_AtencionConsultas": 1, "ID_Usuario": 5, "ID_Funcionario": 2, "Fecha": "2025-11-21", "Via": "Presencial", "Recomendaciones": "..."},
 *     {"ID_AtencionConsultas": 2, "ID_Usuario": 6, "ID_Funcionario": 3, "Fecha": "2025-11-20", "Via": "Telefónica", "Recomendaciones": "..."}
 *   ]
 *
 * Respuesta exitosa (GET one):
 *   HTTP 200
 *   {"ID_AtencionConsultas": 1, "ID_Usuario": 5, "ID_Funcionario": 2, "Fecha": "2025-11-21", "Via": "Presencial", "Recomendaciones": "..."}
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un registro de atención específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_AtencionConsultas, ID_Usuario, ID_Funcionario, Fecha, Via, Recomendaciones FROM atencionconsultas WHERE ID_AtencionConsultas = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna el registro de atención o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todos los registros de atención
    $result = $conn->query("SELECT ID_AtencionConsultas, ID_Usuario, ID_Funcionario, Fecha, Via, Recomendaciones FROM atencionconsultas");

    // Retorna todos los registros de atención como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo registro de atención consulta
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_AtencionConsultas": 10,                // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Usuario": 5,                           // Obligatorio (FK a denunciante_usuario)
 *     "ID_Funcionario": 2,                       // Obligatorio (FK a funcionario)
 *     "Fecha": "2025-11-21",                     // Obligatorio (formato YYYY-MM-DD)
 *     "Via": "Presencial",                       // Obligatorio
 *     "Recomendaciones": "Texto de recomendaciones"  // Obligatorio
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Registro de atención consulta creado correctamente",
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
    $id = $body['ID_AtencionConsultas'] ?? null;       // Opcional
    $id_usuario = $body['ID_Usuario'] ?? null;         // Obligatorio
    $id_funcionario = $body['ID_Funcionario'] ?? null; // Obligatorio
    $fecha = $body['Fecha'] ?? null;                   // Obligatorio
    $via = $body['Via'] ?? null;                       // Obligatorio
    $recomendaciones = $body['Recomendaciones'] ?? null; // Obligatorio

    // Valida que los campos obligatorios estén presentes
    if (!$id_usuario || !$id_funcionario || !$fecha || !$via || !$recomendaciones) {
        http_response_code(400);
        echo json_encode(['error' => 'Los campos ID_Usuario, ID_Funcionario, Fecha, Via y Recomendaciones son obligatorios']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO atencionconsultas (ID_AtencionConsultas, ID_Usuario, ID_Funcionario, Fecha, Via, Recomendaciones) VALUES (?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, i = integer, i = integer, s = string, s = string, s = string)
        $stmt->bind_param("iiisss", $id, $id_usuario, $id_funcionario, $fecha, $via, $recomendaciones);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Registro de atención consulta creado correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado, FK inválida), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO atencionconsultas (ID_Usuario, ID_Funcionario, Fecha, Via, Recomendaciones) VALUES (?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, i = integer, s = string, s = string, s = string)
        $stmt->bind_param("iisss", $id_usuario, $id_funcionario, $fecha, $via, $recomendaciones);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Registro de atención consulta creado correctamente', 'id' => $stmt->insert_id]);
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
 * PUT: Actualiza un registro de atención consulta existente
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON) - cualquiera de los campos a actualizar (al menos uno):
 *   {
 *     "ID_Usuario": 5,
 *     "ID_Funcionario": 2,
 *     "Fecha": "2025-11-21",
 *     "Via": "Presencial",
 *     "Recomendaciones": "Nuevas recomendaciones"
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Registro de atención consulta actualizado correctamente"
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
    $id_usuario = $body['ID_Usuario'] ?? null;
    $id_funcionario = $body['ID_Funcionario'] ?? null;
    $fecha = $body['Fecha'] ?? null;
    $via = $body['Via'] ?? null;
    $recomendaciones = $body['Recomendaciones'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_usuario && !$id_funcionario && !$fecha && !$via && !$recomendaciones)) {
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

    if ($id_usuario) {
        $updates[] = "ID_Usuario = ?";
        $params[] = $id_usuario;
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
    if ($via) {
        $updates[] = "Via = ?";
        $params[] = $via;
        $types .= "s";
    }
    if ($recomendaciones) {
        $updates[] = "Recomendaciones = ?";
        $params[] = $recomendaciones;
        $types .= "s";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE atencionconsultas SET " . implode(", ", $updates) . " WHERE ID_AtencionConsultas = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Registro de atención consulta actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina un registro de atención consulta
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Registro de atención consulta eliminado correctamente"
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
    $stmt = $conn->prepare("DELETE FROM atencionconsultas WHERE ID_AtencionConsultas = ?");

    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);

    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Registro de atención consulta eliminado correctamente']);
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
