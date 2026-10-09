<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT, DELETE");

require_once __DIR__ . "/auth.php";

// Administrador y Recepcionista pueden consultar.
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

function enteroPositivo($valor): int
{
    if (
        !is_int($valor) &&
        !(is_string($valor) && ctype_digit($valor))
    ) {
        return 0;
    }

    $numero = filter_var(
        $valor,
        FILTER_VALIDATE_INT
    );

    return $numero !== false && $numero > 0
        ? $numero
        : 0;
}

function buscarTipo(
    mysqli $conexion,
    int $id
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            id_tipo_habitacion,
            nombre,
            descripcion,
            capacidad,
            tarifa_base,
            estado
         FROM tipos_habitacion
         WHERE id_tipo_habitacion = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $tipo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $tipo ?: null;
}

function nombreDuplicado(
    mysqli $conexion,
    string $nombre,
    int $excluirId = 0
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_tipo_habitacion
         FROM tipos_habitacion
         WHERE nombre = ?
           AND id_tipo_habitacion <> ?
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

function validarDatos(
    string $nombre,
    int $capacidad,
    float $tarifa,
    string $estado
): void {
    if ($nombre === "") {
        responder(
            false,
            "El nombre del tipo de habitación es obligatorio.",
            null,
            400
        );
    }

    if (mb_strlen($nombre) > 100) {
        responder(
            false,
            "El nombre no puede superar 100 caracteres.",
            null,
            400
        );
    }

    if ($capacidad <= 0) {
        responder(
            false,
            "La capacidad debe ser mayor a cero.",
            null,
            400
        );
    }

    if (!is_finite($tarifa) || $tarifa < 0) {
        responder(
            false,
            "La tarifa base no es válida.",
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

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Consultar tipos de habitación.
     * Ambos roles.
     */
    if ($metodo === "GET") {

        if (isset($_GET["id"])) {
            $id = enteroPositivo($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de tipo de habitación no válido.",
                    null,
                    400
                );
            }

            $tipo = buscarTipo($conexion, $id);

            if (!$tipo) {
                responder(
                    false,
                    "Tipo de habitación no encontrado.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Tipo de habitación encontrado.",
                $tipo
            );
        }

        $sql = "
            SELECT
                id_tipo_habitacion,
                nombre,
                descripcion,
                capacidad,
                tarifa_base,
                estado
            FROM tipos_habitacion
        ";

        $estadoFiltro = $_GET["estado"] ?? null;

        if ($estadoFiltro !== null) {
            if (
                !is_string($estadoFiltro) ||
                !in_array(
                    $estadoFiltro,
                    ["Activo", "Inactivo"],
                    true
                )
            ) {
                responder(
                    false,
                    "Filtro de estado no válido.",
                    null,
                    400
                );
            }

            $sql .= " WHERE estado = ?";
        }

        $sql .= " ORDER BY nombre ASC";

        if ($estadoFiltro !== null) {
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $estadoFiltro);
            $stmt->execute();

            $tipos = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();
        } else {
            $resultado = $conexion->query($sql);
            $tipos = $resultado->fetch_all(MYSQLI_ASSOC);
        }

        responder(
            true,
            "Tipos de habitación obtenidos correctamente.",
            $tipos
        );
    }

    /*
     * Las modificaciones son exclusivas
     * del Administrador.
     */
    if (in_array(
        $metodo,
        ["POST", "PUT", "DELETE"],
        true
    )) {
        soloAdministrador();
    }

    /*
     * POST
     * Registrar un tipo de habitación.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $nombre = texto($datos["nombre"] ?? "");
        $descripcion = texto(
            $datos["descripcion"] ?? ""
        );

        $capacidad = enteroPositivo(
            $datos["capacidad"] ?? null
        );

        $tarifa = filter_var(
            $datos["tarifa_base"] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $estado = texto(
            $datos["estado"] ?? "Activo"
        );

        if ($tarifa === false) {
            responder(
                false,
                "La tarifa base debe ser un número válido.",
                null,
                400
            );
        }

        validarDatos(
            $nombre,
            $capacidad,
            $tarifa,
            $estado
        );

        if (nombreDuplicado($conexion, $nombre)) {
            responder(
                false,
                "Ya existe un tipo de habitación con ese nombre.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO tipos_habitacion (
                nombre,
                descripcion,
                capacidad,
                tarifa_base,
                estado
             )
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssids",
            $nombre,
            $descripcion,
            $capacidad,
            $tarifa,
            $estado
        );

        $stmt->execute();

        $idNuevo = $conexion->insert_id;
        $stmt->close();

        responder(
            true,
            "Tipo de habitación registrado correctamente.",
            ["id_tipo_habitacion" => $idNuevo],
            201
        );
    }

    /*
     * PUT
     * Actualizar tipo de habitación.
     */
    if ($metodo === "PUT") {
        $datos = obtenerJSON();

        $id = enteroPositivo(
            $datos["id_tipo_habitacion"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID del tipo de habitación.",
                null,
                400
            );
        }

        $actual = buscarTipo($conexion, $id);

        if (!$actual) {
            responder(
                false,
                "Tipo de habitación no encontrado.",
                null,
                404
            );
        }

        $nombre = texto(
            $datos["nombre"] ?? $actual["nombre"]
        );

        $descripcion = texto(
            $datos["descripcion"] ??
            $actual["descripcion"] ?? ""
        );

        $capacidad = enteroPositivo(
            $datos["capacidad"] ?? $actual["capacidad"]
        );

        $tarifa = filter_var(
            $datos["tarifa_base"] ??
            $actual["tarifa_base"],
            FILTER_VALIDATE_FLOAT
        );

        $estado = texto(
            $datos["estado"] ?? $actual["estado"]
        );

        if ($tarifa === false) {
            responder(
                false,
                "La tarifa base no es válida.",
                null,
                400
            );
        }

        validarDatos(
            $nombre,
            $capacidad,
            $tarifa,
            $estado
        );

        if (nombreDuplicado(
            $conexion,
            $nombre,
            $id
        )) {
            responder(
                false,
                "Ya existe otro tipo de habitación con ese nombre.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE tipos_habitacion
             SET nombre = ?,
                 descripcion = ?,
                 capacidad = ?,
                 tarifa_base = ?,
                 estado = ?
             WHERE id_tipo_habitacion = ?"
        );

        $stmt->bind_param(
            "ssidsi",
            $nombre,
            $descripcion,
            $capacidad,
            $tarifa,
            $estado,
            $id
        );

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Tipo de habitación actualizado correctamente."
        );
    }

    /*
     * DELETE
     * Desactivar sin eliminar físicamente.
     */
    if ($metodo === "DELETE") {
        $datos = obtenerJSON();

        $id = enteroPositivo(
            $datos["id_tipo_habitacion"] ??
            $_GET["id"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID del tipo de habitación.",
                null,
                400
            );
        }

        $actual = buscarTipo($conexion, $id);

        if (!$actual) {
            responder(
                false,
                "Tipo de habitación no encontrado.",
                null,
                404
            );
        }

        if ($actual["estado"] === "Inactivo") {
            responder(
                true,
                "El tipo de habitación ya estaba inactivo."
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE tipos_habitacion
             SET estado = 'Inactivo'
             WHERE id_tipo_habitacion = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Tipo de habitación desactivado correctamente."
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
        "Error SQL en tipos_habitacion.php: " .
        $error->getMessage()
    );

    $codigo = (int) $error->getCode() === 1062
        ? 409
        : 500;

    responder(
        false,
        $codigo === 409
            ? "Ya existe un registro con ese nombre."
            : "Error al procesar el tipo de habitación.",
        null,
        $codigo
    );

} catch (Throwable $error) {
    error_log(
        "Error en tipos_habitacion.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error interno.",
        null,
        500
    );
}
