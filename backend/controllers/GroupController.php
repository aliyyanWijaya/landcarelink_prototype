<?php
require_once __DIR__ . '/../models/Group.php';

/**
 * GroupController — request handling + validation for Group resources.
 *
 * Each action returns a normalised response array:
 *     ['status' => int, 'body' => array]
 * The front controller (public/index.php) turns this into an HTTP response.
 * Keeping handlers free of HTTP globals makes them directly unit-testable.
 */
class GroupController
{
    private Group $model;

    public function __construct(Group $model)
    {
        $this->model = $model;
    }

    /** GET /api/groups */
    public function index(): array
    {
        return $this->respond(200, ['data' => $this->model->all()]);
    }

    /** GET /api/groups/{id} */
    public function show($id): array
    {
        $group = $this->model->find((int) $id);
        if ($group === null) {
            return $this->error(404, 'Group not found');
        }
        return $this->respond(200, ['data' => $group]);
    }

    /** POST /api/groups */
    public function store(array $input): array
    {
        $errors = $this->validate($input);
        if ($errors) {
            return $this->error(422, 'Validation failed', $errors);
        }
        $group = $this->model->create($this->clean($input));
        return $this->respond(201, ['data' => $group]);
    }

    /** PUT /api/groups/{id} */
    public function update($id, array $input): array
    {
        if ($this->model->find((int) $id) === null) {
            return $this->error(404, 'Group not found');
        }
        $errors = $this->validate($input);
        if ($errors) {
            return $this->error(422, 'Validation failed', $errors);
        }
        $group = $this->model->update((int) $id, $this->clean($input));
        return $this->respond(200, ['data' => $group]);
    }

    /** DELETE /api/groups/{id} */
    public function destroy($id): array
    {
        if ($this->model->find((int) $id) === null) {
            return $this->error(404, 'Group not found');
        }
        $this->model->delete((int) $id);
        return $this->respond(200, ['message' => 'Group deleted']);
    }

    // ----- helpers -------------------------------------------------------

    /**
     * Validate input. Returns a map of field => message (empty = valid).
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if (empty($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen(trim($input['name'])) > 255) {
            $errors['name'] = 'Name must be 255 characters or fewer.';
        }

        if (empty($input['type']) || !in_array($input['type'], Group::TYPES, true)) {
            $errors['type'] = 'Type must be one of: ' . implode(', ', Group::TYPES) . '.';
        }

        if (empty($input['region']) || !is_string($input['region'])) {
            $errors['region'] = 'Region is required.';
        }

        if (empty($input['contact_email']) || !filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'A valid contact email is required.';
        }

        if (!isset($input['latitude']) || !is_numeric($input['latitude'])
            || $input['latitude'] < -90 || $input['latitude'] > 90) {
            $errors['latitude'] = 'Latitude must be a number between -90 and 90.';
        }

        if (!isset($input['longitude']) || !is_numeric($input['longitude'])
            || $input['longitude'] < -180 || $input['longitude'] > 180) {
            $errors['longitude'] = 'Longitude must be a number between -180 and 180.';
        }

        return $errors;
    }

    /**
     * Whitelist + normalise fields before they reach the model.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function clean(array $input): array
    {
        return [
            'name'          => trim((string) $input['name']),
            'type'          => $input['type'],
            'region'        => trim((string) $input['region']),
            'contact_email' => trim((string) $input['contact_email']),
            'latitude'      => (float) $input['latitude'],
            'longitude'     => (float) $input['longitude'],
        ];
    }

    private function respond(int $status, array $body): array
    {
        return ['status' => $status, 'body' => $body];
    }

    private function error(int $status, string $message, array $errors = []): array
    {
        $body = ['error' => $message];
        if ($errors) {
            $body['errors'] = $errors;
        }
        return ['status' => $status, 'body' => $body];
    }
}
