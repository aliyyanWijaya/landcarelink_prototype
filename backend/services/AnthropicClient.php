<?php
/**
 * Server-side Claude (Anthropic) client built on plain PHP cURL — no SDK, to
 * keep the prototype dependency-light.
 *
 * The interface + exception are intentionally separate from the cURL
 * implementation so controllers depend on the abstraction and tests can
 * stub the client without making real network calls.
 */

require_once __DIR__ . '/../config/anthropic.php';

/**
 * Raised for any expected upstream failure (missing key, network/timeout,
 * non-2xx API response, malformed body). The message is safe to surface to
 * the client — it never contains the API key or raw provider internals.
 */
class SupportApiException extends RuntimeException
{
}

interface SupportChatClient
{
    /**
     * Send a system prompt + conversation to Claude and return the reply text.
     *
     * @param string                                              $system   system prompt
     * @param array<int, array{role: string, content: string}>   $messages conversation turns
     * @return string the assistant's reply
     * @throws SupportApiException on any upstream failure
     */
    public function reply(string $system, array $messages): string;
}

/**
 * Calls the Anthropic Messages API over cURL.
 */
class AnthropicClient implements SupportChatClient
{
    private ?string $apiKey;
    private string $model;
    private int $maxTokens;
    private int $timeout;

    public function __construct(
        ?string $apiKey,
        string $model = ANTHROPIC_MODEL,
        int $maxTokens = ANTHROPIC_MAX_TOKENS,
        int $timeout = ANTHROPIC_TIMEOUT
    ) {
        $this->apiKey    = $apiKey;
        $this->model     = $model;
        $this->maxTokens = $maxTokens;
        $this->timeout   = $timeout;
    }

    public function reply(string $system, array $messages): string
    {
        if ($this->apiKey === null) {
            throw new SupportApiException(
                'The support assistant is not configured yet (missing API key). '
                . 'Please set ANTHROPIC_API_KEY on the server.'
            );
        }

        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ]);

        if ($payload === false) {
            throw new SupportApiException('Could not build the support request.');
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $raw    = curl_exec($ch);
        $errNo  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errNo !== 0) {
            // Network failure or timeout — don't leak the raw cURL error.
            throw new SupportApiException(
                'The support assistant could not be reached right now. Please try again in a moment.'
            );
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new SupportApiException('The support assistant returned an unexpected response.');
        }

        if ($status < 200 || $status >= 300) {
            // Log the provider detail server-side; return a generic message.
            $detail = $decoded['error']['message'] ?? ('HTTP ' . $status);
            error_log('Anthropic API error: ' . (is_string($detail) ? $detail : json_encode($detail)));
            throw new SupportApiException(
                'The support assistant ran into a problem answering that. Please try again.'
            );
        }

        return $this->extractText($decoded);
    }

    /**
     * Pull the concatenated text out of the Messages API `content` blocks.
     *
     * @param array<string, mixed> $decoded
     */
    private function extractText(array $decoded): string
    {
        $blocks = $decoded['content'] ?? [];
        $text   = '';
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text' && isset($block['text'])) {
                    $text .= (string) $block['text'];
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new SupportApiException('The support assistant did not return an answer. Please try again.');
        }
        return $text;
    }
}
