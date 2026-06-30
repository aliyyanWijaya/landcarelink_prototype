<?php
/**
 * Front controller / API entry point.
 *
 * Run locally with PHP's built-in server:
 *     php -S localhost:8000 -t backend/public
 *
 * All requests are JSON. Errors are returned as structured JSON.
 */


declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/anthropic.php';
require_once __DIR__ . '/../models/Group.php';
require_once __DIR__ . '/../services/AnthropicClient.php';
require_once __DIR__ . '/../controllers/GroupController.php';
require_once __DIR__ . '/../controllers/SupportController.php';
require_once __DIR__ . '/../routes/api.php';

// --- CORS + content type ---------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Pre-flight request: respond immediately.
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Parse request ---------------------------------------------------------
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$raw   = file_get_contents('php://input');
$input = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON in request body']);
        exit;
    }
    $input = is_array($decoded) ? $decoded : [];
}

// --- Dispatch --------------------------------------------------------------
try {
    $pdo        = get_db_connection();
    $model      = new Group($pdo);
    $controller = new GroupController($model);
    $support    = new SupportController($model, new AnthropicClient(get_anthropic_api_key()));
    $response   = route($method, $path, $input, $controller, $support);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'debug' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    exit;
}



http_response_code($response['status']);
echo json_encode($response['body']);
