<?php
/**
 * API REST para gestionar la tabla 'funcionario'
 * * Descripción:
 * Este archivo proporciona un endpoint REST para operaciones CRUD (Create, Read, Update, Delete)
 * sobre la tabla 'funcionario' en la base de datos.
 * * Tabla: funcionario
 * - ID_Funcionario (int, PRIMARY KEY)
 * - Nombre (varchar)
 * - Apellido (varchar)
 * - Correo (varchar)
 * - Numero (varchar)
 * - Contrasena (varchar) (Se almacenará con HASH seguro)
 * * Endpoints soportados:
 * - GET /Funcionario/index.php             → Obtiene todos los funcionarios
 * - GET /Funcionario/index.php?id=<ID>     → Obtiene un funcionario por ID
 * - POST /Funcionario/index.php             → Crea un nuevo funcionario
 * - PUT /Funcionario/index.php?id=<ID>     → Actualiza un funcionario existente
 * - DELETE /Funcionario/index.php?id=<ID> → Elimina un funcionario
 * */

/**
 * handleCORS()
 * * Configura los headers CORS para permitir solicitudes desde cualquier origen.
 * También responde a las solicitudes preflight (OPTIONS) con los permisos necesarios.
 * * @return void
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

// Los campos de la tabla que se seleccionan en las consultas GET
$select_fields = "ID_Funcionario, Nombre, Apellido, Correo, Numero, Contrasena, Rol";


/**
 * GET: Obtiene funcionarios
 * * Comportamiento:
 * - Sin parámetros: Retorna un array JSON con todos los funcionarios
 * - Con parámetro ?id=<ID>: Retorna el funcionario específico o un objeto vacío si no existe
 * * Respuesta exitosa (GET all):
 * HTTP 200
 * [
 *      {"ID_Funcionario": 1, "Nombre": "Juan", "Apellido": "Pérez", "Correo": "juan@example.com", "Numero": "123456", "Contrasena": "$2y$10$HASHED_VALUE"},
 *      {"ID_Funcionario": 2, "Nombre": "María", "Apellido": "López", "Correo": "maria@example.com", "Numero": "789012", "Contrasena": "$2y$10$ANOTHER_HASH"}
 * ]
 * * Respuesta exitosa (GET one):
 * HTTP 200
 * {
 *      "ID_Funcionario": 1, 
 *      "Nombre": "Juan", "Apellido": 
 *      "Pérez", "Correo": 
 *      "juan@example.com", 
 *      "Numero": "123456", 
 *      "Contrasena": "$2y$10$HASHED_VALUE"
 * }
 */
if ($method === 'GET') {
    // Extrae parámetros de la query string (?id=1&nombre=test)
    parse_str($_SERVER['QUERY_STRING'], $query);

    // Si se proporciona un ID, obtiene un funcionario específico
    if (isset($query['id'])) {
        $id = (int)$query['id'];
        
        // Prepara la consulta con un parámetro placeholder (?)
        $stmt = $conn->prepare("SELECT $select_fields FROM funcionario WHERE ID_Funcionario = ?");
        
        // Vincula el parámetro ID (i = integer)
        $stmt->bind_param("i", $id);
        
        // Ejecuta la consulta preparada
        $stmt->execute();
        
        // Obtiene el resultado como un array asociativo
        $result = $stmt->get_result()->fetch_assoc();
        
        // Retorna el funcionario o un objeto vacío si no existe
        echo json_encode($result ?: new stdClass());
        exit;
    }

    // Si no hay ID, obtiene todos los funcionarios
    $result = $conn->query("SELECT $select_fields FROM funcionario");
    
    // Retorna todos los funcionarios como un array JSON
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}


/**
 * POST: Crea un nuevo funcionario
 * * Parámetros esperados (JSON en el body):
 * {
 *      "ID_Funcionario": 5,             // Opcional: si no se proporciona, se auto-incrementa
 *      "Nombre": "Juan",                  // Obligatorio
 *      "Apellido": "Pérez",               // Obligatorio
 *      "Correo": "juan@example.com",      // Obligatorio
 *      "Numero": "123456",                // Obligatorio
 *      "Contrasena": "password123"        // Obligatorio (Se hashea internamente)
 * }
 * * Respuesta exitosa (HTTP 200):
 * {
 *      "message": "Funcionario creado correctamente",
 *      "id": 5
 * }
 * * Respuesta de error:
 * HTTP 400 - Si falta algún campo obligatorio
 * HTTP 500 - Si hay un error en la base de datos (ej. ID duplicado)
 */
