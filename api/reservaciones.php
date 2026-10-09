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

function fechaValida(string $fecha): bool
{
    $d = DateTime::createFromFormat(
        "!Y-m-d",
        $fecha
    );

    return $d !== false &&
        $d->format("Y-m-d") === $fecha;
}

function validarFechas(
    string $entrada,
    string $salida
): void {
    if (
        !fechaValida($entrada) ||
        !fechaValida($salida)
    ) {
        responder(
            false,
            "Las fechas deben tener formato YYYY-MM-DD.",
            null,
            400
        );
    }

    if ($salida <= $entrada) {
        responder(
            false,
            "La fecha de salida debe ser posterior a la entrada.",
            null,
            400
        );
    }
}

function consultarUno(
    mysqli $conexion,
    string $tabla,
    string $campo,
    int $id
): ?array {
    $permitidos = [
        "huespedes" => "id_huesped",
        "habitaciones" => "id_habitacion",
        "reservaciones" => "id_reservacion"
    ];

    if (($permitidos[$tabla] ?? null) !== $campo) {
        throw new InvalidArgumentException(
            "Consulta no permitida."
        );
    }

    $stmt = $conexion->prepare(
        "SELECT *
         FROM $tabla
         WHERE $campo = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

function comprobarHuesped(
    mysqli $conexion,
    int $id
): void {
    $huesped = consultarUno(
        $conexion,
        "huespedes",
        "id_huesped",
        $id
    );

    if (
        !$huesped ||
        $huesped["estado"] !== "Activo"
    ) {
        responder(
            false,
            "El huésped no existe o está inactivo.",
            null,
            400
        );
    }
}

function comprobarHabitacion(
    mysqli $conexion,
    int $id,
    bool $exigirDisponible = true
): void {
    $habitacion = consultarUno(
        $conexion,
        "habitaciones",
        "id_habitacion",
        $id
    );

    if (!$habitacion) {
        responder(
            false,
            "La habitación no existe.",
            null,
            404
        );
    }

    if (
        $exigirDisponible &&
        $habitacion["estado"] !== "Disponible"
    ) {
        responder(
            false,
            "La habitación no está disponible.",
            null,
            409
        );
    }

    if ($exigirDisponible) {
        $stmt = $conexion->prepare(
            "SELECT estado
             FROM tipos_habitacion
             WHERE id_tipo_habitacion = ?
             LIMIT 1"
        );

        $idTipo = (int) $habitacion["id_tipo_habitacion"];
        $stmt->bind_param("i", $idTipo);
        $stmt->execute();

        $tipo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tipo || $tipo["estado"] !== "Activo") {
            responder(
                false,
                "El tipo de habitación está inactivo.",
                null,
                409
            );
        }
    }
}

function validarEstado(
    string $estado,
    bool $nueva = false
): void {
    $permitidos = $nueva
        ? ["Pendiente", "Confirmada"]
        : [
            "Pendiente",
            "Confirmada",
            "En estancia",
            "Finalizada",
            "Cancelada"
        ];

    if (!in_array($estado, $permitidos, true)) {
        responder(
            false,
            "Estado de reservación no válido.",
            null,
            400
        );
    }
}

/*
 * La consulta coincide exactamente con
 * los disparadores de MySQL:
 *
 * trg_validar_reservacion_insert
 * trg_validar_reservacion_update
 *
 * Solo se excluyen las Canceladas.
 */
function existeTraslape(
    mysqli $conexion,
    int $idHabitacion,
    string $entrada,
    string $salida,
    int $excluirId = 0
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_reservacion
         FROM reservaciones
         WHERE id_habitacion = ?
           AND id_reservacion <> ?
           AND estado <> 'Cancelada'
           AND ? < fecha_salida
           AND ? > fecha_entrada
         LIMIT 1"
    );

    $stmt->bind_param(
        "iiss",
        $idHabitacion,
        $excluirId,
        $entrada,
        $salida
    );

    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * Comprobar si una reservación tiene
 * una estancia relacionada.
 */
function tieneEstancia(
    mysqli $conexion,
    int $idReservacion
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_estancia
         FROM estancias
         WHERE id_reservacion = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $idReservacion);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * Consulta principal con las columnas
 * reales de sistema_gestion_hotelera.
 */
function consultaReservaciones(): string
{
    return "
        SELECT
            r.id_reservacion,
            r.id_huesped,
            r.id_habitacion,
            r.id_usuario,
            CONCAT(
                h.nombre, ' ',
                h.apellido_paterno, ' ',
                COALESCE(h.apellido_materno, '')
            ) AS huesped,
            h.identificacion,
            h.telefono,
            h.correo,
            hab.numero AS habitacion,
            t.nombre AS tipo_habitacion,
            t.capacidad,
            t.tarifa_base,
            r.fecha_entrada,
            r.fecha_salida,
            DATEDIFF(
                r.fecha_salida,
                r.fecha_entrada
            ) AS noches,
            r.estado,
            r.fecha_creacion,
            r.observaciones,
            u.nombre AS registrado_por
        FROM reservaciones r
        INNER JOIN huespedes h
            ON r.id_huesped = h.id_huesped
        INNER JOIN habitaciones hab
            ON r.id_habitacion = hab.id_habitacion
        INNER JOIN tipos_habitacion t
            ON hab.id_tipo_habitacion =
               t.id_tipo_habitacion
        LEFT JOIN usuarios u
            ON r.id_usuario = u.id_usuario
    ";
}

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Disponibilidad, detalle y listado.
     */
    if ($metodo === "GET") {

        if (
            ($_GET["disponibilidad"] ?? "") === "1"
        ) {
            $entrada = texto(
                $_GET["fecha_entrada"] ?? ""
            );

            $salida = texto(
                $_GET["fecha_salida"] ?? ""
            );

            validarFechas($entrada, $salida);

            $stmt = $conexion->prepare(
                "SELECT
                    h.id_habitacion,
                    h.numero,
                    h.piso,
                    h.estado,
                    t.id_tipo_habitacion,
                    t.nombre AS tipo_habitacion,
                    t.capacidad,
                    t.tarifa_base
                 FROM habitaciones h
                 INNER JOIN tipos_habitacion t
                    ON h.id_tipo_habitacion =
                       t.id_tipo_habitacion
                 WHERE h.estado = 'Disponible'
                   AND t.estado = 'Activo'
                   AND NOT EXISTS (
                       SELECT 1
                       FROM reservaciones r
                       WHERE r.id_habitacion =
                             h.id_habitacion
                         AND r.estado <> 'Cancelada'
                         AND ? < r.fecha_salida
                         AND ? > r.fecha_entrada
                   )
                   AND NOT EXISTS (
                       SELECT 1
                       FROM estancias e
                       WHERE e.id_habitacion =
                             h.id_habitacion
                         AND e.estado = 'Activa'
                   )
                 ORDER BY h.numero ASC"
            );

            $stmt->bind_param(
                "ss",
                $entrada,
                $salida
            );

            $stmt->execute();

            $habitaciones = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            responder(
                true,
                "Disponibilidad consultada correctamente.",
                $habitaciones
            );
        }

        if (isset($_GET["id"])) {
            $id = enteroPositivo($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de reservación no válido.",
                    null,
                    400
                );
            }

            $sql = consultaReservaciones() .
                " WHERE r.id_reservacion = ?";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $reservacion = $stmt->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$reservacion) {
                responder(
                    false,
                    "Reservación no encontrada.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Reservación encontrada.",
                $reservacion
            );
        }

        $sql = consultaReservaciones() .
            " ORDER BY r.fecha_creacion DESC,
                       r.id_reservacion DESC";

        $resultado = $conexion->query($sql);

        responder(
            true,
            "Reservaciones obtenidas correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Crear una reservación.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $idHuesped = enteroPositivo(
            $datos["id_huesped"] ?? null
        );

        $idHabitacion = enteroPositivo(
            $datos["id_habitacion"] ?? null
        );

        $entrada = texto(
            $datos["fecha_entrada"] ?? ""
        );

        $salida = texto(
            $datos["fecha_salida"] ?? ""
        );

        $estado = texto(
            $datos["estado"] ?? "Pendiente"
        );

        $observaciones = texto(
            $datos["observaciones"] ?? ""
        );

        if (
            $idHuesped === 0 ||
            $idHabitacion === 0
        ) {
            responder(
                false,
                "Huésped y habitación son obligatorios.",
                null,
                400
            );
        }

        validarFechas($entrada, $salida);
        validarEstado($estado, true);

        comprobarHuesped($conexion, $idHuesped);
        comprobarHabitacion(
            $conexion,
            $idHabitacion
        );

        $idUsuario = enteroPositivo(
            $_SESSION["id_usuario"] ?? null
        );

        if ($idUsuario === 0) {
            responder(
                false,
                "No se pudo identificar al usuario de la sesión.",
                null,
                401
            );
        }

        if (existeTraslape(
            $conexion,
            $idHabitacion,
            $entrada,
            $salida
        )) {
            responder(
                false,
                "La habitación ya tiene una reservación durante ese periodo.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO reservaciones (
                id_huesped,
                id_habitacion,
                id_usuario,
                fecha_entrada,
                fecha_salida,
                estado,
                observaciones
             )
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "iiissss",
            $idHuesped,
            $idHabitacion,
            $idUsuario,
            $entrada,
            $salida,
            $estado,
            $observaciones
        );

        $stmt->execute();

        $idNuevo = $conexion->insert_id;
        $stmt->close();

        responder(
            true,
            "Reservación registrada correctamente.",
            ["id_reservacion" => $idNuevo],
            201
        );
    }

    /*
     * PUT
     * Actualizar una reservación.
     */
    if ($metodo === "PUT") {
        $datos = obtenerJSON();

        $id = enteroPositivo(
            $datos["id_reservacion"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID de la reservación.",
                null,
                400
            );
        }

        $actual = consultarUno(
            $conexion,
            "reservaciones",
            "id_reservacion",
            $id
        );

        if (!$actual) {
            responder(
                false,
                "La reservación no existe.",
                null,
                404
            );
        }

        if (in_array(
            $actual["estado"],
            ["Cancelada", "Finalizada"],
            true
        )) {
            responder(
                false,
                "No se puede modificar una reservación cancelada o finalizada.",
                null,
                409
            );
        }

        if (tieneEstancia($conexion, $id)) {
            responder(
                false,
                "La reservación ya tiene una estancia registrada. Debe gestionarse desde el módulo de estancias.",
                null,
                409
            );
        }

        if ($actual["estado"] === "En estancia") {
            responder(
                false,
                "No se puede modificar una reservación en estancia desde este módulo.",
                null,
                409
            );
        }

        $idHuesped = enteroPositivo(
            $datos["id_huesped"] ??
            $actual["id_huesped"]
        );

        $idHabitacion = enteroPositivo(
            $datos["id_habitacion"] ??
            $actual["id_habitacion"]
        );

        $entrada = texto(
            $datos["fecha_entrada"] ??
            $actual["fecha_entrada"]
        );

        $salida = texto(
            $datos["fecha_salida"] ??
            $actual["fecha_salida"]
        );

        $estado = texto(
            $datos["estado"] ??
            $actual["estado"]
        );

        $observaciones = texto(
            $datos["observaciones"] ??
            $actual["observaciones"] ?? ""
        );

        if (
            $idHuesped === 0 ||
            $idHabitacion === 0
        ) {
            responder(
                false,
                "Huésped o habitación no válidos.",
                null,
                400
            );
        }

        validarFechas($entrada, $salida);

        // En este módulo solo se gestionan
        // reservaciones pendientes o confirmadas.
        if (!in_array(
            $estado,
            ["Pendiente", "Confirmada"],
            true
        )) {
            responder(
                false,
                "Desde este módulo solo puedes establecer Pendiente o Confirmada. Usa DELETE para cancelar.",
                null,
                400
            );
        }

        comprobarHuesped(
            $conexion,
            $idHuesped
        );

        $cambiaHabitacion =
            $idHabitacion !==
            (int) $actual["id_habitacion"];

        comprobarHabitacion(
            $conexion,
            $idHabitacion,
            $cambiaHabitacion
        );

        if (existeTraslape(
            $conexion,
            $idHabitacion,
            $entrada,
            $salida,
            $id
        )) {
            responder(
                false,
                "Existe otra reservación para esa habitación durante las fechas seleccionadas.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE reservaciones
             SET id_huesped = ?,
                 id_habitacion = ?,
                 fecha_entrada = ?,
                 fecha_salida = ?,
                 estado = ?,
                 observaciones = ?
             WHERE id_reservacion = ?"
        );

        $stmt->bind_param(
            "iissssi",
            $idHuesped,
            $idHabitacion,
            $entrada,
            $salida,
            $estado,
            $observaciones,
            $id
        );

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Reservación actualizada correctamente."
        );
    }

    /*
     * DELETE
     * Cancelación lógica.
     * No elimina físicamente registros.
     */
    if ($metodo === "DELETE") {
        $datos = obtenerJSON();

        $id = enteroPositivo(
            $datos["id_reservacion"] ??
            $_GET["id"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID de la reservación.",
                null,
                400
            );
        }

        $actual = consultarUno(
            $conexion,
            "reservaciones",
            "id_reservacion",
            $id
        );

        if (!$actual) {
            responder(
                false,
                "La reservación no existe.",
                null,
                404
            );
        }

        if ($actual["estado"] === "Cancelada") {
            responder(
                true,
                "La reservación ya estaba cancelada."
            );
        }

        if ($actual["estado"] === "Finalizada") {
            responder(
                false,
                "Una reservación finalizada no puede cancelarse.",
                null,
                409
            );
        }

        if (
            $actual["estado"] === "En estancia" ||
            tieneEstancia($conexion, $id)
        ) {
            responder(
                false,
                "No puedes cancelar una reservación que tiene una estancia registrada.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE reservaciones
             SET estado = 'Cancelada'
             WHERE id_reservacion = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Reservación cancelada correctamente."
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
        "Error SQL en reservaciones.php: " .
        $error->getMessage()
    );

    $codigoSQL = (int) $error->getCode();

    if (
        $codigoSQL === 1644 ||
        $error->getSqlState() === "45000"
    ) {
        responder(
            false,
            "No se puede guardar la reservación: la habitación ya está reservada durante ese periodo.",
            null,
            409
        );
    }

    if ($codigoSQL === 1451 || $codigoSQL === 1452) {
        responder(
            false,
            "No se puede completar la operación porque existen datos relacionados no válidos.",
            null,
            409
        );
    }

    responder(
        false,
        "No se pudo completar la operación de reservación.",
        null,
        500
    );

} catch (Throwable $error) {
    error_log(
        "Error en reservaciones.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar la solicitud.",
        null,
        500
    );
}
