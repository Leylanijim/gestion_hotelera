<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Método no permitido. Utiliza POST."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Iniciar o recuperar la sesión actual.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Eliminar los datos de la sesión.
$_SESSION = [];

// Eliminar la cookie de sesión del navegador.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => $params["samesite"] ?? "Lax"
    ]);
}

// Destruir la sesión.
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Sesión cerrada correctamente."
], JSON_UNESCAPED_UNICODE);
