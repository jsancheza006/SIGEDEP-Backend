<?php
/**
 * API REST para gestionar la tabla 'denunciante_usuario'
 *
 * Descripción:
 *   Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 *   sobre la tabla 'denunciante_usuario' en la base de datos.
 *
 * Tabla: denunciante_usuario
 *   - ID_Usuario (int, PRIMARY KEY)
 *   - ID_Institucion (int, FOREIGN KEY referencia a institucioneducativa.ID_Institucion)
 *   - Nombre (varchar)
 *   - Apellido (varchar)
 *   - Relacion (varchar)
 *
 * Endpoints soportados:
 *   - GET /DenuncianteUsuario/index.php                    → Obtiene todos los denunciantes
 *   - GET /DenuncianteUsuario/index.php?id=<ID>            → Obtiene un denunciante por ID
 *   - POST /DenuncianteUsuario/index.php                   → Crea un nuevo denunciante
 *   - PUT /DenuncianteUsuario/index.php?id=<ID>            → Actualiza un denunciante existente
 *   - DELETE /DenuncianteUsuario/index.php?id=<ID>         → Elimina un denunciante
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
 * GET: Obtiene denunciantes (usuarios denunciantes)
 *
 * Comportamiento:
 *   - Sin parámetros: Retorna un array JSON con todos los denunciantes
 *   - Con parámetro ?id=<ID>: Retorna el denunciante específico o un objeto vacío si no existe
 *
 * Respuesta exitosa (GET all):
 *   HTTP 200
 *   [
 *     {"ID_Usuario": 1, "ID_Institucion": 2, "Nombre": "Juan", "Apellido": "Pérez", "Relacion": "Padre"},
 *     {"ID_Usuario": 2, "ID_Institucion": 3, "Nombre": "María", "Apellido": "López", "Relacion": "Madre"}
 *   ]
 *
 * Respuesta exitosa (GET one):
 *   HTTP 200
 *   {"ID_Usuario": 1, "ID_Institucion": 2, "Nombre": "Juan", "Apellido": "Pérez", "Relacion": "Padre"}
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un denunciante específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];

        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT ID_Usuario, ID_Institucion, Nombre, Apellido, Relacion FROM denunciante_usuario WHERE ID_Usuario = ?");

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

    // Si no hay ID, obtiene todos los denunciantes
    $result = $conn->query("SELECT ID_Usuario, ID_Institucion, Nombre, Apellido, Relacion FROM denunciante_usuario");

    // Retorna todos los denunciantes como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo denunciante_usuario
 *
 * Parámetros esperados (JSON en el body):
 *   {
 *     "ID_Usuario": 5,                        // Opcional: si no se proporciona, se auto-incrementa
 *     "ID_Institucion": 1,                    // Obligatorio (referencia a institucioneducativa)
 *     "Nombre": "Juan",                       // Obligatorio
 *     "Apellido": "Pérez",                    // Obligatorio
 *     "Relacion": "Padre"                     // Obligatorio
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Denunciante usuario creado correctamente",
 *     "id": 5
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
    $id = $body['ID_Usuario'] ?? null;              // Opcional
    $id_institucion = $body['ID_Institucion'] ?? null;  // Obligatorio
    $nombre = $body['Nombre'] ?? null;              // Obligatorio
    $apellido = $body['Apellido'] ?? null;          // Obligatorio
    $relacion = $body['Relacion'] ?? null;          // Obligatorio

    // Valida que los campos obligatorios estén presentes
    if (!$id_institucion || !$nombre || !$apellido || !$relacion) {
        http_response_code(400);
        echo json_encode(['error' => 'Los campos ID_Institucion, Nombre, Apellido y Relacion son obligatorios']);
        exit;
    }

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO denunciante_usuario (ID_Usuario, ID_Institucion, Nombre, Apellido, Relacion) VALUES (?, ?, ?, ?, ?)");

        // Vincula parámetros (i = integer, i = integer, s = string, s = string, s = string)
        $stmt->bind_param("iisss", $id, $id_institucion, $nombre, $apellido, $relacion);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Denunciante usuario creado correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado, FK inválida), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO denunciante_usuario (ID_Institucion, Nombre, Apellido, Relacion) VALUES (?, ?, ?, ?)");

        // Vincula parámetros (i = integer, s = string, s = string, s = string)
        $stmt->bind_param("isss", $id_institucion, $nombre, $apellido, $relacion);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Denunciante usuario creado correctamente', 'id' => $stmt->insert_id]);
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
 * PUT: Actualiza un denunciante_usuario existente
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *   Body (JSON) - cualquiera de los campos a actualizar (al menos uno):
 *   {
 *     "ID_Institucion": 1,
 *     "Nombre": "Nuevo nombre",
 *     "Apellido": "Nuevo apellido",
 *     "Relacion": "Nueva relacion"
 *   }
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Denunciante usuario actualizado correctamente"
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
    $id_institucion = $body['ID_Institucion'] ?? null;
    $nombre = $body['Nombre'] ?? null;
    $apellido = $body['Apellido'] ?? null;
    $relacion = $body['Relacion'] ?? null;

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$id_institucion && !$nombre && !$apellido && !$relacion)) {
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
    if ($relacion) {
        $updates[] = "Relacion = ?";
        $params[] = $relacion;
        $types .= "s";
    }

    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";

    // Prepara la consulta UPDATE
    $query_str = "UPDATE denunciante_usuario SET " . implode(", ", $updates) . " WHERE ID_Usuario = ?";
    $stmt = $conn->prepare($query_str);

    // Vincula parámetros dinámicamente
    $stmt->bind_param($types, ...$params);

    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Denunciante usuario actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina un denunciante_usuario
 *
 * Parámetros esperados:
 *   URL: ?id=<ID>
 *
 * Respuesta exitosa (HTTP 200):
 *   {
 *     "message": "Denunciante usuario eliminado correctamente"
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
    $stmt = $conn->prepare("DELETE FROM denunciante_usuario WHERE ID_Usuario = ?");

    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);

    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Denunciante usuario eliminado correctamente']);
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
