<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/models/Group.php';
require_once __DIR__ . '/../backend/controllers/GroupController.php';

/**
 * Endpoint-level tests for the Group CRUD API.
 *
 * The controller actions map 1:1 to REST endpoints, so testing them
 * exercises the same code paths the HTTP router calls. An in-memory SQLite
 * database is used so the suite runs with no external services.
 */
final class GroupApiTest extends TestCase
{
    private PDO $pdo;
    private GroupController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec(
            'CREATE TABLE `groups` (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL,
                type          TEXT NOT NULL,
                region        TEXT NOT NULL,
                contact_email TEXT NOT NULL,
                latitude      REAL NOT NULL,
                longitude     REAL NOT NULL,
                created_at    TEXT NOT NULL
            )'
        );

        $this->controller = new GroupController(new Group($this->pdo));
        $this->seed();
    }

    private function seed(): void
    {
        $this->controller->store([
            'name'          => 'Waikato River Care Collective',
            'type'          => 'catchment_collective',
            'region'        => 'Waikato',
            'contact_email' => 'info@waikatorivercare.org.nz',
            'latitude'      => -37.65,
            'longitude'     => 175.17,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Tauranga Moana Restoration',
            'type'          => 'environmental_group',
            'region'        => 'Bay of Plenty',
            'contact_email' => 'kaitiaki@taurangamoana.org.nz',
            'latitude'      => -37.6878,
            'longitude'     => 176.1651,
        ], $overrides);
    }

    /** GET /api/groups */
    public function testIndexReturnsAllGroups(): void
    {
        $res = $this->controller->index();
        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']);
        $this->assertSame('Waikato River Care Collective', $res['body']['data'][0]['name']);
    }

    /** GET /api/groups/{id} */
    public function testShowReturnsSingleGroup(): void
    {
        $res = $this->controller->show(1);
        $this->assertSame(200, $res['status']);
        $this->assertSame('Waikato', $res['body']['data']['region']);
    }

    public function testShowReturns404WhenMissing(): void
    {
        $res = $this->controller->show(999);
        $this->assertSame(404, $res['status']);
        $this->assertArrayHasKey('error', $res['body']);
    }

    /** POST /api/groups */
    public function testStoreCreatesGroup(): void
    {
        $res = $this->controller->store($this->validPayload());
        $this->assertSame(201, $res['status']);
        $this->assertSame('Tauranga Moana Restoration', $res['body']['data']['name']);
        $this->assertCount(2, $this->controller->index()['body']['data']);
    }

    public function testStoreRejectsInvalidInput(): void
    {
        $res = $this->controller->store($this->validPayload([
            'type'          => 'not_a_real_type',
            'contact_email' => 'nope',
            'latitude'      => 200,
        ]));
        $this->assertSame(422, $res['status']);
        $this->assertArrayHasKey('type', $res['body']['errors']);
        $this->assertArrayHasKey('contact_email', $res['body']['errors']);
        $this->assertArrayHasKey('latitude', $res['body']['errors']);
    }

    /** PUT /api/groups/{id} */
    public function testUpdateModifiesGroup(): void
    {
        $res = $this->controller->update(1, $this->validPayload(['name' => 'Renamed Group']));
        $this->assertSame(200, $res['status']);
        $this->assertSame('Renamed Group', $res['body']['data']['name']);
        $this->assertSame('Renamed Group', $this->controller->show(1)['body']['data']['name']);
    }

    public function testUpdateReturns404WhenMissing(): void
    {
        $res = $this->controller->update(999, $this->validPayload());
        $this->assertSame(404, $res['status']);
    }

    /** DELETE /api/groups/{id} */
    public function testDestroyDeletesGroup(): void
    {
        $res = $this->controller->destroy(1);
        $this->assertSame(200, $res['status']);
        $this->assertCount(0, $this->controller->index()['body']['data']);
    }

    public function testDestroyReturns404WhenMissing(): void
    {
        $res = $this->controller->destroy(999);
        $this->assertSame(404, $res['status']);
    }
}
