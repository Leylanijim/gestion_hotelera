<?php

class Database
{
    private string $host = "localhost";
    private string $usuario = "root";
    private string $password = "";
    private string $baseDatos = "sistema_gestion_hotelera";

    public function conectar(): mysqli
    {
        $conexion = new mysqli(
            $this->host,
            $this->usuario,
            $this->password,
            $this->baseDatos
        );

        if ($conexion->connect_error) {
            http_response_code(500);

            header("Content-Type: application/json; charset=UTF-8");

            echo json_encode([
                "success" => false,
                "message" => "No se pudo conectar con la base de datos.",
                "error" => $conexion->connect_error
            ]);

            exit;
        }

        $conexion->set_charset("utf8mb4");

        return $conexion;
    }
}