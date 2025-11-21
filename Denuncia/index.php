<?php
/**
 * API REST para gestionar la tabla 'denuncia'
 *
 * Descripción:
 *   Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla 'denuncia' en la base de datos.
 *
 * Tabla: denuncia
 *   - ID_Denuncia (int, PRIMARY KEY)
 *   - ID_Funcionario (int, FOREIGN KEY referencia a funcionario.ID_Funcionario)
 *   - Denuncia (text)
 *   - Fecha (date)
 *   - Via (varchar)
 *   - Estado (varchar)
 *   - Observaciones (text)
 *   - PersonaAfectada (varchar)
 *   - No_Oficina (varchar)
 *   - ID_Usuario (int, FOREIGN KEY referencia a denunciante_usuario.ID_Usuario)
 *
 * Endpoints soportados:
 *   - GET /Denuncia/index.php                    → Obtiene todos los registros de denuncia
 *   - GET /Denuncia/index.php?id=<ID>            → Obtiene un registro de denuncia por ID
 *   - POST /Denuncia/index.php                   → Crea un nuevo registro de denuncia
 *   - PUT /Denuncia/index.php?id=<ID>            → Actualiza un registro de denuncia existente
 *   - DELETE /Denuncia/index.php?id=<ID>         → Elimina un registro de denuncia
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
 * GET: Obtiene registros de denuncia
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todos los registros de denuncia
 *   - Con parámetro ?id=<ID>: Retorna el registro específico o un objeto vacío si no existe
 *
 * Respuesta exitosa (GET all):
 *   HTTP 200
 *   [
 *     {"ID_Denuncia": 1, "ID_Funcionario": 2, "Denuncia": "texto...", "Fecha": "2025-11-21", "Via": "Presencial", "Estado": "Abierta", "Observaciones": "..", "PersonaAfectada": "Nombre", "No_Oficina": "12A", "ID_Usuario": 5}
 *   ]
 *
 * Respuesta exitosa (GET one):
 *   HTTP 200
 *   {"ID_Denuncia": 1, "ID_Funcionario": 2, "Denuncia": "texto...", "Fecha": "2025-11-21", "Via": "Presencial", "Estado": "Abierta", "Observaciones": "..", "PersonaAfectada": "Nombre", "No_Oficina": "12A", "ID_Usuario": 5}
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un registro de denuncia específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Denuncia, ID_Funcionario, Denuncia, Fecha, Via, Estado, Observaciones, PersonaAfectada, No_Oficina, ID_Usuario FROM denuncia WHERE ID_Denuncia = ?");

        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);

        // Ejecuta la consulta preparada
        $stmt->execute();

        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();

        // Retorna el denunciante o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todos los registros de denuncia
    $result = $conn->query("SELECT ID_Denuncia, ID_Funcionario, Denuncia, Fecha, Via, Estado, Observaciones, PersonaAfectada, No_Oficina, ID_Usuario FROM denuncia");

    // Retorna todos los registros de denuncia como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo registro de denuncia
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Denuncia": 10,                // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Funcionario": 2,              // Obligatorio (FK a funcionario)
 *     "Denuncia": "Texto de la denuncia", // Obligatorio
 *     "Fecha": "2025-11-21",          // Obligatorio (YYYY-MM-DD)
 *     "Via": "Presencial",            // Obligatorio
 *     "Estado": "Abierta",            // Obligatorio
 *     "Observaciones": "...",         // Opcional
 *     "PersonaAfectada": "Nombre",
 *     "No_Oficina": "12A",
 *     "ID_Usuario": 5                    // Obligatorio (FK a denunciante_usuario)
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Denuncia creada correctamente",
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
    $id = $body['ID_Denuncia'] ?? null;                 // Opcional
    $id_funcionario = $body['ID_Funcionario'] ?? null; // Obligatorio
    $denuncia = $body['Denuncia'] ?? null;             // Obligatorio
    $fecha = $body['Fecha'] ?? null;                   // Obligatorio
    $via = $body['Via'] ?? null;                       // Obligatorio
    $estado = $body['Estado'] ?? null;                 // Obligatorio
    $observaciones = $body['Observaciones'] ?? null;   // Opcional
    $personaAfectada = $body['PersonaAfectada'] ?? null; // Obligatorio
    $no_oficina = $body['No_Oficina'] ?? null;         // Obligatorio
    $id_usuario = $body['ID_Usuario'] ?? null;         // Obligatorio

    // Valida que los campos obligatorios estén presentes
    if (!$id_funcionario || !$denuncia || !$fecha || !$via || !$estado || !$personaAfectada || !$no_oficina || !$id_usuario) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: ID_Funcionario, Denuncia, Fecha, Via, Estado, PersonaAfectada, No_Oficina y ID_Usuario']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO denuncia (ID_Denuncia, ID_Funcionario, Denuncia, Fecha, Via, Estado, Observaciones, PersonaAfectada, No_Oficina, ID_Usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i, i, s, s, s, s, s, s, s, i)
        $stmt->bind_param("iisssssssi", $id, $id_funcionario, $denuncia, $fecha, $via, $estado, $observaciones, $personaAfectada, $no_oficina, $id_usuario);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Denuncia creada correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado, FK inválida), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO denuncia (ID_Funcionario, Denuncia, Fecha, Via, Estado, Observaciones, PersonaAfectada, No_Oficina, ID_Usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i, s, s, s, s, s, s, s, i)
        $stmt->bind_param("isssssssi", $id_funcionario, $denuncia, $fecha, $via, $estado, $observaciones, $personaAfectada, $no_oficina, $id_usuario);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Denuncia creada correctamente', 'id' => $stmt->insert_id]);
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
 * PUT: Actualiza un registro de denuncia existente
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON) - cualquiera de los campos a actualizar (al menos uno):
 *   {
 *     "ID_Funcionario": 2,
 *     "Denuncia": "Texto actualizado",
 *     "Fecha": "2025-11-21",
 *     "Via": "Telefónica",
 *     "Estado": "Cerrada",
 *     "Observaciones": "Notas",
 *     "PersonaAfectada": "Nombre",
 *     "No_Oficina": "12A",
 *     "ID_Usuario": 5
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Denuncia actualizada correctamente"
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
    $id_funcionario = $body['ID_Funcionario'] ?? null;
    $denuncia = $body['Denuncia'] ?? null;
    $fecha = $body['Fecha'] ?? null;
    $via = $body['Via'] ?? null;
    $estado = $body['Estado'] ?? null;
    $observaciones = $body['Observaciones'] ?? null;
    $personaAfectada = $body['PersonaAfectada'] ?? null;
    $no_oficina = $body['No_Oficina'] ?? null;
    $id_usuario = $body['ID_Usuario'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_funcionario && !$denuncia && !$fecha && !$via && !$estado && !$observaciones && !$personaAfectada && !$no_oficina && !$id_usuario)) {
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

    if ($id_funcionario) {
        $updates[] = "ID_Funcionario = ?";
        $params[] = $id_funcionario;
        $types .= "i";
    }
    if ($denuncia) {
        $updates[] = "Denuncia = ?";
        $params[] = $denuncia;
        $types .= "s";
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
    if ($personaAfectada) {
        $updates[] = "PersonaAfectada = ?";
        $params[] = $personaAfectada;
        $types .= "s";
    }
    if ($no_oficina) {
        $updates[] = "No_Oficina = ?";
        $params[] = $no_oficina;
        $types .= "s";
    }
    if ($id_usuario) {
        $updates[] = "ID_Usuario = ?";
        $params[] = $id_usuario;
        $types .= "i";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE denuncia SET " . implode(", ", $updates) . " WHERE ID_Denuncia = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Denuncia actualizada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina un registro de denuncia
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Denuncia eliminada correctamente"
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
    $stmt = $conn->prepare("DELETE FROM denuncia WHERE ID_Denuncia = ?");

    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);

    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Denuncia eliminada correctamente']);
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
