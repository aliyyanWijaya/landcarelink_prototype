<?php
/**
 * Tiny REST router.
 *
 * Maps an HTTP method + path to a GroupController action and returns the
 * controller's normalised response array. The router locates the `groups`
 * segment in the path, so it works regardless of the base path the server
 * is mounted under (e.g. /api/groups or /backend/public/api/groups).
 *
 * @param string               $method HTTP verb
 * @param string               $path   request path
 * @param array<string, mixed> $input  decoded JSON body
 * @return array{status:int, body:array}
 */
function route(string $method, string $path, array $input, GroupController $controller): array
{
    $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

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
                return ['status' => 400, 'body' => ['error' => 'An id is required to update a group']];
            }
            return $controller->update($id, $input);

        case 'DELETE':
            if ($id === null) {
                return ['status' => 400, 'body' => ['error' => 'An id is required to delete a group']];
            }
            return $controller->destroy($id);

        default:
            return ['status' => 405, 'body' => ['error' => 'Method not allowed']];
    }
}
