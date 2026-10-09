<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ]);

    exit;
}

// Verificar que el usuario tenga una sesión.
$usuario = verificarSesion();

try {
    $conexion = (new Database())->conectar();

    $sql = "
        SELECT
            id_usuario,
            nombre,
            usuario,
            correo,
            rol,
            estado
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param(
        "i",
        $usuario["id_usuario"]
    );

    $stmt->execute();

    $resultado = $stmt->get_result();
    $datosUsuario = $resultado->fetch_assoc();

    if (
        !$datosUsuario ||
        $datosUsuario["estado"] !== "Activo"
    ) {
        $_SESSION = [];
        session_destroy();

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "La sesión ya no es válida."
        ]);

        exit;
    }

    // Actualizar los datos de la sesión.
    $_SESSION["rol"] = $datosUsuario["rol"];
    $_SESSION["nombre"] = $datosUsuario["nombre"];
    $_SESSION["usuario"] = $datosUsuario["usuario"];

    echo json_encode([
        "success" => true,
        "message" => "Sesión activa.",
        "data" => [
            "id_usuario" => (int)$datosUsuario["id_usuario"],
            "nombre" => $datosUsuario["nombre"],
            "usuario" => $datosUsuario["usuario"],
            "correo" => $datosUsuario["correo"],
            "rol" => $datosUsuario["rol"]
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        "Error al consultar sesión: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No se pudo consultar la sesión."
    ]);
}
