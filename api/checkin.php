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


/* =========================================================
   SOLO POST
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    responder(
        false,
        "El check-in debe realizarse mediante POST.",
        null,
        405
    );
}


$datos = obtenerJSON();

$idReservacion = (int) (
    $datos["id_reservacion"] ?? 0
);

$observaciones = trim(
    $datos["observaciones"] ?? ""
);


if ($idReservacion <= 0) {

    responder(
        false,
        "Debes proporcionar el id de la reservación.",
        null,
        400
    );
}


/* =========================================================
   OBTENER RESERVACIÓN
========================================================= */

$sql = "
    SELECT
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
        ON h.id_tipo_habitacion = t.id_tipo_habitacion

    WHERE r.id_reservacion = ?
";

$stmt = $conexion->prepare($sql);

$stmt->bind_param(
    "i",
    $idReservacion
);

$stmt->execute();

$resultado = $stmt->get_result();


if ($resultado->num_rows === 0) {

    responder(
        false,
        "La reservación no existe.",
        null,
        404
    );
}


$reservacion = $resultado->fetch_assoc();


/* =========================================================
   VALIDACIONES
========================================================= */

if (
    !in_array(
        $reservacion["estado"],
        ["Pendiente", "Confirmada"],
        true
    )
) {

    responder(
        false,
        "Esta reservación no está disponible para realizar check-in.",
        null,
        409
    );
}


if ($reservacion["estado_habitacion"] !== "Disponible") {

    responder(
        false,
        "La habitación no se encuentra disponible para realizar el check-in.",
        null,
        409
    );
}


/* =========================================================
   VERIFICAR QUE NO EXISTA ESTANCIA
========================================================= */

$stmt = $conexion->prepare(
    "SELECT id_estancia
     FROM estancias
     WHERE id_reservacion = ?"
);

$stmt->bind_param(
    "i",
    $idReservacion
);

$stmt->execute();


if ($stmt->get_result()->num_rows > 0) {

    responder(
        false,
        "Esta reservación ya tiene una estancia registrada.",
        null,
        409
    );
}


/* =========================================================
   CALCULAR NÚMERO DE NOCHES
========================================================= */

$entrada = new DateTime(
    $reservacion["fecha_entrada"]
);

$salida = new DateTime(
    $reservacion["fecha_salida"]
);

$noches = $entrada->diff($salida)->days;


if ($noches <= 0) {

    responder(
        false,
        "La reservación no tiene un periodo válido.",
        null,
        400
    );
}


/* =========================================================
   CALCULAR IMPORTE BASE
========================================================= */

$tarifa = (float) $reservacion["tarifa_base"];

$importeBase = $tarifa * $noches;


/* =========================================================
   FECHAS
========================================================= */

$fechaCheckin = date("Y-m-d H:i:s");

$checkoutPrevisto =
    $reservacion["fecha_salida"] . " 12:00:00";


/* =========================================================
   TRANSACCIÓN
========================================================= */

$conexion->begin_transaction();

try {

    /* CREAR ESTANCIA */

    $sql = "
        INSERT INTO estancias (
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
            ?, ?, ?, ?, ?, ?, 0, ?, 'Activa', ?
        )
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param(
        "iiissdds",
        $idReservacion,
        $reservacion["id_huesped"],
        $reservacion["id_habitacion"],
        $fechaCheckin,
        $checkoutPrevisto,
        $importeBase,
        $importeBase,
        $observaciones
    );

    $stmt->execute();

    $idEstancia = $conexion->insert_id;


    /* RESERVACIÓN -> EN ESTANCIA */

    $stmt = $conexion->prepare(
        "UPDATE reservaciones
         SET estado = 'En estancia'
         WHERE id_reservacion = ?"
    );

    $stmt->bind_param(
        "i",
        $idReservacion
    );

    $stmt->execute();


    /* HABITACIÓN -> OCUPADA */

    $stmt = $conexion->prepare(
        "UPDATE habitaciones
         SET estado = 'Ocupada'
         WHERE id_habitacion = ?"
    );

    $stmt->bind_param(
        "i",
        $reservacion["id_habitacion"]
    );

    $stmt->execute();


    $conexion->commit();


    responder(
        true,
        "Check-in realizado correctamente.",
        [
            "id_estancia" => $idEstancia,
            "id_reservacion" => $idReservacion,
            "id_habitacion" =>
                $reservacion["id_habitacion"],
            "noches" => $noches,
            "tarifa_noche" => $tarifa,
            "importe_base" => $importeBase,
            "fecha_checkin" => $fechaCheckin,
            "checkout_previsto" => $checkoutPrevisto
        ],
        201
    );

} catch (Throwable $e) {

    $conexion->rollback();

    responder(
        false,
        "No se pudo realizar el check-in: " .
        $e->getMessage(),
        null,
        500
    );
}