<?php
require_once __DIR__ . '/../models/Group.php';
require_once __DIR__ . '/../services/AnthropicClient.php';

/**
 * SupportController — handles the AI support widget endpoint.
 *
 * Like GroupController, each action returns a normalised response array:
 *     ['status' => int, 'body' => array]
 * It holds no HTTP globals, so it is directly unit-testable with a stubbed
 * chat client (no real Claude API calls in tests).
 */
class SupportController
{
    /** Hard cap on inbound message length — keeps prompts small. */
    private const MESSAGE_MAX = 2000;

    private Group $model;
    private SupportChatClient $chat;

    public function __construct(Group $model, SupportChatClient $chat)
    {
        $this->model = $model;
        $this->chat  = $chat;
    }

    /** POST /api/support  body: {"message": "..."} -> {"reply": "..."} */
    public function handle(array $input): array
    {
        $message = $input['message'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            return $this->error(422, 'A non-empty "message" is required.');
        }
        if (mb_strlen($message) > self::MESSAGE_MAX) {
            return $this->error(422, 'Message is too long (max ' . self::MESSAGE_MAX . ' characters).');
        }

        // Ground the model in the current directory so it can answer data
        // questions like "which groups are in Waikato?" accurately.
        $system = $this->buildSystemPrompt($this->model->all());

        try {
            $reply = $this->chat->reply($system, [
                ['role' => 'user', 'content' => trim($message)],
            ]);
        } catch (SupportApiException $e) {
            // Graceful, clear error instead of a 500.
            return $this->error(502, $e->getMessage());
        }

        return ['status' => 200, 'body' => ['reply' => $reply]];
    }

    /**
     * Build the system prompt: behavioural rules + a compact snapshot of the
     * current groups data.
     *
     * @param array<int, array<string, mixed>> $groups
     */
    private function buildSystemPrompt(array $groups): string
    {
        $lines = [];
        foreach ($groups as $g) {
            $lines[] = sprintf(
                '- %s | %s | %s',
                $g['name'] ?? '',
                $g['type'] ?? '',
                $g['region'] ?? ''
            );
        }
        $list = $lines !== [] ? implode("\n", $lines) : '(no groups in the directory yet)';

        return <<<PROMPT
You are the in-app support assistant for LandcareLink, a web directory of community environmental groups in New Zealand. The page shows a map of groups, a searchable/filterable table, and lets users add groups (via an inline row at the top of the table) and edit them (the Edit button on each row).

Rules:
- Only answer questions about (a) how to use this LandcareLink page, or (b) the community groups listed below.
- Keep answers short: 2 to 4 sentences, plain text, no markdown headings.
- If you are asked something unrelated to LandcareLink or to the groups data, briefly say you can only help with using LandcareLink and its group directory.
- Do not invent groups or details that are not in the list below.

Current list of community groups (name | type | region):
{$list}
PROMPT;
    }

    private function error(int $status, string $message): array
    {
        return ['status' => $status, 'body' => ['error' => $message]];
    }
}
