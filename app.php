<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

require_once __DIR__ . '/database.php';

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if ($providedToken === '' || !hash_equals(csrfToken(), $providedToken)) {
        jsonResponse([
            'success' => false,
            'message' => 'Your session token is invalid. Refresh the page and try again.',
        ], 419);
    }
}

function jsonInput(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid JSON request body.',
        ], 400);
    }

    return $decoded;
}

function jsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizedString(mixed $value, int $maxLength): string
{
    $value = trim((string) $value);

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function positiveIntOrNull(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $integer === false ? null : $integer;
}

function parseDateTimeOrNull(mixed $value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $input = trim((string) $value);
    $formats = ['Y-m-d\\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $input);
        $errors = DateTime::getLastErrors();

        if ($date instanceof DateTime && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    jsonResponse([
        'success' => false,
        'message' => 'The due date is invalid.',
    ], 422);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'category-' . bin2hex(random_bytes(3));
}

function apiExceptionResponse(Throwable $exception): never
{
    error_log($exception->__toString());
    $debug = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL);

    jsonResponse([
        'success' => false,
        'message' => $debug ? $exception->getMessage() : 'A server error occurred. Check the PHP error log for details.',
    ], 500);
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

function requireAuthApi(): int
{
    $userId = currentUserId();
    if ($userId === null) {
        jsonResponse([
            'success' => false,
            'message' => 'Unauthorized. Please sign in to continue.',
        ], 401);
    }

    return $userId;
}

function requireAuthWeb(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}
