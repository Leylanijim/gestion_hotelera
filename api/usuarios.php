<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Allow: GET, POST, PUT, DELETE");

require_once __DIR__ . "/auth.php";

// Solo el Administrador puede gestionar usuarios.
soloAdministrador();

function responder(
    bool $success,
    string $message,
    $data = null,
    int $codigo = 200
): void {
    http_response_code($codigo);

    $respuesta = [
        "success" => $success,
        "message" => $message
    ];

    if ($data !== null) {
        $respuesta["data"] = $data;
    }

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function obtenerJSON(): array
{
    $contenido = file_get_contents("php://input");
    $datos = json_decode($contenido ?: "", true);

    return is_array($datos) ? $datos : [];
}

function texto($valor): string
{
    return is_string($valor) ? trim($valor) : "";
}

function idValido($valor): int
{
    if (
        !is_int($valor) &&
        !(is_string($valor) && ctype_digit($valor))
    ) {
        return 0;
    }

    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT
    );

    return $id !== false && $id > 0 ? $id : 0;
}

/*
 * Nunca incluir password_hash en respuestas.
 */
function obtenerUsuario(
    mysqli $conexion,
    int $id
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            id_usuario,
            nombre,
            usuario,
            correo,
            rol,
            estado,
            fecha_registro
         FROM usuarios
         WHERE id_usuario = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $usuario ?: null;
}

function existeDuplicado(
    mysqli $conexion,
    string $campo,
    string $valor,
    int $excluirId = 0
): bool {
    $permitidos = ["usuario", "correo"];

    if (!in_array($campo, $permitidos, true)) {
        throw new InvalidArgumentException(
            "Campo de búsqueda no permitido."
        );
    }

    $stmt = $conexion->prepare(
        "SELECT id_usuario
         FROM usuarios
         WHERE $campo = ?
           AND id_usuario <> ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "si",
        $valor,
        $excluirId
    );

    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

function validarDatos(
    string $nombre,
    string $usuario,
    string $correo,
    string $rol,
    string $estado
): void {
    if (
        $nombre === "" ||
        $usuario === "" ||
        $correo === ""
    ) {
        responder(
            false,
            "Nombre, usuario y correo son obligatorios.",
            null,
            400
        );
    }

    if (!filter_var(
        $correo,
        FILTER_VALIDATE_EMAIL
    )) {
        responder(
            false,
            "El correo electrónico no es válido.",
            null,
            400
        );
    }

    if (!in_array(
        $rol,
        ["Administrador", "Recepcionista"],
        true
    )) {
        responder(
            false,
            "El rol debe ser Administrador o Recepcionista.",
            null,
            400
        );
    }

    if (!in_array(
        $estado,
        ["Activo", "Inactivo"],
        true
    )) {
        responder(
            false,
            "El estado debe ser Activo o Inactivo.",
            null,
            400
        );
    }
}

/*
 * Comprobar que no se desactive
 * al último administrador activo.
 */
function esUltimoAdministrador(
    mysqli $conexion,
    int $idUsuario
): bool {
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total
         FROM usuarios
         WHERE rol = 'Administrador'
           AND estado = 'Activo'
           AND id_usuario <> ?"
    );

    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($fila["total"] ?? 0) === 0;
}

