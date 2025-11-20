<?php
function handleCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 1728000');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        exit();
    }
}

handleCORS();

// Obtener el cuerpo del request
$data = json_decode(file_get_contents("php://input"), true);

$correo = $data['correo'] ?? '';
$contrasena = $data['contrasena'] ?? '';

// Validar campos obligatorios
if (!$correo || !$contrasena) {
    http_response_code(400);
    echo json_encode(["error" => "Correo y contraseña son obligatorios"]);
    exit();
}

// ------------------------------
//  USUARIO HARDCODEADO
// ------------------------------
$usuario_valido = "usuario@mep.go.cr";
$contrasena_valida = "12345";

// Validar correo
if ($correo !== $usuario_valido) {
    http_response_code(401);
    echo json_encode(["error" => "Usuario no encontrado"]);
    exit();
}

// Validar contraseña
if ($contrasena !== $contrasena_valida) {
    http_response_code(401);
    echo json_encode(["error" => "Contraseña incorrecta"]);
    exit();
}

// Éxito
echo json_encode([
    "success" => true,
    "user" => [
        "id" => 1,
        "nombre" => "Usuario de Prueba",
        "correo" => $usuario_valido,
        "rol" => "admin"
    ]
]);
exit();
?>