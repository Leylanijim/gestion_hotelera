<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("Allow: GET, POST, PUT, DELETE");

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/auth.php";

// Ambos roles pueden consultar habitaciones.
personalHotel();

/*
 * RESPUESTAS JSON
 */
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

/*
 * OBTENER DATOS JSON
 */
function obtenerJSON(): array
{
    $contenido = file_get_contents("php://input");
    $datos = json_decode($contenido ?: "", true);

    return is_array($datos) ? $datos : [];
}

/*
 * VALIDAR ID
 */
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

/*
 * CONSULTAR HABITACIÓN POR ID
 */
function buscarHabitacion(
    mysqli $conexion,
    int $id
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            h.id_habitacion,
            h.numero,
            h.id_tipo_habitacion,
            t.nombre AS tipo_habitacion,
            t.capacidad,
            t.tarifa_base,
            h.piso,
            h.estado,
            h.condiciones_uso,
            h.observaciones
         FROM habitaciones h
         INNER JOIN tipos_habitacion t
             ON h.id_tipo_habitacion = t.id_tipo_habitacion
         WHERE h.id_habitacion = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $habitacion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $habitacion ?: null;
}

/*
 * VERIFICAR NÚMERO DUPLICADO
 */
function numeroDuplicado(
    mysqli $conexion,
    string $numero,
    int $excluirId = 0
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_habitacion
         FROM habitaciones
         WHERE numero = ?
           AND id_habitacion <> ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "si",
        $numero,
        $excluirId
    );

    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * VALIDAR TIPO DE HABITACIÓN
 */
function tipoExiste(
    mysqli $conexion,
    int $idTipo
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_tipo_habitacion
         FROM tipos_habitacion
         WHERE id_tipo_habitacion = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $idTipo);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * VERIFICAR ESTANCIA ACTIVA
 */
