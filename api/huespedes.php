<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT, DELETE");

require_once __DIR__ . "/auth.php";

// Administrador y Recepcionista.
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

function columnasHuesped(): string
{
    return "
        id_huesped,
        identificacion,
        nombre,
        apellido_paterno,
        apellido_materno,
        telefono,
        correo,
        direccion,
        fecha_registro,
        estado
    ";
}

function buscarHuesped(
    mysqli $conexion,
    int $id
): ?array {
    $sql = "SELECT " . columnasHuesped() . "
            FROM huespedes
            WHERE id_huesped = ?
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $huesped = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $huesped ?: null;
}

function validarHuesped(
    string $identificacion,
    string $nombre,
    string $apellidoPaterno,
    string $correo,
    string $estado
): void {
    if (
        $identificacion === "" ||
        $nombre === "" ||
        $apellidoPaterno === ""
    ) {
        responder(
            false,
            "Identificación, nombre y apellido paterno son obligatorios.",
            null,
            400
        );
    }

    if (
        $correo !== "" &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {
        responder(
            false,
            "El correo electrónico no es válido.",
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

function identificacionDuplicada(
    mysqli $conexion,
    string $identificacion,
    int $excluirId = 0
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_huesped
         FROM huespedes
         WHERE identificacion = ?
           AND id_huesped <> ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "si",
        $identificacion,
        $excluirId
    );

    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * Impedir desactivar un huésped con
 * reservaciones pendientes, confirmadas
 * o en estancia.
 */
function tieneReservacionesVigentes(
    mysqli $conexion,
    int $idHuesped
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_reservacion
         FROM reservaciones
         WHERE id_huesped = ?
           AND estado IN (
               'Pendiente',
               'Confirmada',
               'En estancia'
           )
         LIMIT 1"
    );

    $stmt->bind_param("i", $idHuesped);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * Comprobar estancias activas.
 */
function tieneEstanciaActiva(
    mysqli $conexion,
    int $idHuesped
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_estancia
         FROM estancias
         WHERE id_huesped = ?
           AND estado = 'Activa'
         LIMIT 1"
    );

    $stmt->bind_param("i", $idHuesped);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * Evitar desactivaciones que afecten
 * operaciones vigentes del hotel.
 */
function validarDesactivacion(
    mysqli $conexion,
    int $idHuesped
): void {
    if (tieneEstanciaActiva(
        $conexion,
        $idHuesped
    )) {
        responder(
            false,
            "No puedes desactivar al huésped porque tiene una estancia activa.",
            null,
            409
        );
    }

    if (tieneReservacionesVigentes(
        $conexion,
        $idHuesped
    )) {
        responder(
            false,
            "No puedes desactivar al huésped porque tiene reservaciones vigentes.",
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
     * Consultar y buscar huéspedes.
     */
    if ($metodo === "GET") {

        if (isset($_GET["id"])) {
            $id = idValido($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de huésped no válido.",
                    null,
                    400
                );
            }

            $huesped = buscarHuesped(
                $conexion,
                $id
            );

            if (!$huesped) {
                responder(
                    false,
                    "Huésped no encontrado.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Huésped encontrado.",
                $huesped
            );
        }

        if (isset($_GET["buscar"])) {
            $termino = texto(
                $_GET["buscar"]
            );

            if ($termino !== "") {
                $buscar = "%" . $termino . "%";

                $sql = "SELECT " .
                    columnasHuesped() . "
                    FROM huespedes
                    WHERE identificacion LIKE ?
                       OR nombre LIKE ?
                       OR apellido_paterno LIKE ?
                       OR apellido_materno LIKE ?
                       OR telefono LIKE ?
                       OR correo LIKE ?
                    ORDER BY nombre ASC";

                $stmt = $conexion->prepare($sql);

                $stmt->bind_param(
                    "ssssss",
                    $buscar,
                    $buscar,
                    $buscar,
                    $buscar,
                    $buscar,
                    $buscar
                );

                $stmt->execute();

                $huespedes = $stmt->get_result()
                    ->fetch_all(MYSQLI_ASSOC);

                $stmt->close();

                responder(
                    true,
                    "Búsqueda realizada correctamente.",
                    $huespedes
                );
            }
        }

        $sql = "SELECT " . columnasHuesped() . "
                FROM huespedes
                ORDER BY id_huesped DESC";

        $resultado = $conexion->query($sql);

        responder(
            true,
            "Huéspedes obtenidos correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Registrar huésped.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $identificacion = texto(
            $datos["identificacion"] ?? ""
        );

        $nombre = texto(
            $datos["nombre"] ?? ""
        );

        $apellidoPaterno = texto(
            $datos["apellido_paterno"] ?? ""
        );

        $apellidoMaterno = texto(
            $datos["apellido_materno"] ?? ""
        );

        $telefono = texto(
            $datos["telefono"] ?? ""
        );

        $correo = texto(
            $datos["correo"] ?? ""
        );

        $direccion = texto(
            $datos["direccion"] ?? ""
        );

        validarHuesped(
            $identificacion,
            $nombre,
            $apellidoPaterno,
            $correo,
            "Activo"
        );

        if (identificacionDuplicada(
            $conexion,
            $identificacion
        )) {
            responder(
                false,
                "Ya existe un huésped con esa identificación.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO huespedes (
                identificacion,
                nombre,
                apellido_paterno,
                apellido_materno,
                telefono,
                correo,
                direccion,
                estado
             )
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo')"
        );

        $stmt->bind_param(
            "sssssss",
            $identificacion,
            $nombre,
            $apellidoPaterno,
            $apellidoMaterno,
            $telefono,
            $correo,
            $direccion
        );

        $stmt->execute();

        $idNuevo = $conexion->insert_id;
        $stmt->close();

        responder(
            true,
            "Huésped registrado correctamente.",
            ["id_huesped" => $idNuevo],
            201
        );
    }

    /*
     * PUT
     * Actualizar huésped.
     */
    if ($metodo === "PUT") {
        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_huesped"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID del huésped.",
                null,
                400
            );
        }

        $actual = buscarHuesped(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "El huésped no existe.",
                null,
                404
            );
        }

        $identificacion = texto(
            $datos["identificacion"] ??
            $actual["identificacion"]
        );

        $nombre = texto(
            $datos["nombre"] ??
            $actual["nombre"]
        );

        $apellidoPaterno = texto(
            $datos["apellido_paterno"] ??
            $actual["apellido_paterno"]
        );

        $apellidoMaterno = texto(
            $datos["apellido_materno"] ??
            $actual["apellido_materno"] ?? ""
        );

        $telefono = texto(
            $datos["telefono"] ??
            $actual["telefono"] ?? ""
        );

        $correo = texto(
            $datos["correo"] ??
            $actual["correo"] ?? ""
        );

        $direccion = texto(
            $datos["direccion"] ??
            $actual["direccion"] ?? ""
        );

        $estado = texto(
            $datos["estado"] ??
            $actual["estado"]
        );

        validarHuesped(
            $identificacion,
            $nombre,
            $apellidoPaterno,
            $correo,
            $estado
        );

        if (identificacionDuplicada(
            $conexion,
            $identificacion,
            $id
        )) {
            responder(
                false,
                "Ya existe otro huésped con esa identificación.",
                null,
                409
            );
        }

        /*
         * También proteger el cambio
         * de estado desde PUT.
         */
        if (
            $estado === "Inactivo" &&
            $actual["estado"] !== "Inactivo"
        ) {
            validarDesactivacion(
                $conexion,
                $id
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE huespedes
             SET identificacion = ?,
                 nombre = ?,
                 apellido_paterno = ?,
                 apellido_materno = ?,
                 telefono = ?,
                 correo = ?,
                 direccion = ?,
                 estado = ?
             WHERE id_huesped = ?"
        );

        $stmt->bind_param(
            "ssssssssi",
            $identificacion,
            $nombre,
            $apellidoPaterno,
            $apellidoMaterno,
            $telefono,
            $correo,
            $direccion,
            $estado,
            $id
        );

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Huésped actualizado correctamente."
        );
    }

    /*
     * DELETE
     * Desactivar sin borrar historial.
     */
    if ($metodo === "DELETE") {
        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_huesped"] ??
            $_GET["id"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID del huésped.",
                null,
                400
            );
        }

        $actual = buscarHuesped(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "El huésped no existe.",
                null,
                404
            );
        }

        if ($actual["estado"] === "Inactivo") {
            responder(
                true,
                "El huésped ya estaba inactivo."
            );
        }

        validarDesactivacion(
            $conexion,
            $id
        );

        $stmt = $conexion->prepare(
            "UPDATE huespedes
             SET estado = 'Inactivo'
             WHERE id_huesped = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Huésped desactivado correctamente."
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
        "Error SQL en huespedes.php: " .
        $error->getMessage()
    );

    if ((int) $error->getCode() === 1062) {
        responder(
            false,
            "Ya existe un huésped con esa identificación.",
            null,
            409
        );
    }

    responder(
        false,
        "No se pudo completar la operación con el huésped.",
        null,
        500
    );

} catch (Throwable $error) {
    error_log(
        "Error en huespedes.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar la solicitud.",
        null,
        500
    );
}
