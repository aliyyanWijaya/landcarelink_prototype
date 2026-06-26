<?php
/**
 * Anthropic / Claude API configuration.
 *
 * The API key is read from the environment only (ANTHROPIC_API_KEY) — it is
 * never hardcoded here and never sent to the frontend. The front controller
 * loads .env via phpdotenv, so the key is available on $_ENV / getenv().
 */

/** Default Claude model used for support replies. */
const ANTHROPIC_MODEL = 'claude-haiku-4-5-20251001';

/** Cap the reply length — support answers are meant to be short. */
const ANTHROPIC_MAX_TOKENS = 1024;

/** Total request timeout (seconds) for the Claude API call. */
const ANTHROPIC_TIMEOUT = 30;

/**
 * Read the Anthropic API key from the environment.
 *
 * @return string|null the key, or null when it is not configured
 */
function get_anthropic_api_key(): ?string
{
    $key = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
    if ($key === false || $key === null || trim((string) $key) === '') {
        return null;
    }
    return (string) $key;
}