function tieneEstanciaActiva(
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
 * VERIFICAR RESERVACIONES VIGENTES
 */
function tieneReservacionesVigentes(
    mysqli $conexion,
    int $idHabitacion
): bool {
    $stmt = $conexion->prepare(
        "SELECT id_reservacion
         FROM reservaciones
         WHERE id_habitacion = ?
           AND estado IN (
               'Pendiente',
               'Confirmada',
               'En estancia'
           )
           AND fecha_salida >= CURDATE()
         LIMIT 1"
    );

    $stmt->bind_param("i", $idHabitacion);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

/*
 * VALIDAR ESTADO DE HABITACIÓN
 */
function validarEstado(string $estado): void
{
    $estadosPermitidos = [
        "Disponible",
        "Ocupada",
        "Mantenimiento",
        "Fuera de servicio"
    ];

    if (!in_array(
        $estado,
        $estadosPermitidos,
        true
    )) {
        responder(
            false,
            "Estado de habitación no válido.",
            null,
            400
        );
    }
}

/*
 * EJECUTAR API
 */
try {
    $database = new Database();
    $conexion = $database->conectar();

    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /*
     * GET
     * CONSULTAR HABITACIONES
     */
    if ($metodo === "GET") {

        // Consultar una habitación específica.
        if (isset($_GET["id"])) {
            $id = enteroPositivo($_GET["id"]);

            if ($id === 0) {
                responder(
                    false,
                    "ID de habitación no válido.",
                    null,
                    400
                );
            }

            $habitacion = buscarHabitacion(
                $conexion,
                $id
            );

            if (!$habitacion) {
                responder(
                    false,
                    "Habitación no encontrada.",
                    null,
                    404
                );
            }

            responder(
                true,
                "Habitación encontrada.",
                $habitacion
            );
        }

        // Consultar habitaciones disponibles.
        if (
            isset($_GET["disponibles"]) &&
            $_GET["disponibles"] === "1"
        ) {
            $resultado = $conexion->query(
                "SELECT *
                 FROM vw_habitaciones_disponibles
                 ORDER BY numero ASC"
            );

            responder(
                true,
                "Habitaciones disponibles obtenidas correctamente.",
                $resultado->fetch_all(MYSQLI_ASSOC)
            );
        }

        // Consultar todas las habitaciones.
        $resultado = $conexion->query(
            "SELECT *
             FROM vw_habitaciones
             ORDER BY numero ASC"
        );

        responder(
            true,
            "Habitaciones obtenidas correctamente.",
            $resultado->fetch_all(MYSQLI_ASSOC)
        );
    }

    /*
     * POST, PUT Y DELETE
     * SOLO ADMINISTRADOR
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
     * REGISTRAR HABITACIÓN
     */
    if ($metodo === "POST") {
        $datos = obtenerJSON();

        $numero = trim(
            (string) ($datos["numero"] ?? "")
        );

        $idTipo = enteroPositivo(
            $datos["id_tipo_habitacion"] ?? null
        );

        $piso = $datos["piso"] ?? null;

        $estado = trim(
            (string) (
                $datos["estado"] ?? "Disponible"
            )
        );

        $condiciones = trim(
            (string) (
                $datos["condiciones_uso"] ?? ""
            )
        );

        $observaciones = trim(
            (string) (
                $datos["observaciones"] ?? ""
            )
        );

        if ($numero === "") {
            responder(
                false,
                "El número de habitación es obligatorio.",
                null,
                400
            );
        }

        if (strlen($numero) > 10) {
            responder(
                false,
                "El número no puede superar 10 caracteres.",
                null,
                400
            );
        }

        if ($idTipo === 0) {
            responder(
                false,
                "Debes seleccionar un tipo de habitación.",
                null,
                400
            );
        }

        validarEstado($estado);

        if (!tipoExiste($conexion, $idTipo)) {
            responder(
                false,
                "El tipo de habitación no existe.",
                null,
                404
            );
        }

        if (numeroDuplicado($conexion, $numero)) {
            responder(
                false,
                "Ya existe una habitación con ese número.",
                null,
                409
            );
        }

        if (
            $piso !== null &&
            filter_var(
                $piso,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            responder(
                false,
                "El piso debe ser un número entero.",
                null,
                400
            );
        }

        $piso = $piso === null
            ? null
            : (int) $piso;

        $stmt = $conexion->prepare(
            "INSERT INTO habitaciones (
                numero,
                id_tipo_habitacion,
                piso,
                estado,
                condiciones_uso,
                observaciones
             )
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "siisss",
            $numero,
            $idTipo,
            $piso,
            $estado,
            $condiciones,
            $observaciones
        );

        $stmt->execute();

        $idNuevo = $conexion->insert_id;
        $stmt->close();

        responder(
            true,
            "Habitación registrada correctamente.",
            ["id_habitacion" => $idNuevo],
            201
        );
    }

    /*
     * PUT
     * ACTUALIZAR HABITACIÓN
     */
    if ($metodo === "PUT") {
        $datos = obtenerJSON();

        $id = enteroPositivo(
            $datos["id_habitacion"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID de la habitación.",
                null,
                400
            );
        }

        $actual = buscarHabitacion(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "La habitación no existe.",
                null,
                404
            );
        }

        // Conservar los valores no enviados.
        $numero = trim(
            (string) (
                $datos["numero"] ??
                $actual["numero"]
            )
        );

        $idTipo = enteroPositivo(
            $datos["id_tipo_habitacion"] ??
            $actual["id_tipo_habitacion"]
        );

        $piso = $datos["piso"] ??
            $actual["piso"];

        $estado = trim(
            (string) (
                $datos["estado"] ??
                $actual["estado"]
            )
        );

        $condiciones = trim(
            (string) (
                $datos["condiciones_uso"] ??
                $actual["condiciones_uso"] ?? ""
            )
        );

        $observaciones = trim(
            (string) (
                $datos["observaciones"] ??
                $actual["observaciones"] ?? ""
            )
        );

        if ($numero === "" || strlen($numero) > 10) {
            responder(
                false,
                "El número de habitación debe tener entre 1 y 10 caracteres.",
                null,
                400
            );
        }

        if ($idTipo === 0) {
            responder(
                false,
                "Tipo de habitación no válido.",
                null,
                400
            );
        }

        validarEstado($estado);

        if (!tipoExiste($conexion, $idTipo)) {
            responder(
                false,
                "El tipo de habitación no existe.",
                null,
                404
            );
        }

        if (numeroDuplicado(
            $conexion,
            $numero,
            $id
        )) {
            responder(
                false,
                "Ya existe otra habitación con ese número.",
                null,
                409
            );
        }

        if (
            $piso !== null &&
            filter_var(
                $piso,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            responder(
                false,
                "El piso debe ser un número entero.",
                null,
                400
            );
        }

        $piso = $piso === null
            ? null
            : (int) $piso;

        /*
         * PROTECCIÓN DE ESTANCIAS ACTIVAS
         *
         * No cambiar datos operativos mientras
         * la habitación tenga una estancia activa.
         */
        $enEstancia = tieneEstanciaActiva(
            $conexion,
            $id
        );

        if (
            $enEstancia &&
            (
                $estado !== $actual["estado"] ||
                $idTipo !== (int) $actual["id_tipo_habitacion"] ||
                $numero !== $actual["numero"]
            )
        ) {
            responder(
                false,
                "No puedes cambiar el número, tipo o estado de una habitación con estancia activa.",
                null,
                409
            );
        }

        /*
         * PROTECCIÓN DE RESERVACIONES
         *
         * No permitir cambios de número o tipo
         * si hay reservaciones vigentes.
         */
        $tieneReservaciones = tieneReservacionesVigentes(
            $conexion,
            $id
        );

        if (
            $tieneReservaciones &&
            (
                $numero !== $actual["numero"] ||
                $idTipo !== (int) $actual["id_tipo_habitacion"]
            )
        ) {
            responder(
                false,
                "No puedes cambiar el número o tipo de una habitación con reservaciones vigentes.",
                null,
                409
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE habitaciones
             SET numero = ?,
                 id_tipo_habitacion = ?,
                 piso = ?,
                 estado = ?,
                 condiciones_uso = ?,
                 observaciones = ?
             WHERE id_habitacion = ?"
        );

        $stmt->bind_param(
            "siisssi",
            $numero,
            $idTipo,
            $piso,
            $estado,
            $condiciones,
            $observaciones,
            $id
        );

        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Habitación actualizada correctamente."
        );
    }

    /*
     * DELETE
     * ELIMINAR HABITACIÓN
     *
     * Solo se permite cuando no existe
     * historial relacionado.
     */
    if ($metodo === "DELETE") {
        $datos = obtenerJSON();

        $id = enteroPositivo(
            $datos["id_habitacion"] ??
            $_GET["id"] ?? null
        );

        if ($id === 0) {
            responder(
                false,
                "Debes proporcionar el ID de la habitación.",
                null,
                400
            );
        }

        $actual = buscarHabitacion(
            $conexion,
            $id
        );

        if (!$actual) {
            responder(
                false,
                "La habitación no existe.",
                null,
                404
            );
        }

        /*
         * No eliminar si hay reservaciones,
         * estancias o mantenimientos.
         */
        $relaciones = [
            "reservaciones" => "id_habitacion",
            "estancias" => "id_habitacion",
            "mantenimientos" => "id_habitacion"
        ];

        foreach ($relaciones as $tabla => $campo) {
            $stmt = $conexion->prepare(
                "SELECT 1
                 FROM $tabla
                 WHERE $campo = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($existe) {
                responder(
                    false,
                    "No puedes eliminar esta habitación porque tiene historial relacionado.",
                    null,
                    409
                );
            }
        }

        $stmt = $conexion->prepare(
            "DELETE FROM habitaciones
             WHERE id_habitacion = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        responder(
            true,
            "Habitación eliminada correctamente."
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
        "Error SQL en habitaciones.php: " .
        $error->getMessage()
    );

    $codigo = (int) $error->getCode();

    if ($codigo === 1062) {
        responder(
            false,
            "Ya existe una habitación con ese número.",
            null,
            409
        );
    }

    if ($codigo === 1451 || $codigo === 1452) {
        responder(
            false,
            "La operación no se puede completar por las relaciones de la habitación.",
            null,
            409
        );
    }

    responder(
        false,
        "Error al procesar la habitación.",
        null,
        500
    );

} catch (Throwable $error) {
    error_log(
        "Error en habitaciones.php: " .
        $error->getMessage()
    );

    responder(
        false,
        "Ocurrió un error interno.",
        null,
        500
    );
}