function validarCambioAdministrador(
    mysqli $conexion,
    array $actual,
    string $rolNuevo,
    string $estadoNuevo
): void {
    $id = (int) $actual["id_usuario"];
    $idSesion = (int) (
        $_SESSION["id_usuario"] ?? 0
    );

    if (
        $id === $idSesion &&
        (
            $rolNuevo !== "Administrador" ||
            $estadoNuevo !== "Activo"
        )
    ) {
        responder(
            false,
            "No puedes desactivar tu propia cuenta ni quitarte el rol de Administrador.",
            null,
            403
        );
    }

    if (
        $actual["rol"] === "Administrador" &&
        $actual["estado"] === "Activo" &&
        (
            $rolNuevo !== "Administrador" ||
            $estadoNuevo !== "Activo"
        ) &&
        esUltimoAdministrador($conexion, $id)
    ) {
        responder(
            false,
            "No puedes desactivar o cambiar el rol del último administrador activo.",
            null,
            409
        );
    }
}

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Consultar usuarios.
     */
    if ($metodo === "GET") {

        if (isset($_GET["id"])) {
            $id = idValido($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de usuario no válido.",
                    null,
                    400
                );
            }

            $usuario = obtenerUsuario(
                $conexion,
                $id
            );

            if (!$usuario) {
                responder(
                    false,
                    "Usuario no encontrado.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Usuario encontrado.",
                $usuario
            );
        }

        $resultado = $conexion->query(
            "SELECT
                id_usuario,
                nombre,
                usuario,
                correo,
                rol,
                estado,
                fecha_registro
             FROM usuarios
             ORDER BY nombre ASC"
        );

        responder(
            true,
            "Usuarios obtenidos correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Registrar un usuario nuevo.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $nombre = texto(
            $datos["nombre"] ?? ""
        );

        $usuario = texto(
            $datos["usuario"] ?? ""
        );

        $correo = texto(
            $datos["correo"] ?? ""
        );

        $password = $datos["password"] ?? "";

        $rol = texto(
            $datos["rol"] ?? "Recepcionista"
        );

        validarDatos(
            $nombre,
            $usuario,
            $correo,
            $rol,
            "Activo"
        );

        if (
            !is_string($password) ||
            strlen($password) < 8
        ) {
            responder(
                false,
                "La contraseña debe tener al menos 8 caracteres.",
                null,
                400
            );
        }

        if (existeDuplicado(
            $conexion,
            "usuario",
            $usuario
        )) {
            responder(
                false,
                "Ese nombre de usuario ya está registrado.",
                null,
                409
            );
        }

        if (existeDuplicado(
            $conexion,
            "correo",
            $correo
        )) {
            responder(
                false,
                "Ese correo electrónico ya está registrado.",
                null,
                409
            );
        }

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conexion->prepare(
            "INSERT INTO usuarios (
                nombre,
                usuario,
                correo,
                password_hash,
                rol,
                estado
             )
             VALUES (?, ?, ?, ?, ?, 'Activo')"
        );

        $stmt->bind_param(
            "sssss",
            $nombre,
            $usuario,
            $correo,
            $passwordHash,
            $rol
        );

        $stmt->execute();

        $idNuevo = $conexion->insert_id;
        $stmt->close();

        responder(
            true,
            "Usuario registrado correctamente.",
            [
                "id_usuario" => $idNuevo,
                "nombre" => $nombre,
                "usuario" => $usuario,
                "correo" => $correo,
                "rol" => $rol,
                "estado" => "Activo"
            ],
            201
        );
    }

    /*
     * PUT
     * Actualizar un usuario.
     *
     * Si no se envía password,
     * se conserva el hash existente.
     */
    if ($metodo === "PUT") {
        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_usuario"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar un ID válido.",
                null,
                400
            );
        }

        $actual = obtenerUsuario(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "El usuario no existe.",
                null,
                404
            );
        }

        $nombre = texto(
            $datos["nombre"] ??
            $actual["nombre"]
        );

        $usuario = texto(
            $datos["usuario"] ??
            $actual["usuario"]
        );

        $correo = texto(
            $datos["correo"] ??
            $actual["correo"]
        );

        $rol = texto(
            $datos["rol"] ??
            $actual["rol"]
        );

        $estado = texto(
            $datos["estado"] ??
            $actual["estado"]
        );

        validarDatos(
            $nombre,
            $usuario,
            $correo,
            $rol,
            $estado
        );

        if (existeDuplicado(
            $conexion,
            "usuario",
            $usuario,
            $id
        )) {
            responder(
                false,
                "Ese nombre de usuario pertenece a otro usuario.",
                null,
                409
            );
        }

        if (existeDuplicado(
            $conexion,
            "correo",
            $correo,
            $id
        )) {
            responder(
                false,
                "Ese correo pertenece a otro usuario.",
                null,
                409
            );
        }

        validarCambioAdministrador(
            $conexion,
            $actual,
            $rol,
            $estado
        );

        $password = $datos["password"] ?? "";

        if (!is_string($password)) {
            responder(
                false,
                "La contraseña no es válida.",
                null,
                400
            );
        }

        if ($password !== "") {
            if (strlen($password) < 8) {
                responder(
                    false,
                    "La nueva contraseña debe tener al menos 8 caracteres.",
                    null,
                    400
                );
            }

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conexion->prepare(
                "UPDATE usuarios
                 SET nombre = ?,
                     usuario = ?,
                     correo = ?,
                     password_hash = ?,
                     rol = ?,
                     estado = ?
                 WHERE id_usuario = ?"
            );

            $stmt->bind_param(
                "ssssssi",
                $nombre,
                $usuario,
                $correo,
                $passwordHash,
                $rol,
                $estado,
                $id
            );

        } else {
            // No se modifica password_hash.
            $stmt = $conexion->prepare(
                "UPDATE usuarios
                 SET nombre = ?,
                     usuario = ?,
                     correo = ?,
                     rol = ?,
                     estado = ?
                 WHERE id_usuario = ?"
            );

            $stmt->bind_param(
                "sssssi",
                $nombre,
                $usuario,
                $correo,
                $rol,
                $estado,
                $id
            );
        }

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Usuario actualizado correctamente."
        );
    }

    /*
     * DELETE
     * Desactivar usuario sin borrarlo.
     */
    if ($metodo === "DELETE") {
        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_usuario"] ??
            $_GET["id"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar un ID válido.",
                null,
                400
            );
        }

        $actual = obtenerUsuario(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "El usuario no existe.",
                null,
                404
            );
        }

        if (
            $id === (int) (
                $_SESSION["id_usuario"] ?? 0
            )
        ) {
            responder(
                false,
                "No puedes desactivar tu propia cuenta.",
                null,
                403
            );
        }

        if ($actual["estado"] === "Inactivo") {
            responder(
                true,
                "El usuario ya estaba inactivo."
            );
        }

        validarCambioAdministrador(
            $conexion,
            $actual,
            $actual["rol"],
            "Inactivo"
        );

        $stmt = $conexion->prepare(
            "UPDATE usuarios
             SET estado = 'Inactivo'
             WHERE id_usuario = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Usuario desactivado correctamente."
        );
    }

    responder(
        false,
        "Método HTTP no permitido.",
        null,
        405
    );

} catch (mysqli_sql_exception $error) {
    error_log(
        "Error SQL en api/usuarios.php: " .
        $error->getMessage()
    );

    if ((int) $error->getCode() === 1062) {
        responder(
            false,
            "El nombre de usuario o correo electrónico ya está registrado.",
            null,
            409
        );
    }

    responder(
        false,
        "No se pudo completar la operación con el usuario.",
        null,
        500
    );

} catch (Throwable $error) {
    error_log(
        "Error en api/usuarios.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar la solicitud.",
        null,
        500
    );
}
