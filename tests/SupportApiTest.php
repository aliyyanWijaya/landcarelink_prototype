<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/models/Group.php';
require_once __DIR__ . '/../backend/services/AnthropicClient.php';
require_once __DIR__ . '/../backend/controllers/SupportController.php';

/**
 * Tests for the /api/support endpoint.
 *
 * The external Claude API call is stubbed via the SupportChatClient interface,
 * so the suite never makes a real network request. An in-memory SQLite
 * database supplies the groups data the controller summarises.
 */
final class SupportApiTest extends TestCase
{
    private PDO $pdo;

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

        (new GroupController(new Group($this->pdo)))->store([
            'name'          => 'Waikato River Care Collective',
            'type'          => 'catchment_collective',
            'region'        => 'Waikato',
            'contact_email' => 'info@waikatorivercare.org.nz',
            'latitude'      => -37.65,
            'longitude'     => 175.17,
        ]);
    }

    private function controllerWith(SupportChatClient $chat): SupportController
    {
        return new SupportController(new Group($this->pdo), $chat);
    }

    /** A stub that records what it was asked and returns a canned reply. */
    private function stubReturning(string $reply): SupportChatClient
    {
        return new class ($reply) implements SupportChatClient {
            public ?string $lastSystem = null;
            /** @var array<int, array{role: string, content: string}>|null */
            public ?array $lastMessages = null;

            public function __construct(private string $reply)
            {
            }

            public function reply(string $system, array $messages): string
            {
                $this->lastSystem   = $system;
                $this->lastMessages = $messages;
                return $this->reply;
            }
        };
    }

    /** A stub that fails like a real upstream error would. */
    private function stubThrowing(string $message): SupportChatClient
    {
        return new class ($message) implements SupportChatClient {
            public function __construct(private string $message)
            {
            }

            public function reply(string $system, array $messages): string
            {
                throw new SupportApiException($this->message);
            }
        };
    }

    public function testReturnsAssistantReply(): void
    {
        $controller = $this->controllerWith($this->stubReturning('You can add a group using the top row.'));

        $res = $controller->handle(['message' => 'How do I add a group?']);

        $this->assertSame(200, $res['status']);
        $this->assertSame('You can add a group using the top row.', $res['body']['reply']);
    }

    public function testGroundsThePromptInTheGroupsData(): void
    {
        $stub       = $this->stubReturning('ok');
        $controller = $this->controllerWith($stub);

        $controller->handle(['message' => 'Which groups are in Waikato?']);

        // The current groups must be summarised into the system prompt.
        $this->assertStringContainsString('Waikato River Care Collective', (string) $stub->lastSystem);
        $this->assertStringContainsString('Waikato', (string) $stub->lastSystem);
        // The user's message is forwarded as a user turn.
        $this->assertSame('user', $stub->lastMessages[0]['role']);
        $this->assertSame('Which groups are in Waikato?', $stub->lastMessages[0]['content']);
    }

    public function testRejectsMissingMessage(): void
    {
        $res = $this->controllerWith($this->stubReturning('unused'))->handle([]);
        $this->assertSame(422, $res['status']);
        $this->assertArrayHasKey('error', $res['body']);
    }

    public function testRejectsEmptyMessage(): void
    {
        $res = $this->controllerWith($this->stubReturning('unused'))->handle(['message' => '   ']);
        $this->assertSame(422, $res['status']);
    }

    public function testHandlesUpstreamFailureGracefully(): void
    {
        $controller = $this->controllerWith($this->stubThrowing('The support assistant could not be reached.'));

        $res = $controller->handle(['message' => 'Hello?']);

        // Clear error, never a 500.
        $this->assertSame(502, $res['status']);
        $this->assertNotSame(500, $res['status']);
        $this->assertSame('The support assistant could not be reached.', $res['body']['error']);
    }

    public function testMissingApiKeySurfacesAsClearError(): void
    {
        // The real cURL client with no key must fail fast without a network call.
        $controller = $this->controllerWith(new AnthropicClient(null));

        $res = $controller->handle(['message' => 'Hi']);

        $this->assertSame(502, $res['status']);
        $this->assertStringContainsString('not configured', $res['body']['error']);
    }
}
