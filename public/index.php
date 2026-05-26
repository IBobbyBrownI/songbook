<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . "/../.env");

$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
    $_ENV["DB_HOST"],
    $_ENV["DB_PORT"],
    $_ENV["DB_NAME"]
);

$pdo = new PDO
(   $dsn, $_ENV["DB_USER"],
    $_ENV["DB_PASSWORD"],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$stmt = $pdo->query("SELECT NOW()");
var_dump($stmt->fetch());

echo "Hello songbook!";