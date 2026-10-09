<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

function respuestaAuth(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => $mensaje
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function verificarSesion(): array
{
    if (
        !isset($_SESSION["id_usuario"]) ||
        !isset($_SESSION["rol"])
    ) {
        respuestaAuth(401, "Debes iniciar sesión para continuar.");
    }

    try {
        $conexion = (new Database())->conectar();

        $idUsuario = (int) $_SESSION["id_usuario"];

        $stmt = $conexion->prepare(
            "SELECT id_usuario, nombre, usuario, rol, estado
             FROM usuarios
             WHERE id_usuario = ?
             LIMIT 1"
        );

        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();

        $datos = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$datos || $datos["estado"] !== "Activo") {
            $_SESSION = [];
            session_destroy();

            respuestaAuth(401, "Tu sesión ya no es válida.");
        }

        // Mantener actualizados los permisos de la sesión.
        $_SESSION["nombre"] = $datos["nombre"];
        $_SESSION["usuario"] = $datos["usuario"];
        $_SESSION["rol"] = $datos["rol"];

        return [
            "id_usuario" => (int) $datos["id_usuario"],
            "nombre" => $datos["nombre"],
            "usuario" => $datos["usuario"],
            "rol" => $datos["rol"]
        ];

    } catch (Throwable $e) {
        error_log("Error de autenticación: " . $e->getMessage());

        respuestaAuth(500, "No se pudo verificar la sesión.");
    }
}

function verificarRol(array $rolesPermitidos): array
{
    $usuario = verificarSesion();

    if (!in_array($usuario["rol"], $rolesPermitidos, true)) {
        respuestaAuth(403, "No tienes permisos para realizar esta acción.");
    }

    return $usuario;
}

function soloAdministrador(): array
{
    return verificarRol(["Administrador"]);
}

function personalHotel(): array
{
    return verificarRol(["Administrador", "Recepcionista"]);
}
