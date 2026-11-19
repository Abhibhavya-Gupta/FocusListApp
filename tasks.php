<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $userId = requireAuthApi();

    switch ($method) {
        case 'GET':
            listTasks($userId);
            break;
        case 'POST':
            verifyCsrfToken();
            createTask($userId, jsonInput());
            break;
        case 'PUT':
            verifyCsrfToken();
            updateTask($userId, jsonInput());
            break;
        case 'PATCH':
            verifyCsrfToken();
            toggleTask($userId, jsonInput());
            break;
        case 'DELETE':
            verifyCsrfToken();
            deleteTask($userId, jsonInput());
            break;
        default:
            jsonResponse([
                'success' => false,
                'message' => 'HTTP method not allowed.',
            ], 405);
    }
} catch (Throwable $exception) {
    apiExceptionResponse($exception);
}

function listTasks(int $userId): never
{
    $pdo = db();
    $view = $_GET['view'] ?? 'all';
    $search = normalizedString($_GET['search'] ?? '', 100);
    $categoryId = positiveIntOrNull($_GET['category_id'] ?? null);
    $sort = $_GET['sort'] ?? 'smart';

    $allowedViews = ['all', 'today', 'upcoming', 'overdue', 'completed'];
    if (!in_array($view, $allowedViews, true)) {
        $view = 'all';
    }

    $where = ['t.user_id = :user_id'];
    $parameters = ['user_id' => $userId];

    switch ($view) {
        case 'today':
            $where[] = 't.status = \'pending\'';
            $where[] = 'DATE(t.due_date) = CURDATE()';
            break;
        case 'upcoming':
            $where[] = 't.status = \'pending\'';
            $where[] = 'DATE(t.due_date) > CURDATE()';
            break;
        case 'overdue':
            $where[] = 't.status = \'pending\'';
            $where[] = 't.due_date IS NOT NULL';
            $where[] = 't.due_date < NOW()';
            break;
        case 'completed':
            $where[] = 't.status = \'completed\'';
            break;
        default:
            if ($search === '') {
                $where[] = 't.status = \'pending\'';
            }
    }

    if ($search !== '') {
        $where[] = '(t.title LIKE :search_title OR t.description LIKE :search_desc)';
        $parameters['search_title'] = '%' . $search . '%';
        $parameters['search_desc'] = '%' . $search . '%';
    }

    if ($categoryId !== null) {
        $where[] = 't.category_id = :category_id';
        $parameters['category_id'] = $categoryId;
    }

    $sortSql = match ($sort) {
        'due_asc' => 't.due_date IS NULL, t.due_date ASC, t.created_at DESC',
        'due_desc' => 't.due_date IS NULL, t.due_date DESC, t.created_at DESC',
        'priority' => "FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.due_date IS NULL, t.due_date ASC",
        'newest' => 't.created_at DESC',
        'oldest' => 't.created_at ASC',
        default => "CASE WHEN t.due_date IS NOT NULL AND t.due_date < NOW() AND t.status = 'pending' THEN 0 ELSE 1 END, FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.due_date IS NULL, t.due_date ASC, t.created_at DESC",
    };

    $sql = "
        SELECT
            t.id,
            t.user_id,
            t.title,
            t.description,
            t.category_id,
            t.priority,
            t.status,
            DATE_FORMAT(t.due_date, '%Y-%m-%d %H:%i:%s') AS due_date,
            DATE_FORMAT(t.completed_at, '%Y-%m-%d %H:%i:%s') AS completed_at,
            DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
            c.name AS category_name,
            c.color AS category_color,
            c.icon AS category_icon,
            CASE
                WHEN t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1
                ELSE 0
            END AS is_overdue,
            CASE
                WHEN t.due_date IS NOT NULL AND DATE(t.due_date) = CURDATE() THEN 1
                ELSE 0
            END AS is_today
        FROM tasks t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . $sortSql;

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $tasks = $statement->fetchAll();

    foreach ($tasks as &$task) {
        $task['id'] = (int) $task['id'];
        $task['user_id'] = (int) $task['user_id'];
        $task['category_id'] = $task['category_id'] !== null ? (int) $task['category_id'] : null;
        $task['is_overdue'] = (bool) $task['is_overdue'];
        $task['is_today'] = (bool) $task['is_today'];
    }
    unset($task);

    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'pending') AS pending,
            SUM(status = 'completed') AS completed,
            SUM(status = 'pending' AND due_date IS NOT NULL AND due_date < NOW()) AS overdue,
            SUM(status = 'pending' AND due_date IS NOT NULL AND DATE(due_date) = CURDATE()) AS today
        FROM tasks
        WHERE user_id = :user_id
    ");
    $summaryStmt->execute(['user_id' => $userId]);
    $summary = $summaryStmt->fetch();

    foreach (['total', 'pending', 'completed', 'overdue', 'today'] as $key) {
        $summary[$key] = (int) ($summary[$key] ?? 0);
    }

    $summary['completion_rate'] = $summary['total'] > 0
        ? (int) round(($summary['completed'] / $summary['total']) * 100)
        : 0;

    jsonResponse([
        'success' => true,
        'tasks' => $tasks,
        'summary' => $summary,
    ]);
}

