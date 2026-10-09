<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/config/database.php";

$database = new Database();
$conexion = $database->conectar();


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
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    exit;
}


function obtenerJSON(): array
{
    $contenido = file_get_contents("php://input");

    if (empty($contenido)) {
        return [];
    }

    $datos = json_decode($contenido, true);

    return is_array($datos) ? $datos : [];
}


function recalcularTotal(
    mysqli $conexion,
    int $idEstancia
): void {

    $stmt = $conexion->prepare(
        "CALL sp_calcular_total_estancia(?)"
    );

    $stmt->bind_param(
        "i",
        $idEstancia
    );

    $stmt->execute();

    do {

        if ($resultado = $stmt->get_result()) {
            $resultado->free();
        }

    } while ($stmt->next_result());

    $stmt->close();
}


/* =========================================================
   SOLO POST
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    responder(
        false,
        "El checkout debe realizarse mediante POST.",
        null,
        405
    );
}


$datos = obtenerJSON();

$idEstancia = (int) (
    $datos["id_estancia"] ?? 0
);


if ($idEstancia <= 0) {

    responder(
        false,
        "Debes proporcionar el id de la estancia.",
        null,
        400
    );
}


/* =========================================================
   OBTENER ESTANCIA
========================================================= */

$sql = "
    SELECT
        e.id_estancia,
        e.id_reservacion,
        e.id_huesped,
        e.id_habitacion,
        e.fecha_checkin,
        e.check_out_previsto,
        e.fecha_checkout,
        e.importe_base,
        e.total_servicios,
        e.total,
        e.estado,

        hab.numero AS habitacion,

        CONCAT(
            h.nombre,
            ' ',
            h.apellido_paterno,
            ' ',
            COALESCE(h.apellido_materno, '')
        ) AS huesped

    FROM estancias e

    INNER JOIN habitaciones hab
        ON e.id_habitacion = hab.id_habitacion

    INNER JOIN huespedes h
        ON e.id_huesped = h.id_huesped

    WHERE e.id_estancia = ?
";

$stmt = $conexion->prepare($sql);

$stmt->bind_param(
    "i",
    $idEstancia
);

$stmt->execute();

$resultado = $stmt->get_result();


if ($resultado->num_rows === 0) {

    responder(
        false,
        "La estancia no existe.",
        null,
        404
    );
}


$estancia = $resultado->fetch_assoc();


/* =========================================================
   VALIDAR ESTADO
========================================================= */

if ($estancia["estado"] !== "Activa") {

    responder(
        false,
        "Esta estancia ya fue finalizada.",
        null,
        409
    );
}


/* =========================================================
   TRANSACCIÓN
========================================================= */

$conexion->begin_transaction();

try {

    /*
     * 1. RECALCULAR TOTAL
     */

    recalcularTotal(
        $conexion,
        $idEstancia
    );


    /*
     * 2. OBTENER TOTAL FINAL
     */

    $stmt = $conexion->prepare(
        "SELECT
            importe_base,
            total_servicios,
            total
         FROM estancias
         WHERE id_estancia = ?"
    );

    $stmt->bind_param(
        "i",
        $idEstancia
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $totales = $resultado->fetch_assoc();


    /*
     * 3. REGISTRAR FECHA DE CHECKOUT
     *    Y FINALIZAR ESTANCIA
     */

    $fechaCheckout = date("Y-m-d H:i:s");

    $stmt = $conexion->prepare(
        "UPDATE estancias
         SET
            fecha_checkout = ?,
            estado = 'Finalizada'
         WHERE id_estancia = ?"
    );

    $stmt->bind_param(
        "si",
        $fechaCheckout,
        $idEstancia
    );

    $stmt->execute();


    /*
     * 4. FINALIZAR RESERVACIÓN
     */

    $stmt = $conexion->prepare(
        "UPDATE reservaciones
         SET estado = 'Finalizada'
         WHERE id_reservacion = ?"
    );

    $stmt->bind_param(
        "i",
        $estancia["id_reservacion"]
    );

    $stmt->execute();


    /*
     * 5. LIBERAR HABITACIÓN
     */

    $stmt = $conexion->prepare(
        "UPDATE habitaciones
         SET estado = 'Disponible'
         WHERE id_habitacion = ?"
    );

    $stmt->bind_param(
        "i",
        $estancia["id_habitacion"]
    );

    $stmt->execute();


    /*
     * 6. CONFIRMAR TODO
     */

    $conexion->commit();


    responder(
        true,
        "Checkout realizado correctamente.",
        [
            "id_estancia" =>
                $idEstancia,

            "id_reservacion" =>
                $estancia["id_reservacion"],

            "huesped" =>
                $estancia["huesped"],

            "habitacion" =>
                $estancia["habitacion"],

            "fecha_checkin" =>
                $estancia["fecha_checkin"],

            "fecha_checkout" =>
                $fechaCheckout,

            "importe_base" =>
                $totales["importe_base"],

            "total_servicios" =>
                $totales["total_servicios"],

            "total" =>
                $totales["total"],

            "estado_estancia" =>
                "Finalizada",

            "estado_reservacion" =>
                "Finalizada",

            "estado_habitacion" =>
                "Disponible"
        ]
    );


} catch (Throwable $e) {

    $conexion->rollback();

    responder(
        false,
        "No se pudo realizar el checkout: " .
        $e->getMessage(),
        null,
        500
    );
}