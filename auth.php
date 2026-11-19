<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $_GET['action'] ?? '';

try {
    if ($method === 'POST') {
        verifyCsrfToken();
        $input = jsonInput();

        switch ($action) {
            case 'login':
                handleLogin($input);
                break;
            case 'register':
                handleRegister($input);
                break;
            case 'logout':
                handleLogout();
                break;
            default:
                jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
        }
    } else {
        jsonResponse(['success' => false, 'message' => 'HTTP method not allowed.'], 405);
    }
} catch (Throwable $e) {
    apiExceptionResponse($e);
}

function handleLogin(array $input): never
{
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Please provide both email and password.'], 422);
    }

    $stmt = db()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    // Regenerate session ID for security against session fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    jsonResponse([
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => 'index.php',
    ]);
}

function handleRegister(array $input): never
{
    $name = normalizedString($input['name'] ?? '', 80);
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Full name is required.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Please provide a valid email address.'], 422);
    }

    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 422);
    }

    if ($password !== $confirmPassword) {
        jsonResponse(['success' => false, 'message' => 'Passwords do not match.'], 422);
    }

    // Check if email already exists
    $check = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'An account with this email address already exists.'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)');
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'hash' => $hash,
    ]);

    $newUserId = (int)db()->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    jsonResponse([
        'success' => true,
        'message' => 'Account registered successfully.',
        'redirect' => 'index.php',
    ], 201);
}

function handleLogout(): never
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    jsonResponse([
        'success' => true,
        'message' => 'Logged out successfully.',
        'redirect' => 'login.php',
    ]);
}
