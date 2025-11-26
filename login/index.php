<?php
/**
 * login.php
 * * Endpoint de autenticación (Login) para funcionarios.
 * Verifica las credenciales (Correo y Contrasena) contra la base de datos.
 */

// Función para manejar CORS
function handleCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 1728000');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        http_response_code(200);
        exit();
    }
}

handleCORS();

// Define el tipo de contenido de la respuesta
header('Content-Type: application/json');

// Incluye el archivo de conexión a la base de datos.
// Asume que está en una ruta relativa correcta y proporciona la variable $conn.
include_once "../db.php"; 

// Obtener el cuerpo del request
$data = json_decode(file_get_contents("php://input"), true);

// Usamos 'Correo' y 'Contrasena' (mayúsculas) para ser consistentes con la tabla
$correo = $data['Correo'] ?? '';       
$contrasena = $data['Contrasena'] ?? '';

// Validar campos obligatorios
if (!$correo || !$contrasena) {
    http_response_code(400);
    echo json_encode(["error" => "Correo y Contraseña son obligatorios"]);
    exit();
}

// ------------------------------
//  VALIDACIÓN CONTRA LA BASE DE DATOS
// ------------------------------

try {
    // 1. Buscar al funcionario por Correo
    $stmt = $conn->prepare("SELECT ID_Funcionario, Nombre, Apellido, Correo, Contrasena, Numero FROM funcionario WHERE Correo = ?");
    
    // Vincula el correo (s = string)
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    
    // Obtiene el resultado como un array asociativo
    $result = $stmt->get_result();
    $funcionario = $result->fetch_assoc();
    $stmt->close();

    // 2. Verificar si el usuario existe
    if (!$funcionario) {
        // Mensaje genérico para no dar pistas de si el correo existe o no
        http_response_code(401);
        echo json_encode(["error" => "Correo o Contraseña incorrectos"]);
        exit();
    }
    
    // 3. Verificar la contraseña usando el hash almacenado
    $hash_guardado = $funcionario['Contrasena'];
    
    // password_verify() compara la contraseña plana con el hash guardado
    if (password_verify($contrasena, $hash_guardado)) {
        // La contraseña es correcta

        // Prepara los datos del usuario para la respuesta (SIN enviar el hash de vuelta)
        unset($funcionario['Contrasena']); 
        
        // Éxito
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $funcionario['ID_Funcionario'],
                "nombre" => $funcionario['Nombre'],
                "apellido" => $funcionario['Apellido'],
                "correo" => $funcionario['Correo'],
                "numero" => $funcionario['Numero'] // Devolvemos los datos del funcionario
            ]
        ]);
        exit();
    } else {
        // La contraseña es incorrecta
        // Mensaje genérico para no dar pistas
        http_response_code(401);
        echo json_encode(["error" => "Correo o Contraseña incorrectos"]);
        exit();
    }

} catch (mysqli_sql_exception $e) {
    // Manejo de error de consulta
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
    exit;
}

?>