if ($method === 'POST') {
    // Decodifica el cuerpo JSON de la solicitud en un array asociativo
    $body = json_decode(file_get_contents('php://input'), true);
    
    // Obtiene los campos del cuerpo
    $id = $body['ID_Funcionario'] ?? null;     // Opcional
    $nombre = $body['Nombre'] ?? null;         // Obligatorio
    $apellido = $body['Apellido'] ?? null;    // Obligatorio
    $correo = $body['Correo'] ?? null;       // Obligatorio (¡C mayúscula!)
    $numero = $body['Numero'] ?? null;         // Obligatorio
    $contrasena = $body['Contrasena'] ?? null; // Obligatorio (Campo nuevo)
    $rol = $body['Rol'] ?? null;               // Opcional (nuevo campo)

    // Valida que los campos obligatorios estén presentes
    if (!$nombre || !$apellido || !$correo || !$numero || !$contrasena) {
        http_response_code(400);
        echo json_encode(['error' => 'Los campos Nombre, Apellido, Correo, Numero y Contrasena son obligatorios']);
        exit;
    }
    
    // ⭐ CIFRADO: Hashear la contraseña antes de insertarla
    $contrasena_hashed = password_hash($contrasena, PASSWORD_DEFAULT);

    // Si se proporciona ID, lo convierte a entero y lo usa en el INSERT
    if ($id !== null) {
        $id = (int)$id;
        // INSERT especificando el ID (sin AUTO_INCREMENT)
        $stmt = $conn->prepare("INSERT INTO funcionario (ID_Funcionario, Nombre, Apellido, Correo, Numero, Contrasena, Rol) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (i, s, s, s, s, s, s). Usamos $contrasena_hashed y $rol
        $stmt->bind_param("issssss", $id, $nombre, $apellido, $correo, $numero, $contrasena_hashed, $rol);
        
        // Ejecuta la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Funcionario creado correctamente', 'id' => $id]);
        } else {
            // Si hay error (ej. ID duplicado), retorna código 500
            http_response_code(500);
            echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
        }
    } else {
        // Si NO se proporciona ID, deja que auto-incremente
        $stmt = $conn->prepare("INSERT INTO funcionario (Nombre, Apellido, Correo, Numero, Contrasena, Rol) VALUES (?, ?, ?, ?, ?, ?)");

        // Vincula parámetros (s, s, s, s, s, s). Usamos $contrasena_hashed y $rol
        $stmt->bind_param("ssssss", $nombre, $apellido, $correo, $numero, $contrasena_hashed, $rol);

        // Ejecuta la inserción
        if ($stmt->execute()) {
            // Retorna el ID auto-generado
            echo json_encode(['message' => 'Funcionario creado correctamente', 'id' => $stmt->insert_id]);
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
 * PUT: Actualiza un funcionario existente
 * * Parámetros esperados:
 * URL: ?id=<ID>
 * Body (JSON) - todos los campos son opcionales, pero al menos uno debe estar presente:
 * {
 *      "Nombre": "Nuevo nombre",
 *      "Apellido": "Nuevo apellido",
 *      "Correo": "nuevo@example.com",
 *      "Numero": "999999",
 *      "Contrasena": "newpass" // Opcional, se hashea si está presente
 * }
 * * Respuesta exitosa (HTTP 200):
 * {
 *      "message": "Funcionario actualizado correctamente"
 * }
 * * Respuesta de error:
 * HTTP 400 - Si falta "id"
 * HTTP 500 - Si hay un error en la base de datos
 */
if ($method === 'PUT') {
    // Extrae parámetros de la query string
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = $query['id'] ?? null;
    
    // Decodifica el cuerpo JSON
    $body = json_decode(file_get_contents('php://input'), true);
    
    // Obtiene los campos opcionales del cuerpo
    $nombre = $body['Nombre'] ?? null;
    $apellido = $body['Apellido'] ?? null;
    $correo = $body['Correo'] ?? null; // ¡C mayúscula!
    $numero = $body['Numero'] ?? null;
    $contrasena = $body['Contrasena'] ?? null; // Campo nuevo
    $rol = $body['Rol'] ?? null; // Campo nuevo (rol)

    // Valida que el ID sea obligatorio y al menos un campo para actualizar
    if (!$id || (!$nombre && !$apellido && !$correo && !$numero && !$contrasena && !$rol)) {
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
    if ($rol) {
        $updates[] = "Rol = ?";
        $params[] = $rol;
        $types .= "s";
    }
    // ⭐ Lógica de Hashing: Cifra la contraseña si se proporciona para actualizar
    if ($contrasena) {
        $contrasena_hashed = password_hash($contrasena, PASSWORD_DEFAULT);
        $updates[] = "Contrasena = ?"; // Campo nuevo
        $params[] = $contrasena_hashed; // Usamos el hash
        $types .= "s";
    }
    
    // Añade el ID al final para WHERE
    $params[] = $id;
    $types .= "i";
    
    // Prepara la consulta UPDATE
    $query_str = "UPDATE funcionario SET " . implode(", ", $updates) . " WHERE ID_Funcionario = ?";
    $stmt = $conn->prepare($query_str);
    
    // Vincula parámetros dinámicamente
    // Usamos el operador spread (...) para pasar el array de parámetros
    $stmt->bind_param($types, ...$params);
    
    // Ejecuta la actualización
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Funcionario actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}


/**
 * DELETE: Elimina un funcionario
 * * Parámetros esperados:
 * URL: ?id=<ID>
 * * Respuesta exitosa (HTTP 200):
 * {
 *      "message": "Funcionario eliminado correctamente"
 * }
 * * Respuesta de error:
 * HTTP 400 - Si no se proporciona el ID
 * HTTP 500 - Si hay un error en la base de datos
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
    $stmt = $conn->prepare("DELETE FROM funcionario WHERE ID_Funcionario = ?");
    
    // Vincula el parámetro ID (i = integer)
    $stmt->bind_param("i", $id);
    
    // Ejecuta la eliminación
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Funcionario eliminado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

/**
 * Manejo de métodos no soportados
 * * Si se recibe una solicitud con un método HTTP no soportado (ej. TRACE, HEAD, CONNECT),
 * retorna un código 405 Method Not Allowed
 */
http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);