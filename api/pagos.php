<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT, PATCH");

require_once __DIR__ . "/auth.php";

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

    $id = filter_var($valor, FILTER_VALIDATE_INT);

    return $id !== false && $id > 0 ? $id : 0;
}

function texto($valor): string
{
    return is_string($valor) ? trim($valor) : "";
}

function montoValido($valor): float
{
    if (
        !is_int($valor) &&
        !is_float($valor) &&
        !(is_string($valor) && is_numeric(trim($valor)))
    ) {
        responder(
            false,
            "El monto debe ser numérico.",
            null,
            400
        );
    }

    $monto = (float) $valor;

    if (
        !is_finite($monto) ||
        $monto <= 0 ||
        $monto > 99999999.99 ||
        round($monto, 2) <= 0
    ) {
        responder(
            false,
            "El monto debe ser mayor a cero y estar dentro del límite permitido.",
            null,
            400
        );
    }

    return round($monto, 2);
}

function validarMetodo(string $metodo): void
{
    if (!in_array(
        $metodo,
        ["Efectivo", "Tarjeta", "Transferencia"],
        true
    )) {
        responder(
            false,
            "Método de pago no válido.",
            null,
            400
        );
    }
}

function validarEstado(string $estado): void
{
    if (!in_array(
        $estado,
        ["Pendiente", "Pagado", "Cancelado"],
        true
    )) {
        responder(
            false,
            "Estado de pago no válido.",
            null,
            400
        );
    }
}

function buscarPago(
    mysqli $conexion,
    int $idPago
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            id_pago,
            id_estancia,
            monto,
            metodo_pago,
            fecha_pago,
            referencia,
            estado,
            observaciones
         FROM pagos
         WHERE id_pago = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $idPago);
    $stmt->execute();

    $pago = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $pago ?: null;
}

/*
 * Bloquear la estancia durante operaciones
 * que puedan cambiar el saldo.
 */
function bloquearEstancia(
    mysqli $conexion,
    int $idEstancia
): array {
    $stmt = $conexion->prepare(
        "SELECT
            id_estancia,
            total,
            estado
         FROM estancias
         WHERE id_estancia = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param("i", $idEstancia);
    $stmt->execute();

    $estancia = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$estancia) {
        responder(
            false,
            "La estancia no existe.",
            null,
            404
        );
    }

    return $estancia;
}

function obtenerResumen(
    mysqli $conexion,
    int $idEstancia
): array {
    $stmt = $conexion->prepare(
        "SELECT
            e.id_estancia,
            e.total,
            COALESCE(SUM(
                CASE
                    WHEN p.estado = 'Pagado'
                    THEN p.monto
                    ELSE 0
                END
            ), 0) AS total_pagado
         FROM estancias e
         LEFT JOIN pagos p
            ON p.id_estancia = e.id_estancia
         WHERE e.id_estancia = ?
         GROUP BY e.id_estancia, e.total"
    );

    $stmt->bind_param("i", $idEstancia);
    $stmt->execute();

    $resumen = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resumen) {
        responder(
            false,
            "La estancia no existe.",
            null,
            404
        );
    }

    $total = round((float) $resumen["total"], 2);
    $pagado = round(
        (float) $resumen["total_pagado"],
        2
    );

    return [
        "id_estancia" => $idEstancia,
        "total_estancia" => $total,
        "total_pagado" => $pagado,
        "saldo_pendiente" => round(
            max(0, $total - $pagado),
            2
        )
    ];
}

