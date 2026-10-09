
<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once __DIR__ . "/config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $entrada = json_decode(file_get_contents("php://input"), true);

    if (!is_array($entrada)) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Los datos enviados no son válidos."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $usuario = trim($entrada["usuario"] ?? "");
    $password = $entrada["password"] ?? "";

    if ($usuario === "" || $password === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Ingresa tu usuario y contraseña."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $conexion = (new Database())->conectar();

    $stmt = $conexion->prepare(
        "SELECT id_usuario, nombre, usuario, correo,
                password_hash, rol, estado
         FROM usuarios
         WHERE usuario = ? OR correo = ?
         LIMIT 1"
    );

    $stmt->bind_param("ss", $usuario, $usuario);
    $stmt->execute();

    $datos = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$datos || !password_verify($password, $datos["password_hash"])) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Usuario o contraseña incorrectos."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($datos["estado"] !== "Activo") {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Tu cuenta está desactivada."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if (!in_array($datos["rol"], ["Administrador", "Recepcionista"], true)) {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Tu usuario no tiene un rol válido."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    session_regenerate_id(true);

    $_SESSION["id_usuario"] = (int) $datos["id_usuario"];
    $_SESSION["nombre"] = $datos["nombre"];
    $_SESSION["usuario"] = $datos["usuario"];
    $_SESSION["rol"] = $datos["rol"];

    echo json_encode([
        "success" => true,
        "message" => "Inicio de sesión correcto.",
        "data" => [
            "id_usuario" => (int) $datos["id_usuario"],
            "nombre" => $datos["nombre"],
            "usuario" => $datos["usuario"],
            "correo" => $datos["correo"],
            "rol" => $datos["rol"]
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("Error en login: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No se pudo iniciar sesión."
    ], JSON_UNESCAPED_UNICODE);
}
