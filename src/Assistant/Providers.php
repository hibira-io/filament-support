<?php

namespace Hibira\FilamentSupport\Assistant;

/**
 * The AI services the desk can call with the customer's own key. Most speak
 * OpenAI's chat completions API; Anthropic has its own.
 */
class Providers
{
    public const ALL = [
        'openai' => ['label' => 'OpenAI', 'api' => 'openai', 'url' => 'https://api.openai.com/v1', 'model' => 'gpt-5-mini'],
        'anthropic' => ['label' => 'Anthropic (Claude)', 'api' => 'anthropic', 'url' => 'https://api.anthropic.com/v1', 'model' => 'claude-haiku-5-5'],
        'gemini' => ['label' => 'Google Gemini', 'api' => 'openai', 'url' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'model' => 'gemini-2.5-flash'],
        'openrouter' => ['label' => 'OpenRouter', 'api' => 'openai', 'url' => 'https://openrouter.ai/api/v1', 'model' => 'openai/gpt-5-mini'],
        'deepseek' => ['label' => 'DeepSeek', 'api' => 'openai', 'url' => 'https://api.deepseek.com/v1', 'model' => 'deepseek-chat'],
        'custom' => ['label' => 'OpenAI-compatible (Ollama, vLLM, Azure…)', 'api' => 'openai', 'url' => null, 'model' => 'llama3.1'],
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $p): string => $p['label'], self::ALL);
    }

    public static function api(string $provider): string
    {
        return self::ALL[$provider]['api'] ?? 'openai';
    }

    public static function url(string $provider, ?string $override): ?string
    {
        $url = filled($override) ? $override : (self::ALL[$provider]['url'] ?? null);

        return $url === null ? null : rtrim($url, '/');
    }

    public static function suggestedModel(string $provider): string
    {
        return self::ALL[$provider]['model'] ?? '';
    }
}
