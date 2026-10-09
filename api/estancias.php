<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT, PATCH");

require_once __DIR__ . "/auth.php";

// Solo Administrador y Recepcionista.
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

function buscarEstancia(
    mysqli $conexion,
    int $id
): ?array {
    $stmt = $conexion->prepare(
        "SELECT *
         FROM estancias
         WHERE id_estancia = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

function consultaEstancias(): string
{
    return "
        SELECT
            e.id_estancia,
            e.id_reservacion,
            e.id_huesped,
            e.id_habitacion,
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
            t.tarifa_base,
            e.fecha_checkin,
            e.check_out_previsto,
            e.fecha_checkout,
            e.importe_base,
            e.total_servicios,
            e.total,
            e.estado,
            e.observaciones
        FROM estancias e
        INNER JOIN huespedes h
            ON e.id_huesped = h.id_huesped
        INNER JOIN habitaciones hab
            ON e.id_habitacion = hab.id_habitacion
        INNER JOIN tipos_habitacion t
            ON hab.id_tipo_habitacion =
               t.id_tipo_habitacion
    ";
}

function tieneEstanciaReservacion(
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

function tieneEstanciaActivaHabitacion(
    mysqli $conexion,
    int $idHabitacion
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_estancia
         FROM estancias
         WHERE id_habitacion = ?
           AND estado = 'Activa'
         LIMIT 1"
    );

    $stmt->bind_param("i", $idHabitacion);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * Calcular noches entre dos fechas.
 * Se cobra como mínimo una noche.
 */
function calcularNoches(
    string $entrada,
    string $salida
): int {
    $inicio = new DateTime($entrada);
    $fin = new DateTime($salida);

    $dias = (int) $inicio->diff($fin)->days;

    return max(1, $dias);
}

/*
 * Calcular servicios registrados.
 */
function calcularServicios(
    mysqli $conexion,
    int $idEstancia
): float {
    $stmt = $conexion->prepare(
        "SELECT COALESCE(SUM(importe), 0) AS total
         FROM consumos
         WHERE id_estancia = ?"
    );

    $stmt->bind_param("i", $idEstancia);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (float) ($fila["total"] ?? 0);
}

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Consultar estancias.
     */
    if ($metodo === "GET") {

        if (isset($_GET["id"])) {
            $id = idValido($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de estancia no válido.",
                    null,
                    400
                );
            }

            $sql = consultaEstancias() .
                " WHERE e.id_estancia = ?";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $estancia = $stmt->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$estancia) {
                responder(
                    false,
                    "Estancia no encontrada.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Estancia encontrada.",
                $estancia
            );
        }

        if (isset($_GET["estado"])) {
            $estado = texto($_GET["estado"]);

            if (!in_array(
                $estado,
                ["Activa", "Finalizada"],
                true
            )) {
                responder(
                    false,
                    "Estado de estancia no válido.",
                    null,
                    400
                );
            }

            $sql = consultaEstancias() .
                " WHERE e.estado = ?
                  ORDER BY e.fecha_checkin DESC";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $estado);
            $stmt->execute();

            $estancias = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            responder(
                true,
                "Estancias obtenidas correctamente.",
                $estancias
            );
        }

        $resultado = $conexion->query(
            consultaEstancias() .
            " ORDER BY e.fecha_checkin DESC"
        );

        responder(
            true,
            "Estancias obtenidas correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Registrar check-in.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $idReservacion = idValido(
            $datos["id_reservacion"] ?? null
        );

        $observaciones = texto(
            $datos["observaciones"] ?? ""
        );

        if ($idReservacion === 0) {
            responder(
                false,
                "Debes indicar la reservación.",
                null,
                400
            );
        }

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "SELECT
                r.id_reservacion,
                r.id_huesped,
                r.id_habitacion,
                r.fecha_entrada,
                r.fecha_salida,
                r.estado,
                h.estado AS estado_habitacion,
                t.tarifa_base
             FROM reservaciones r
             INNER JOIN habitaciones h
                ON r.id_habitacion = h.id_habitacion
             INNER JOIN tipos_habitacion t
                ON h.id_tipo_habitacion =
                   t.id_tipo_habitacion
             WHERE r.id_reservacion = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param("i", $idReservacion);
        $stmt->execute();

        $reservacion = $stmt->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$reservacion) {
            $conexion->rollback();

            responder(
                false,
                "La reservación no existe.",
                null,
                404
            );
        }

        if ($reservacion["estado"] !== "Confirmada") {
            $conexion->rollback();

            responder(
                false,
                "Solo puedes registrar el check-in de una reservación confirmada.",
                null,
                409
            );
        }

        if ($reservacion["estado_habitacion"] !== "Disponible") {
            $conexion->rollback();

            responder(
                false,
                "La habitación no está disponible para check-in.",
                null,
                409
            );
        }

        if (tieneEstanciaReservacion(
            $conexion,
            $idReservacion
        )) {
            $conexion->rollback();

            responder(
                false,
                "Esta reservación ya tiene una estancia registrada.",
                null,
                409
            );
        }

        $idHabitacion = (int) $reservacion["id_habitacion"];

        if (tieneEstanciaActivaHabitacion(
            $conexion,
            $idHabitacion
        )) {
            $conexion->rollback();

            responder(
                false,
                "La habitación ya tiene una estancia activa.",
                null,
                409
            );
        }

        $idHuesped = (int) $reservacion["id_huesped"];
        $fechaEntrada = $reservacion["fecha_entrada"];
        $fechaSalida = $reservacion["fecha_salida"];

        $noches = calcularNoches(
            $fechaEntrada,
            $fechaSalida
        );

        $tarifa = (float) $reservacion["tarifa_base"];
        $importeBase = round($noches * $tarifa, 2);

        $stmt = $conexion->prepare(
            "INSERT INTO estancias (
                id_reservacion,
                id_huesped,
                id_habitacion,
                fecha_checkin,
                check_out_previsto,
                importe_base,
                total_servicios,
                total,
                estado,
                observaciones
             )
             VALUES (
                ?, ?, ?, NOW(), ?,
                ?, 0, ?, 'Activa', ?
             )"
        );

        $stmt->bind_param(
            "iiisdds",
            $idReservacion,
            $idHuesped,
            $idHabitacion,
            $fechaSalida,
            $importeBase,
            $importeBase,
            $observaciones
        );

        $stmt->execute();

        $idEstancia = $conexion->insert_id;
        $stmt->close();

        $stmt = $conexion->prepare(
            "UPDATE reservaciones
             SET estado = 'En estancia'
             WHERE id_reservacion = ?"
        );

        $stmt->bind_param("i", $idReservacion);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexion->prepare(
            "UPDATE habitaciones
             SET estado = 'Ocupada'
             WHERE id_habitacion = ?"
        );

        $stmt->bind_param("i", $idHabitacion);
        $stmt->execute();
        $stmt->close();

        $conexion->commit();

        responder(
            true,
            "Check-in registrado correctamente.",
            [
                "id_estancia" => $idEstancia,
                "id_reservacion" => $idReservacion,
                "noches" => $noches,
                "importe_base" => $importeBase
            ],
            201
        );
    }

    /*
     * PUT
     * Actualizar observaciones.
     */
    if ($metodo === "PUT") {
        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_estancia"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes indicar el ID de estancia.",
                null,
                400
            );
        }

        $actual = buscarEstancia(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "La estancia no existe.",
                null,
                404
            );
        }

        if ($actual["estado"] !== "Activa") {
            responder(
                false,
                "No puedes editar una estancia finalizada.",
                null,
                409
            );
        }

        $observaciones = texto(
            $datos["observaciones"] ??
            $actual["observaciones"] ?? ""
        );

        $stmt = $conexion->prepare(
            "UPDATE estancias
             SET observaciones = ?
             WHERE id_estancia = ?"
        );

        $stmt->bind_param(
            "si",
            $observaciones,
            $id
        );

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Estancia actualizada correctamente."
        );
    }

    /*
     * PATCH
     * Registrar check-out.
     */
    if ($metodo === "PATCH") {
        $datos = obtenerJSON();

        $id = idValido(
            $datos["id_estancia"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes indicar el ID de estancia.",
                null,
                400
            );
        }

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "SELECT *
             FROM estancias
             WHERE id_estancia = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $estancia = $stmt->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$estancia) {
            $conexion->rollback();

            responder(
                false,
                "La estancia no existe.",
                null,
                404
            );
        }

        if ($estancia["estado"] !== "Activa") {
            $conexion->rollback();

            responder(
                false,
                "La estancia ya está finalizada.",
                null,
                409
            );
        }

        $totalServicios = calcularServicios(
            $conexion,
            $id
        );

        $importeBase = (float) $estancia["importe_base"];
        $total = round(
            $importeBase + $totalServicios,
            2
        );

        $stmt = $conexion->prepare(
            "UPDATE estancias
             SET fecha_checkout = NOW(),
                 total_servicios = ?,
                 total = ?,
                 estado = 'Finalizada'
             WHERE id_estancia = ?"
        );

        $stmt->bind_param(
            "ddi",
            $totalServicios,
            $total,
            $id
        );

        $stmt->execute();
        $stmt->close();

        $idReservacion = (int) $estancia["id_reservacion"];
        $idHabitacion = (int) $estancia["id_habitacion"];

        $stmt = $conexion->prepare(
            "UPDATE reservaciones
             SET estado = 'Finalizada'
             WHERE id_reservacion = ?"
        );

        $stmt->bind_param("i", $idReservacion);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexion->prepare(
            "UPDATE habitaciones
             SET estado = 'Disponible'
             WHERE id_habitacion = ?"
        );

        $stmt->bind_param("i", $idHabitacion);
        $stmt->execute();
        $stmt->close();

        $conexion->commit();

        responder(
            true,
            "Check-out registrado correctamente.",
            [
                "id_estancia" => $id,
                "importe_base" => $importeBase,
                "total_servicios" => $totalServicios,
                "total" => $total
            ]
        );
    }

    responder(
        false,
        "Método HTTP no permitido.",
        null,
        405
    );

} catch (mysqli_sql_exception $error) {
    if (
        isset($conexion) &&
        $conexion->errno === 0
    ) {
        // El estado de la conexión no indica
        // por sí solo si hay transacción abierta.
    }

    if (isset($conexion)) {
        try {
            $conexion->rollback();
        } catch (Throwable $ignorado) {
        }
    }

    error_log(
        "Error SQL en estancias.php: " .
        $error->getMessage()
    );

    if ((int) $error->getCode() === 1062) {
        responder(
            false,
            "La reservación ya tiene una estancia registrada.",
            null,
            409
        );
    }

    responder(
        false,
        "No se pudo completar la operación de estancia.",
        null,
        500
    );

} catch (Throwable $error) {
    if (isset($conexion)) {
        try {
            $conexion->rollback();
        } catch (Throwable $ignorado) {
        }
    }

    error_log(
        "Error en estancias.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar la solicitud.",
        null,
        500
    );
}
