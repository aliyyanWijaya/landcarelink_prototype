<?php

/**
 * SIMPLE RATE LIMITER (session-based)
 */
function rate_limit(): void {
    session_start();

    $limit = 5;      // max 5 request
    $window = 10;    // per 10 seconds

    if (!isset($_SESSION['rl'])) {
        $_SESSION['rl'] = [];
    }

    $_SESSION['rl'] = array_filter(
        $_SESSION['rl'],
        fn($t) => $t > time() - $window
    );

    if (count($_SESSION['rl']) >= $limit) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }

    $_SESSION['rl'][] = time();
}

/**
 * BASIC INPUT VALIDATION
 */
function validate_message(array $input): ?string {
    if (!isset($input['message'])) {
        return 'Message is required';
    }

    $msg = trim($input['message']);

    if ($msg === '') {
        return 'Message cannot be empty';
    }

    if (strlen($msg) < 3) {
        return 'Message too short';
    }

    if (strlen($msg) > 500) {
        return 'Message too long';
    }

    // anti-spam (link / injection style)
    if (preg_match('/http|www|href|<script/i', $msg)) {
        return 'Suspicious content detected';
    }

    return null;
}

/**
 * OPTIONAL CAPTCHA VALIDATION (Cloudflare Turnstile)
 */
function verify_captcha(?string $token): bool {
    if (!$token) return false;

    $secret = 'YOUR_SECRET_KEY';

    $response = file_get_contents(
        "https://challenges.cloudflare.com/turnstile/v0/siteverify",
        false,
        stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded",
                'content' => http_build_query([
                    'secret'   => $secret,
                    'response' => $token
                ])
            ]
        ])
    );

    $result = json_decode($response, true);
    return $result['success'] ?? false;
}


/**
 * MAIN ROUTER
 */
function route(
    string $method,
    string $path,
    array $input,
    GroupController $controller,
    SupportController $support
): array {

    $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

    /**
     * ✅ SUPPORT ENDPOINT (SECURED)
     */
    if (in_array('support', $segments, true)) {

        if ($method !== 'POST') {
            return ['status' => 405, 'body' => ['error' => 'Method not allowed']];
        }

        // ✅ rate limit
        rate_limit();

        // ✅ validate message
        $error = validate_message($input);
        if ($error !== null) {
            return ['status' => 400, 'body' => ['error' => $error]];
        }

    

        return $support->handle($input);
    }

    /**
     * GROUP ROUTES
     */
    $idx = array_search('groups', $segments, true);
    if ($idx === false) {
        return ['status' => 404, 'body' => ['error' => 'Resource not found']];
    }

    $id = $segments[$idx + 1] ?? null;

    switch ($method) {
        case 'GET':
            return $id === null ? $controller->index() : $controller->show($id);

        case 'POST':
            return $controller->store($input);

        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                return ['status' => 400, 'body' => ['error' => 'An id is required']];
            }
            return $controller->update($id, $input);

        case 'DELETE':
            if ($id === null) {
                return ['status' => 400, 'body' => ['error' => 'An id is required']];
            }
            return $controller->destroy($id);

        default:
            return ['status' => 405, 'body' => ['error' => 'Method not allowed']];
    }
}
