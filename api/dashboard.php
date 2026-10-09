<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET");

require_once __DIR__ . "/auth.php";

// Solo usuarios autenticados del hotel.
personalHotel();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function contar(mysqli $conexion, string $sql): int
{
    $resultado = $conexion->query($sql);

    if (!$resultado) {
        throw new RuntimeException(
            "No se pudo ejecutar la consulta."
        );
    }

    $fila = $resultado->fetch_assoc();
    $resultado->free();

    return (int) ($fila["total"] ?? 0);
}

try {
    $database = new Database();
    $conexion = $database->conectar();

    /*
     * HABITACIONES
     */

    $totalHabitaciones = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM habitaciones"
    );

    $habitacionesDisponibles = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM habitaciones
         WHERE estado = 'Disponible'"
    );

    $habitacionesOcupadas = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM habitaciones
         WHERE estado = 'Ocupada'"
    );

    $habitacionesMantenimiento = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM habitaciones
         WHERE estado = 'Mantenimiento'"
    );

    /*
     * RESERVACIONES
     */

    $totalReservaciones = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM reservaciones"
    );

    $reservacionesPendientes = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM reservaciones
         WHERE estado = 'Pendiente'"
    );

    $reservacionesConfirmadas = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM reservaciones
         WHERE estado = 'Confirmada'"
    );

    /*
     * HUÉSPEDES
     */

    $totalHuespedes = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM huespedes
         WHERE estado = 'Activo'"
    );

    /*
     * ESTANCIAS
     */

    $estanciasActivas = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM estancias
         WHERE estado = 'Activa'"
    );

    $estanciasFinalizadas = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM estancias
         WHERE estado = 'Finalizada'"
    );

    /*
     * MANTENIMIENTOS
     */

    $mantenimientosPendientes = contar(
        $conexion,
        "SELECT COUNT(*) AS total
         FROM mantenimientos
         WHERE estado IN (
             'Reportado',
             'En proceso'
         )"
    );

    /*
     * RESPUESTA PARA app.js
     */

    $datos = [
        "habitaciones" => [
            "total" => $totalHabitaciones,
            "disponibles" => $habitacionesDisponibles,
            "ocupadas" => $habitacionesOcupadas,
            "mantenimiento" => $habitacionesMantenimiento
        ],

        "reservaciones" => [
            "total" => $totalReservaciones,
            "pendientes" => $reservacionesPendientes,
            "confirmadas" => $reservacionesConfirmadas
        ],

        "huespedes" => [
            "total" => $totalHuespedes
        ],

        "estancias" => [
            "activas" => $estanciasActivas,
            "finalizadas" => $estanciasFinalizadas
        ],

        "mantenimientos" => [
            "pendientes" => $mantenimientosPendientes
        ]
    ];

    echo json_encode([
        "success" => true,
        "message" => "Dashboard cargado correctamente.",
        "data" => $datos
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $error) {

    error_log(
        "Error en dashboard.php: " .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No se pudieron cargar las estadísticas."
    ], JSON_UNESCAPED_UNICODE);
}
