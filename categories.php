<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $userId = requireAuthApi();

    switch ($method) {
        case 'GET':
            listCategories($userId);
            break;
        case 'POST':
            verifyCsrfToken();
            createCategory($userId, jsonInput());
            break;
        case 'DELETE':
            verifyCsrfToken();
            deleteCategory($userId, jsonInput());
            break;
        default:
            jsonResponse([
                'success' => false,
                'message' => 'HTTP method not allowed.',
            ], 405);
    }
} catch (PDOException $exception) {
    if ((string) $exception->getCode() === '23000') {
        jsonResponse([
            'success' => false,
            'message' => 'A category with that name already exists.',
        ], 409);
    }

    apiExceptionResponse($exception);
} catch (Throwable $exception) {
    apiExceptionResponse($exception);
}

function listCategories(int $userId): never
{
    $statement = db()->prepare("
        SELECT
            c.id,
            c.user_id,
            c.name,
            c.slug,
            c.color,
            c.icon,
            c.is_default,
            COUNT(t.id) AS task_count,
            SUM(t.status = 'pending') AS pending_count
        FROM categories c
        LEFT JOIN tasks t ON t.category_id = c.id AND t.user_id = :user_id
        WHERE c.user_id IS NULL OR c.user_id = :user_id2
        GROUP BY c.id, c.user_id, c.name, c.slug, c.color, c.icon, c.is_default
        ORDER BY c.is_default DESC, c.name ASC
    ");
    $statement->execute([
        'user_id' => $userId,
        'user_id2' => $userId,
    ]);

    $categories = $statement->fetchAll();

    foreach ($categories as &$category) {
        $category['id'] = (int) $category['id'];
        $category['is_default'] = (bool) $category['is_default'];
        $category['task_count'] = (int) $category['task_count'];
        $category['pending_count'] = (int) ($category['pending_count'] ?? 0);
    }
    unset($category);

    jsonResponse([
        'success' => true,
        'categories' => $categories,
    ]);
}

function createCategory(int $userId, array $input): never
{
    $name = normalizedString($input['name'] ?? '', 50);
    $icon = normalizedString($input['icon'] ?? '🏷️', 12);
    $color = strtoupper(normalizedString($input['color'] ?? '#6C63FF', 7));

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Category name is required.'], 422);
    }

    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
        jsonResponse(['success' => false, 'message' => 'Category color must be a valid hex color.'], 422);
    }

    $baseSlug = slugify($name);
    $slug = $baseSlug;
    $counter = 2;

    $check = db()->prepare('SELECT id FROM categories WHERE slug = :slug AND (user_id IS NULL OR user_id = :user_id)');
    while (true) {
        $check->execute(['slug' => $slug, 'user_id' => $userId]);
        if (!$check->fetch()) {
            break;
        }
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }

    $statement = db()->prepare("
        INSERT INTO categories (user_id, name, slug, color, icon, is_default)
        VALUES (:user_id, :name, :slug, :color, :icon, 0)
    ");
    $statement->execute([
        'user_id' => $userId,
        'name' => $name,
        'slug' => $slug,
        'color' => $color,
        'icon' => $icon === '' ? '🏷️' : $icon,
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Category created.',
        'id' => (int) db()->lastInsertId(),
    ], 201);
}

function deleteCategory(int $userId, array $input): never
{
    $id = positiveIntOrNull($input['id'] ?? null);
    if ($id === null) {
        jsonResponse(['success' => false, 'message' => 'A valid category ID is required.'], 422);
    }

    $statement = db()->prepare('SELECT id, is_default, user_id FROM categories WHERE id = :id');
    $statement->execute(['id' => $id]);
    $category = $statement->fetch();

    if (!$category) {
        jsonResponse(['success' => false, 'message' => 'Category not found.'], 404);
    }

    if ((bool) $category['is_default'] || $category['user_id'] === null) {
        jsonResponse(['success' => false, 'message' => 'Default system categories cannot be deleted.'], 422);
    }

    if ((int) $category['user_id'] !== $userId) {
        jsonResponse(['success' => false, 'message' => 'You do not have permission to delete this category.'], 403);
    }

    $countStatement = db()->prepare('SELECT COUNT(*) FROM tasks WHERE category_id = :id AND user_id = :user_id');
    $countStatement->execute(['id' => $id, 'user_id' => $userId]);

    if ((int) $countStatement->fetchColumn() > 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Move or delete tasks from this category before deleting it.',
        ], 422);
    }

    $deleteStatement = db()->prepare('DELETE FROM categories WHERE id = :id AND user_id = :user_id');
    $deleteStatement->execute(['id' => $id, 'user_id' => $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Category deleted.',
    ]);
}
