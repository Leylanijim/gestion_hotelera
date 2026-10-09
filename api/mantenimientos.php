<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT");

require_once __DIR__ . "/auth.php";

/*
 * Ambos roles pueden consultar y reportar.
 * Solo el administrador puede cambiar estados.
 */
personalHotel();

$metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

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

function obtenerJSON(): array {
    $contenido = file_get_contents("php://input");

    if (!$contenido) {
        return [];
    }

    $datos = json_decode($contenido, true);

    if (!is_array($datos)) {
        responder(
            false,
            "El contenido JSON no es válido.",
            null,
            400
        );
    }

    return $datos;
}

function texto($valor, int $maximo = 1000): string {
    if (!is_string($valor)) {
        return "";
    }

    return substr(trim($valor), 0, $maximo);
}

function consultarMantenimiento(
    mysqli $conexion,
    int $id
): ?array {
    $sql = "
        SELECT
            m.id_mantenimiento,
            m.id_habitacion,
            h.numero AS habitacion,
            h.piso,
            h.estado AS estado_habitacion,
            m.motivo,
            m.fecha_inicio,
            m.fecha_fin,
            m.estado,
            m.observaciones
        FROM mantenimientos m
        INNER JOIN habitaciones h
            ON h.id_habitacion = m.id_habitacion
        WHERE m.id_mantenimiento = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

if (!in_array($metodo, ["GET", "POST", "PUT"], true)) {
    responder(
        false,
        "Método HTTP no permitido.",
        null,
        405
    );
}

try {
    /*
     * Se usa la conexión configurada
     * en el proyecto.
     */
    $database = new Database();
    $conexion = $database->conectar();

    if (!$conexion instanceof mysqli) {
        throw new RuntimeException(
            "No se pudo establecer la conexión."
        );
    }

    /*
     * ====================================
     * GET - CONSULTAR MANTENIMIENTOS
     * ====================================
     */
    if ($metodo === "GET") {

        if (isset($_GET["id"])) {
            $id = filter_var(
                $_GET["id"],
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                responder(
                    false,
                    "ID de mantenimiento inválido.",
                    null,
                    400
                );
            }

            $mantenimiento = consultarMantenimiento(
                $conexion,
                $id
            );

            if (!$mantenimiento) {
                responder(
                    false,
                    "Mantenimiento no encontrado.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Mantenimiento encontrado.",
                $mantenimiento
            );
        }

        $sql = "
            SELECT
                m.id_mantenimiento,
                m.id_habitacion,
                h.numero AS habitacion,
                h.piso,
                h.estado AS estado_habitacion,
                m.motivo,
                m.fecha_inicio,
                m.fecha_fin,
                m.estado,
                m.observaciones
            FROM mantenimientos m
            INNER JOIN habitaciones h
                ON h.id_habitacion = m.id_habitacion
            ORDER BY
                m.fecha_inicio DESC,
                m.id_mantenimiento DESC
        ";

        $resultado = $conexion->query($sql);
        $mantenimientos = [];

        while ($fila = $resultado->fetch_assoc()) {
            $mantenimientos[] = $fila;
        }

        $resultado->free();

        responder(
            true,
            "Mantenimientos obtenidos correctamente.",
            $mantenimientos
        );
    }

    /*
     * ====================================
     * POST - REPORTAR MANTENIMIENTO
     * ====================================
     */
    if ($metodo === "POST") {

        $datos = obtenerJSON();

        $idHabitacion = filter_var(
            $datos["id_habitacion"] ?? null,
            FILTER_VALIDATE_INT
        );

        $motivo = texto(
            $datos["motivo"] ?? "",
            500
        );

        $observaciones = texto(
            $datos["observaciones"] ?? "",
            2000
        );

        if (!$idHabitacion || $idHabitacion <= 0) {
            responder(
                false,
                "Debes seleccionar una habitación.",
                null,
                400
            );
        }

        if ($motivo === "") {
            responder(
                false,
                "El motivo es obligatorio.",
                null,
                400
            );
        }

        $conexion->begin_transaction();

        try {
            /*
             * Bloquear habitación durante
             * la operación.
             */
            $stmt = $conexion->prepare("
                SELECT
                    id_habitacion,
                    numero,
                    estado
                FROM habitaciones
                WHERE id_habitacion = ?
                FOR UPDATE
            ");

            $stmt->bind_param(
                "i",
                $idHabitacion
            );

            $stmt->execute();

            $habitacion = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$habitacion) {
                $conexion->rollback();

                responder(
                    false,
                    "La habitación no existe.",
                    null,
                    404
                );
            }

            /*
             * Solo se permite reportar
             * habitaciones disponibles.
             */
            if ($habitacion["estado"] !== "Disponible") {
                $conexion->rollback();

                responder(
                    false,
                    "La habitación no está disponible para mantenimiento.",
                    null,
                    409
                );
            }

            /*
             * Verificar que no exista
             * otro mantenimiento abierto.
             */
            $stmt = $conexion->prepare("
                SELECT id_mantenimiento
                FROM mantenimientos
                WHERE id_habitacion = ?
                  AND estado IN (
                      'Reportado',
                      'En proceso'
                  )
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $idHabitacion
            );

            $stmt->execute();

            $duplicado = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if ($duplicado) {
                $conexion->rollback();

                responder(
                    false,
                    "La habitación ya tiene un mantenimiento abierto.",
                    null,
                    409
                );
            }

            /*
             * Evitar mantenimiento si
             * existe una estancia activa.
             */
            $stmt = $conexion->prepare("
                SELECT id_estancia
                FROM estancias
                WHERE id_habitacion = ?
                  AND estado = 'Activa'
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $idHabitacion
            );

            $stmt->execute();

            $estancia = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if ($estancia) {
                $conexion->rollback();

                responder(
                    false,
                    "No puedes bloquear una habitación con una estancia activa.",
                    null,
                    409
                );
            }

            $fechaInicio = date("Y-m-d H:i:s");

            $stmt = $conexion->prepare("
                INSERT INTO mantenimientos (
                    id_habitacion,
                    motivo,
                    fecha_inicio,
                    estado,
                    observaciones
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'Reportado',
                    ?
                )
            ");

            $stmt->bind_param(
                "isss",
                $idHabitacion,
                $motivo,
                $fechaInicio,
                $observaciones
            );

            $stmt->execute();

            $idMantenimiento = $conexion->insert_id;

            $stmt->close();

            $stmt = $conexion->prepare("
                UPDATE habitaciones
                SET estado = 'Mantenimiento'
                WHERE id_habitacion = ?
                  AND estado = 'Disponible'
            ");

            $stmt->bind_param(
                "i",
                $idHabitacion
            );

            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    "No se pudo bloquear la habitación."
                );
            }

            $stmt->close();

            $conexion->commit();

            responder(
                true,
                "Mantenimiento reportado correctamente.",
                [
                    "id_mantenimiento" => $idMantenimiento,
                    "habitacion" => $habitacion["numero"],
                    "estado_mantenimiento" => "Reportado",
                    "estado_habitacion" => "Mantenimiento"
                ],
                201
            );

        } catch (Throwable $error) {
            $conexion->rollback();
            throw $error;
        }
    }

    /*
     * ====================================
     * PUT - ACTUALIZAR ESTADO
     * ====================================
     *
     * Esta operación queda restringida
     * al administrador.
     */
    if ($metodo === "PUT") {

        soloAdministrador();

        $datos = obtenerJSON();

        $id = filter_var(
            $datos["id_mantenimiento"] ?? null,
            FILTER_VALIDATE_INT
        );

        $estado = texto(
            $datos["estado"] ?? "",
            30
        );

        $estadosPermitidos = [
            "Reportado",
            "En proceso",
            "Finalizado",
            "Cancelado"
        ];

        if (!$id || $id <= 0) {
            responder(
                false,
                "ID de mantenimiento inválido.",
                null,
                400
            );
        }

        if (!in_array(
            $estado,
            $estadosPermitidos,
            true
        )) {
            responder(
                false,
                "Estado de mantenimiento no válido.",
                null,
                400
            );
        }

        $conexion->begin_transaction();

        try {
            /*
             * Primero bloquear la habitación
             * asociada al mantenimiento.
             */
            $stmt = $conexion->prepare("
                SELECT
                    h.id_habitacion,
                    h.estado AS estado_habitacion
                FROM mantenimientos m
                INNER JOIN habitaciones h
                    ON h.id_habitacion = m.id_habitacion
                WHERE m.id_mantenimiento = ?
                FOR UPDATE
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $habitacion = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$habitacion) {
                $conexion->rollback();

                responder(
                    false,
                    "El mantenimiento no existe.",
                    null,
                    404
                );
            }

            $stmt = $conexion->prepare("
                SELECT *
                FROM mantenimientos
                WHERE id_mantenimiento = ?
                FOR UPDATE
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $mantenimiento = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();

            if (!$mantenimiento) {
                $conexion->rollback();

                responder(
                    false,
                    "El mantenimiento no existe.",
                    null,
                    404
                );
            }

            if (in_array(
                $mantenimiento["estado"],
                ["Finalizado", "Cancelado"],
                true
            )) {
                $conexion->rollback();

                responder(
                    false,
                    "Este mantenimiento ya está cerrado.",
                    null,
                    409
                );
            }

            $observaciones = array_key_exists(
                "observaciones",
                $datos
            )
                ? texto($datos["observaciones"], 2000)
                : (string) (
                    $mantenimiento["observaciones"] ?? ""
                );

            $cerrar = in_array(
                $estado,
                ["Finalizado", "Cancelado"],
                true
            );

            if ($cerrar) {
                /*
                 * No liberar una habitación
                 * que tenga una estancia activa.
                 */
                $stmt = $conexion->prepare("
                    SELECT id_estancia
                    FROM estancias
                    WHERE id_habitacion = ?
                      AND estado = 'Activa'
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "i",
                    $mantenimiento["id_habitacion"]
                );

                $stmt->execute();

                $estancia = $stmt
                    ->get_result()
                    ->fetch_assoc();

                $stmt->close();

                if ($estancia) {
                    $conexion->rollback();

                    responder(
                        false,
                        "La habitación tiene una estancia activa. Revisa su situación antes de cerrar el mantenimiento.",
                        null,
                        409
                    );
                }

                /*
                 * Cerrar mantenimiento.
                 */
                $fechaFin = date("Y-m-d H:i:s");

                $stmt = $conexion->prepare("
                    UPDATE mantenimientos
                    SET
                        estado = ?,
                        fecha_fin = ?,
                        observaciones = ?
                    WHERE id_mantenimiento = ?
                ");

                $stmt->bind_param(
                    "sssi",
                    $estado,
                    $fechaFin,
                    $observaciones,
                    $id
                );

                $stmt->execute();
                $stmt->close();

                /*
                 * Liberar únicamente si la
                 * habitación sigue marcada
                 * como Mantenimiento.
                 */
                $stmt = $conexion->prepare("
                    UPDATE habitaciones
                    SET estado = 'Disponible'
                    WHERE id_habitacion = ?
                      AND estado = 'Mantenimiento'
                      AND NOT EXISTS (
                          SELECT 1
                          FROM mantenimientos
                          WHERE id_habitacion = ?
                            AND id_mantenimiento <> ?
                            AND estado IN (
                                'Reportado',
                                'En proceso'
                            )
                      )
                ");

                $idHabitacion = (int) (
                    $mantenimiento["id_habitacion"]
                );

                $stmt->bind_param(
                    "iii",
                    $idHabitacion,
                    $idHabitacion,
                    $id
                );

                $stmt->execute();
                $stmt->close();

            } else {
                /*
                 * Mantener abierto el reporte.
                 */
                $stmt = $conexion->prepare("
                    UPDATE mantenimientos
                    SET
                        estado = ?,
                        observaciones = ?
                    WHERE id_mantenimiento = ?
                ");

                $stmt->bind_param(
                    "ssi",
                    $estado,
                    $observaciones,
                    $id
                );

                $stmt->execute();
                $stmt->close();
            }

            $conexion->commit();

            $actualizado = consultarMantenimiento(
                $conexion,
                $id
            );

            responder(
                true,
                "Mantenimiento actualizado correctamente.",
                $actualizado
            );

        } catch (Throwable $error) {
            $conexion->rollback();
            throw $error;
        }
    }

} catch (Throwable $error) {

    error_log(
        "Error en mantenimientos.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "No se pudo completar la operación de mantenimiento.",
        null,
        500
    );
}