try {
    $conexion = (new Database())->conectar();
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * Consultar pagos.
     */
    if ($metodo === "GET") {

        if (isset($_GET["id"])) {
            $idPago = idValido($_GET["id"]);

            if ($idPago === 0) {
                responder(
                    false,
                    "ID de pago no válido.",
                    null,
                    400
                );
            }

            $pago = buscarPago($conexion, $idPago);

            if (!$pago) {
                responder(
                    false,
                    "Pago no encontrado.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Pago encontrado.",
                $pago
            );
        }

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

            $stmt = $conexion->prepare(
                "SELECT
                    id_pago,
                    id_estancia,
                    monto,
                    metodo_pago,
                    fecha_pago,
                    referencia,
                    estado,
                    observaciones
                 FROM pagos
                 WHERE id_estancia = ?
                 ORDER BY fecha_pago DESC, id_pago DESC"
            );

            $stmt->bind_param("i", $idEstancia);
            $stmt->execute();

            $pagos = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            $resumen = obtenerResumen(
                $conexion,
                $idEstancia
            );

            responder(
                true,
                "Pagos obtenidos correctamente.",
                [
                    "pagos" => $pagos,
                    "resumen" => $resumen
                ]
            );
        }

        $resultado = $conexion->query(
            "SELECT
                id_pago,
                id_estancia,
                monto,
                metodo_pago,
                fecha_pago,
                referencia,
                estado,
                observaciones
             FROM pagos
             ORDER BY fecha_pago DESC, id_pago DESC"
        );

        responder(
            true,
            "Pagos obtenidos correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST
     * Registrar un pago.
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $idEstancia = idValido(
            $datos["id_estancia"] ?? null
        );

        if ($idEstancia === 0) {
            responder(
                false,
                "Debes proporcionar una estancia válida.",
                null,
                400
            );
        }

        if (!array_key_exists("monto", $datos)) {
            responder(
                false,
                "El monto es obligatorio.",
                null,
                400
            );
        }

        $monto = montoValido($datos["monto"]);
        $metodoPago = texto(
            $datos["metodo_pago"] ?? ""
        );
        $referencia = texto(
            $datos["referencia"] ?? ""
        );
        $observaciones = texto(
            $datos["observaciones"] ?? ""
        );

        $estado = texto(
            $datos["estado"] ?? "Pendiente"
        );

        validarMetodo($metodoPago);
        validarEstado($estado);

        $conexion->begin_transaction();

        bloquearEstancia($conexion, $idEstancia);

        $resumen = obtenerResumen(
            $conexion,
            $idEstancia
        );

        if (
            $estado === "Pagado" &&
            $monto > $resumen["saldo_pendiente"]
        ) {
            $conexion->rollback();

            responder(
                false,
                "El monto supera el saldo pendiente de la estancia.",
                [
                    "saldo_pendiente" =>
                        $resumen["saldo_pendiente"]
                ],
                409
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO pagos (
                id_estancia,
                monto,
                metodo_pago,
                referencia,
                estado,
                observaciones
             )
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "idssss",
            $idEstancia,
            $monto,
            $metodoPago,
            $referencia,
            $estado,
            $observaciones
        );

        $stmt->execute();

        $idPago = $conexion->insert_id;
        $stmt->close();

        $resumenActualizado = obtenerResumen(
            $conexion,
            $idEstancia
        );

        $conexion->commit();

        responder(
            true,
            "Pago registrado correctamente.",
            [
                "id_pago" => $idPago,
                "resumen" => $resumenActualizado
            ],
            201
        );
    }

    /*
     * PUT
     * Editar datos de un pago pendiente.
     */
    if ($metodo === "PUT") {
        soloAdministrador();

        $datos = obtenerJSON();

        $idPago = idValido(
            $datos["id_pago"] ?? null
        );

        if ($idPago === 0) {
            responder(
                false,
                "ID de pago no válido.",
                null,
                400
            );
        }

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "SELECT *
             FROM pagos
             WHERE id_pago = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param("i", $idPago);
        $stmt->execute();

        $actual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$actual) {
            $conexion->rollback();

            responder(
                false,
                "El pago no existe.",
                null,
                404
            );
        }

        if ($actual["estado"] !== "Pendiente") {
            $conexion->rollback();

            responder(
                false,
                "Solo se pueden editar pagos pendientes.",
                null,
                409
            );
        }

        $monto = array_key_exists("monto", $datos)
            ? montoValido($datos["monto"])
            : (float) $actual["monto"];

        $metodoPago = texto(
            $datos["metodo_pago"] ??
            $actual["metodo_pago"]
        );

        $referencia = texto(
            $datos["referencia"] ??
            $actual["referencia"] ?? ""
        );

        $observaciones = texto(
            $datos["observaciones"] ??
            $actual["observaciones"] ?? ""
        );

        validarMetodo($metodoPago);

        $stmt = $conexion->prepare(
            "UPDATE pagos
             SET monto = ?,
                 metodo_pago = ?,
                 referencia = ?,
                 observaciones = ?
             WHERE id_pago = ?"
        );

        $stmt->bind_param(
            "dsssi",
            $monto,
            $metodoPago,
            $referencia,
            $observaciones,
            $idPago
        );

        $stmt->execute();
        $stmt->close();

        $conexion->commit();

        responder(
            true,
            "Pago pendiente actualizado correctamente."
        );
    }

    /*
     * PATCH
     * Cambiar el estado de un pago.
     */
    if ($metodo === "PATCH") {
        soloAdministrador();

        $datos = obtenerJSON();

        $idPago = idValido(
            $datos["id_pago"] ?? null
        );

        $nuevoEstado = texto(
            $datos["estado"] ?? ""
        );

        if ($idPago === 0) {
            responder(
                false,
                "ID de pago no válido.",
                null,
                400
            );
        }

        validarEstado($nuevoEstado);

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "SELECT *
             FROM pagos
             WHERE id_pago = ?
             LIMIT 1"
        );

        $stmt->bind_param("i", $idPago);
        $stmt->execute();

        $actual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$actual) {
            $conexion->rollback();

            responder(
                false,
                "El pago no existe.",
                null,
                404
            );
        }

        $idEstancia = (int) $actual["id_estancia"];

        bloquearEstancia(
            $conexion,
            $idEstancia
        );

        $stmt = $conexion->prepare(
            "SELECT
                id_pago,
                monto,
                estado
             FROM pagos
             WHERE id_pago = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param("i", $idPago);
        $stmt->execute();

        $actual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($actual["estado"] === $nuevoEstado) {
            $conexion->commit();

            responder(
                true,
                "El pago ya tiene ese estado."
            );
        }

        if ($actual["estado"] !== "Pendiente") {
            $conexion->rollback();

            responder(
                false,
                "Solo se puede cambiar el estado de pagos pendientes.",
                null,
                409
            );
        }

        if ($nuevoEstado === "Pagado") {
            $resumen = obtenerResumen(
                $conexion,
                $idEstancia
            );

            if (
                (float) $actual["monto"] >
                $resumen["saldo_pendiente"]
            ) {
                $conexion->rollback();

                responder(
                    false,
                    "El pago supera el saldo pendiente.",
                    [
                        "saldo_pendiente" =>
                            $resumen["saldo_pendiente"]
                    ],
                    409
                );
            }
        }

        $stmt = $conexion->prepare(
            "UPDATE pagos
             SET estado = ?
             WHERE id_pago = ?"
        );

        $stmt->bind_param(
            "si",
            $nuevoEstado,
            $idPago
        );

        $stmt->execute();
        $stmt->close();

        $resumenActualizado = obtenerResumen(
            $conexion,
            $idEstancia
        );

        $conexion->commit();

        responder(
            true,
            "Estado del pago actualizado correctamente.",
            $resumenActualizado
        );
    }

    responder(
        false,
        "Método HTTP no permitido.",
        null,
        405
    );

} catch (Throwable $error) {
    if (isset($conexion)) {
        try {
            $conexion->rollback();
        } catch (Throwable $ignorado) {
        }
    }

    error_log(
        "Error en api/pagos.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error al procesar el pago.",
        null,
        500
    );
}