function createTask(int $userId, array $input): never
{
    $task = validateTaskInput($userId, $input);

    $statement = db()->prepare("
        INSERT INTO tasks (user_id, title, description, category_id, priority, status, due_date, completed_at)
        VALUES (:user_id, :title, :description, :category_id, :priority, :status, :due_date, :completed_at)
    ");

    $statement->execute([
        'user_id' => $userId,
        'title' => $task['title'],
        'description' => $task['description'],
        'category_id' => $task['category_id'],
        'priority' => $task['priority'],
        'status' => $task['status'],
        'due_date' => $task['due_date'],
        'completed_at' => $task['status'] === 'completed' ? date('Y-m-d H:i:s') : null,
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Task created successfully.',
        'id' => (int) db()->lastInsertId(),
    ], 201);
}

function updateTask(int $userId, array $input): never
{
    $id = positiveIntOrNull($input['id'] ?? null);
    if ($id === null) {
        jsonResponse(['success' => false, 'message' => 'A valid task ID is required.'], 422);
    }

    assertTaskExists($id, $userId);
    $task = validateTaskInput($userId, $input);

    $statement = db()->prepare("
        UPDATE tasks
        SET
            title = :title,
            description = :description,
            category_id = :category_id,
            priority = :priority,
            status = :status,
            due_date = :due_date,
            completed_at = CASE
                WHEN :status_for_completed = 'completed' AND completed_at IS NULL THEN NOW()
                WHEN :status_for_pending = 'pending' THEN NULL
                ELSE completed_at
            END
        WHERE id = :id AND user_id = :user_id
    ");

    $statement->execute([
        'title' => $task['title'],
        'description' => $task['description'],
        'category_id' => $task['category_id'],
        'priority' => $task['priority'],
        'status' => $task['status'],
        'status_for_completed' => $task['status'],
        'status_for_pending' => $task['status'],
        'due_date' => $task['due_date'],
        'id' => $id,
        'user_id' => $userId,
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Task updated successfully.',
    ]);
}

function toggleTask(int $userId, array $input): never
{
    $id = positiveIntOrNull($input['id'] ?? null);
    $status = $input['status'] ?? '';

    if ($id === null || !in_array($status, ['pending', 'completed'], true)) {
        jsonResponse([
            'success' => false,
            'message' => 'A valid task ID and status are required.',
        ], 422);
    }

    assertTaskExists($id, $userId);

    $statement = db()->prepare("
        UPDATE tasks
        SET
            status = :status,
            completed_at = CASE WHEN :completed_status = 'completed' THEN NOW() ELSE NULL END
        WHERE id = :id AND user_id = :user_id
    ");
    $statement->execute([
        'status' => $status,
        'completed_status' => $status,
        'id' => $id,
        'user_id' => $userId,
    ]);

    jsonResponse([
        'success' => true,
        'message' => $status === 'completed' ? 'Task completed.' : 'Task restored.',
    ]);
}

function deleteTask(int $userId, array $input): never
{
    $id = positiveIntOrNull($input['id'] ?? null);
    if ($id === null) {
        jsonResponse(['success' => false, 'message' => 'A valid task ID is required.'], 422);
    }

    assertTaskExists($id, $userId);
    $statement = db()->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $id, 'user_id' => $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Task deleted.',
    ]);
}

function validateTaskInput(int $userId, array $input): array
{
    $title = normalizedString($input['title'] ?? '', 180);
    $description = normalizedString($input['description'] ?? '', 5000);
    $categoryId = positiveIntOrNull($input['category_id'] ?? null);
    $priority = $input['priority'] ?? 'medium';
    $status = $input['status'] ?? 'pending';
    $dueDate = parseDateTimeOrNull($input['due_date'] ?? null);

    if ($title === '') {
        jsonResponse(['success' => false, 'message' => 'Task title is required.'], 422);
    }

    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid task priority.'], 422);
    }

    if (!in_array($status, ['pending', 'completed'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid task status.'], 422);
    }

    if ($categoryId !== null) {
        $statement = db()->prepare('SELECT id FROM categories WHERE id = :id AND (user_id IS NULL OR user_id = :user_id)');
        $statement->execute(['id' => $categoryId, 'user_id' => $userId]);
        if (!$statement->fetch()) {
            jsonResponse(['success' => false, 'message' => 'The selected category does not exist.'], 422);
        }
    }

    return [
        'title' => $title,
        'description' => $description,
        'category_id' => $categoryId,
        'priority' => $priority,
        'status' => $status,
        'due_date' => $dueDate,
    ];
}

function assertTaskExists(int $id, int $userId): void
{
    $statement = db()->prepare('SELECT id FROM tasks WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $id, 'user_id' => $userId]);

    if (!$statement->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Task not found or access denied.'], 404);
    }
}
