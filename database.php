<?php

declare(strict_types=1);

const DEFAULT_DB_HOST = '127.0.0.1';
const DEFAULT_DB_PORT = '3306';
const DEFAULT_DB_NAME = 'todoist_lite';
const DEFAULT_DB_USER = 'root';
const DEFAULT_DB_PASS = '';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: DEFAULT_DB_HOST;
    $port = getenv('DB_PORT') ?: DEFAULT_DB_PORT;
    $name = getenv('DB_NAME') ?: DEFAULT_DB_NAME;
    $user = getenv('DB_USER') ?: DEFAULT_DB_USER;
    $pass = getenv('DB_PASS');
    $pass = $pass === false ? DEFAULT_DB_PASS : $pass;

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
