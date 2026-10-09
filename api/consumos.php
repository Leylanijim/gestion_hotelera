<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, DELETE");

require_once __DIR__ . "/auth.php";

// Acceso para Administrador y Recepcionista.
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

function texto($valor): string
{
    return is_string($valor) ? trim($valor) : "";
}

function consultarTotales(
    mysqli $conexion,
    int $idEstancia
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            importe_base,
            total_servicios,
            total
         FROM estancias
         WHERE id_estancia = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $idEstancia);
    $stmt->execute();

    $totales = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $totales ?: null;
}

/*
 * Recalcular los importes utilizando
 * el procedimiento almacenado existente.
 */
function recalcularTotal(
    mysqli $conexion,
    int $idEstancia
): void {
    $stmt = $conexion->prepare(
        "CALL sp_calcular_total_estancia(?)"
    );

    $stmt->bind_param("i", $idEstancia);
    $stmt->execute();

    do {
        $resultado = $stmt->get_result();

        if ($resultado instanceof mysqli_result) {
            $resultado->free();
        }
    } while ($stmt->next_result());

    $stmt->close();
}

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Consultar consumos.
     */
    if ($metodo === "GET") {

        if (isset($_GET["id_estancia"])) {
            $idEstancia = idValido(
                $_GET["id_estancia"]
            );

            if ($idEstancia === 0) {
                responder(
                    false,
                    "ID de estancia no válido.",
                    null,
                    400
                );
            }

            $totales = consultarTotales(
                $conexion,
                $idEstancia
            );

            if (!$totales) {
                responder(
                    false,
                    "La estancia no existe.",
                    null,
                    404
                );
            }

            $stmt = $conexion->prepare(
                "SELECT
                    c.id_consumo,
                    c.id_estancia,
                    c.id_servicio,
                    s.nombre AS servicio,
                    c.cantidad,
                    c.precio_unitario,
                    c.importe,
                    c.fecha_consumo,
                    c.observaciones
                 FROM consumos c
                 INNER JOIN servicios s
                    ON c.id_servicio = s.id_servicio
                 WHERE c.id_estancia = ?
                 ORDER BY
                    c.fecha_consumo DESC,
                    c.id_consumo DESC"
            );

            $stmt->bind_param("i", $idEstancia);
            $stmt->execute();

            $consumos = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            responder(
                true,
                "Consumos obtenidos correctamente.",
                [
                    "consumos" => $consumos,
                    "totales" => $totales
                ]
            );
        }

        $resultado = $conexion->query(
            "SELECT
                c.id_consumo,
                c.id_estancia,
                c.id_servicio,
                s.nombre AS servicio,
                c.cantidad,
                c.precio_unitario,
                c.importe,
                c.fecha_consumo,
                c.observaciones
             FROM consumos c
             INNER JOIN servicios s
                ON c.id_servicio = s.id_servicio
             ORDER BY
                c.fecha_consumo DESC,
                c.id_consumo DESC"
        );

        responder(
            true,
            "Consumos obtenidos correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Registrar un consumo.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $idEstancia = idValido(
            $datos["id_estancia"] ?? null
        );

        $idServicio = idValido(
            $datos["id_servicio"] ?? null
        );

        $cantidad = idValido(
            $datos["cantidad"] ?? 1
        );

        $observaciones = texto(
            $datos["observaciones"] ?? ""
        );

        if (
            $idEstancia === 0 ||
            $idServicio === 0
        ) {
            responder(
                false,
                "La estancia y el servicio son obligatorios.",
                null,
                400
            );
        }

        if ($cantidad === 0) {
            responder(
                false,
                "La cantidad debe ser un entero mayor que cero.",
                null,
                400
            );
        }

        $conexion->begin_transaction();

        /*
         * Bloquear la estancia para evitar
         * registros durante el check-out.
         */
        $stmt = $conexion->prepare(
            "SELECT id_estancia, estado
             FROM estancias
             WHERE id_estancia = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param("i", $idEstancia);
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
                "Solo puedes registrar consumos en una estancia activa.",
                null,
                409
            );
        }

        /*
         * Obtener el precio directamente
         * de la base de datos.
         */
        $stmt = $conexion->prepare(
            "SELECT
                id_servicio,
                nombre,
                precio,
                estado
             FROM servicios
             WHERE id_servicio = ?
             LIMIT 1"
        );

        $stmt->bind_param("i", $idServicio);
        $stmt->execute();

        $servicio = $stmt->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$servicio) {
            $conexion->rollback();

            responder(
                false,
                "El servicio no existe.",
                null,
                404
            );
        }

        if ($servicio["estado"] !== "Activo") {
            $conexion->rollback();

            responder(
                false,
                "El servicio seleccionado está inactivo.",
                null,
                409
            );
        }

        $precioUnitario = (float) $servicio["precio"];

        $importe = round(
            $precioUnitario * $cantidad,
            2
        );

        if (
            !is_finite($importe) ||
            $importe > 99999999.99
        ) {
            $conexion->rollback();

            responder(
                false,
                "El importe supera el límite permitido.",
                null,
                400
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO consumos (
                id_estancia,
                id_servicio,
                cantidad,
                precio_unitario,
                importe,
                observaciones
             )
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "iiidds",
            $idEstancia,
            $idServicio,
            $cantidad,
            $precioUnitario,
            $importe,
            $observaciones
        );

        $stmt->execute();

        $idConsumo = $conexion->insert_id;
        $stmt->close();

        /*
         * Recalcular el total de la estancia.
         */
        recalcularTotal(
            $conexion,
            $idEstancia
        );

        $totales = consultarTotales(
            $conexion,
            $idEstancia
        );

        $conexion->commit();

        responder(
            true,
            "Consumo registrado correctamente.",
            [
                "id_consumo" => $idConsumo,
                "id_estancia" => $idEstancia,
                "servicio" => $servicio["nombre"],
                "cantidad" => $cantidad,
                "precio_unitario" => $precioUnitario,
                "importe" => $importe,
                "totales" => $totales
            ],
            201
        );
    }

    /*
     * DELETE
     * Protección del historial.
     *
     * La tabla consumos no tiene campo
     * de estado ni de cancelación.
     * No se eliminan registros históricos.
     */
    if ($metodo === "DELETE") {
        responder(
            false,
            "La eliminación de consumos está deshabilitada para proteger el historial. Se requiere un mecanismo de cancelación con auditoría.",
            null,
            405
        );
    }

    responder(
        false,
        "Método HTTP no permitido.",
        null,
        405
    );

} catch (mysqli_sql_exception $error) {
    if (isset($conexion)) {
        try {
            $conexion->rollback();
        } catch (Throwable $ignorado) {
        }
    }

    error_log(
        "Error SQL en api/consumos.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "No se pudo completar la operación con el consumo.",
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
        "Error en api/consumos.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar la solicitud.",
        null,
        500
    );
}
