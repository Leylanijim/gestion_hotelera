<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT, DELETE");

require_once __DIR__ . "/auth.php";

// Administrador y Recepcionista pueden consultar servicios.
// Solo el Administrador puede crear, editar o desactivar.
personalHotel();

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

function validarPrecio($valor): float
{
    if (
        !is_int($valor) &&
        !is_float($valor) &&
        !(is_string($valor) && is_numeric(trim($valor)))
    ) {
        responder(
            false,
            "El precio debe ser un número válido.",
            null,
            400
        );
    }

    $precio = (float) $valor;

    if (
        !is_finite($precio) ||
        $precio < 0
    ) {
        responder(
            false,
            "El precio debe ser un número mayor o igual a cero.",
            null,
            400
        );
    }

    return round($precio, 2);
}

function validarServicio(
    string $nombre,
    string $estado
): void {
    if ($nombre === "") {
        responder(
            false,
            "El nombre del servicio es obligatorio.",
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

function buscarServicio(
    mysqli $conexion,
    int $id
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            id_servicio,
            nombre,
            descripcion,
            precio,
            estado
         FROM servicios
         WHERE id_servicio = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $servicio = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $servicio ?: null;
}

function nombreDuplicado(
    mysqli $conexion,
    string $nombre,
    int $excluirId = 0
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_servicio
         FROM servicios
         WHERE nombre = ?
           AND id_servicio <> ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "si",
        $nombre,
        $excluirId
    );

    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Consultar servicios.
     */
    if ($metodo === "GET") {

        // Consultar un servicio por ID.
        if (isset($_GET["id"])) {
            $id = idValido($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de servicio no válido.",
                    null,
                    400
                );
            }

            $servicio = buscarServicio(
                $conexion,
                $id
            );

            if (!$servicio) {
                responder(
                    false,
                    "Servicio no encontrado.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Servicio encontrado.",
                $servicio
            );
        }

        // Filtrar por estado.
        if (isset($_GET["estado"])) {
            $estado = texto($_GET["estado"]);

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

            $stmt = $conexion->prepare(
                "SELECT
                    id_servicio,
                    nombre,
                    descripcion,
                    precio,
                    estado
                 FROM servicios
                 WHERE estado = ?
                 ORDER BY nombre ASC"
            );

            $stmt->bind_param("s", $estado);
            $stmt->execute();

            $servicios = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            responder(
                true,
                "Servicios obtenidos correctamente.",
                $servicios
            );
        }

        // Listar todos los servicios.
        $resultado = $conexion->query(
            "SELECT
                id_servicio,
                nombre,
                descripcion,
                precio,
                estado
             FROM servicios
             ORDER BY nombre ASC"
        );

        responder(
            true,
            "Servicios obtenidos correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Registrar un servicio.
     */
    if ($metodo === "POST") {
        soloAdministrador();

        $datos = obtenerJSON();

        $nombre = texto(
            $datos["nombre"] ?? ""
        );

        $descripcion = texto(
            $datos["descripcion"] ?? ""
        );

        if (!array_key_exists("precio", $datos)) {
            responder(
                false,
                "Debes proporcionar el precio del servicio.",
                null,
                400
            );
        }

        $precio = validarPrecio(
            $datos["precio"]
        );

        validarServicio(
            $nombre,
            "Activo"
        );

        if (nombreDuplicado(
            $conexion,
            $nombre
        )) {
            responder(
                false,
                "Ya existe un servicio con ese nombre.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO servicios (
                nombre,
                descripcion,
                precio,
                estado
             )
             VALUES (?, ?, ?, 'Activo')"
        );

        $stmt->bind_param(
            "ssd",
            $nombre,
            $descripcion,
            $precio
        );

        $stmt->execute();

        $idNuevo = $conexion->insert_id;
        $stmt->close();

        responder(
            true,
            "Servicio registrado correctamente.",
            [
                "id_servicio" => $idNuevo,
                "nombre" => $nombre,
                "precio" => $precio,
                "estado" => "Activo"
            ],
            201
        );
    }

    /*
     * PUT
     * Actualizar un servicio.
     */
    if ($metodo === "PUT") {
        soloAdministrador();

        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_servicio"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar un ID de servicio válido.",
                null,
                400
            );
        }

        $actual = buscarServicio(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "El servicio no existe.",
                null,
                404
            );
        }

        // Conservar campos no enviados.
        $nombre = texto(
            $datos["nombre"] ??
            $actual["nombre"]
        );

        $descripcion = texto(
            $datos["descripcion"] ??
            $actual["descripcion"] ?? ""
        );

        $precio = array_key_exists("precio", $datos)
            ? validarPrecio($datos["precio"])
            : (float) $actual["precio"];

        $estado = texto(
            $datos["estado"] ??
            $actual["estado"]
        );

        validarServicio(
            $nombre,
            $estado
        );

        if (nombreDuplicado(
            $conexion,
            $nombre,
            $id
        )) {
            responder(
                false,
                "Ya existe otro servicio con ese nombre.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE servicios
             SET nombre = ?,
                 descripcion = ?,
                 precio = ?,
                 estado = ?
             WHERE id_servicio = ?"
        );

        $stmt->bind_param(
            "ssdsi",
            $nombre,
            $descripcion,
            $precio,
            $estado,
            $id
        );

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Servicio actualizado correctamente."
        );
    }

    /*
     * DELETE
     * Desactivar sin eliminar físicamente.
     */
    if ($metodo === "DELETE") {
        soloAdministrador();

        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_servicio"] ??
            $_GET["id"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar un ID de servicio válido.",
                null,
                400
            );
        }

        $actual = buscarServicio(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "El servicio no existe.",
                null,
                404
            );
        }

        if ($actual["estado"] === "Inactivo") {
            responder(
                true,
                "El servicio ya estaba inactivo."
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE servicios
             SET estado = 'Inactivo'
             WHERE id_servicio = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Servicio desactivado correctamente."
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
        "Error SQL en api/servicios.php: " .
        $error->getMessage()
    );

    if ((int) $error->getCode() === 1062) {
        responder(
            false,
            "Ya existe un servicio con ese nombre.",
            null,
            409
        );
    }

    responder(
        false,
        "No se pudo completar la operación con el servicio.",
        null,
        500
    );

} catch (Throwable $error) {
    error_log(
        "Error en api/servicios.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar la solicitud.",
        null,
        500
    );
}
