<?php

declare(strict_types=1);

namespace Tests\Providers\XAI;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\ChunkType;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Prism;
use Tests\Fixtures\FixtureResponse;

beforeEach(function (): void {
    config()->set('prism.providers.xai.api_key', env('XAI_API_KEY', 'fake-key'));
});

it('can generate text with a basic stream', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withPrompt('Who are you?')
        ->asStream();

    $text = '';
    $chunks = [];

    $responseId = null;
    $model = null;

    foreach ($response as $chunk) {
        if ($chunk->meta) {
            $responseId = $chunk->meta?->id;
            $model = $chunk->meta?->model;
        }

        $chunks[] = $chunk;
        $text .= $chunk->text;
    }

    expect($chunks)->not
        ->toBeEmpty()
        ->and($text)->not
        ->toBeEmpty()
        ->and($responseId)
        ->not
        ->toBeNull()
        ->toStartWith('chatcmpl-')
        ->and($model)->toBe('grok-4');

    // Verify the HTTP request
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.x.ai/v1/chat/completions'
            && $body['stream'] === true
            && $body['model'] === 'grok-4';
    });
});

it('can generate text using tools with streaming', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-with-tools-responses');

    $tools = [
        Tool::as('get_weather')
            ->for('useful when you need to search for current weather conditions')
            ->withStringParameter('city', 'The city that you want the weather for')
            ->using(fn (string $city): string => "The weather will be 75° and sunny in {$city}"),

        Tool::as('search')
            ->for('useful for searching current events or data')
            ->withStringParameter('query', 'The detailed search query')
            ->using(fn (string $query): string => "Search results for: {$query}"),
    ];

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withTools($tools)
        ->withMaxSteps(4)
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->asStream();

    $text = '';
    $chunks = [];
    $toolCalls = [];
    $toolResults = [];

    foreach ($response as $chunk) {
        $chunks[] = $chunk;

        if ($chunk->chunkType === ChunkType::ToolCall) {
            $toolCalls = array_merge($toolCalls, $chunk->toolCalls);
        }

        if ($chunk->chunkType === ChunkType::ToolResult) {
            $toolResults = array_merge($toolResults, $chunk->toolResults);
        }

        $text .= $chunk->text;
    }

    expect($chunks)->not
        ->toBeEmpty()
        ->and($toolCalls)->toHaveCount(2)
        ->and($toolResults)->toHaveCount(2);

    // Verify the HTTP request
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.x.ai/v1/chat/completions'
            && isset($body['tools'])
            && $body['stream'] === true
            && $body['model'] === 'grok-4';
    });
});

it('handles max_tokens parameter correctly', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withMaxTokens(1000)
        ->withPrompt('Who are you?')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    // Verify the HTTP request
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.x.ai/v1/chat/completions'
            && $body['max_tokens'] === 1000;
    });
});

it('uses default max_tokens when not specified', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withPrompt('Who are you?')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    // Verify the HTTP request
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.x.ai/v1/chat/completions'
            && $body['max_tokens'] === 2048;
    });
});

it('can process a complete conversation with multiple tool calls', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-with-tools-responses');

    $tools = [
        Tool::as('get_weather')
            ->for('Get weather information')
            ->withStringParameter('city', 'City name')
            ->using(fn (string $city): string => "The weather in {$city} is 75° and sunny."),

        Tool::as('search')
            ->for('Search for information')
            ->withStringParameter('query', 'The search query')
            ->using(fn (string $query): string => 'Tigers game is at 3pm in Detroit today.'),
    ];

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withTools($tools)
        ->withMaxSteps(5)
        ->withPrompt('What time is the Tigers game today and should I wear a coat in Detroit?')
        ->asStream();

    $fullResponse = '';
    $toolCalls = [];
    $toolResults = [];

    foreach ($response as $chunk) {
        if ($chunk->chunkType === ChunkType::ToolCall) {
            $toolCalls = array_merge($toolCalls, $chunk->toolCalls);
        }

        if ($chunk->chunkType === ChunkType::ToolResult) {
            $toolResults = array_merge($toolResults, $chunk->toolResults);
        }

        $fullResponse .= $chunk->text;
    }

    expect($toolCalls)
        ->toHaveCount(2)
        ->and($toolResults)
        ->toHaveCount(2)
        ->and($fullResponse)->not->toBeEmpty();

    // Verify the HTTP request
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.x.ai/v1/chat/completions'
            && isset($body['tools'])
            && $body['stream'] === true
            && $body['model'] === 'grok-4';
    });
});

it('handles system prompts correctly', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withSystemPrompt('You are a helpful assistant.')
        ->withPrompt('Who are you?')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return count($body['messages']) === 2
            && $body['messages'][0]['role'] === 'system'
            && $body['messages'][1]['role'] === 'user';
    });
});

it('excludes null parameters from request', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withPrompt('Who are you?')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        // Should not include temperature or top_p when not set
        expect($body)->not
            ->toHaveKey('temperature')
            ->and($body)->not->toHaveKey('top_p');

        return true;
    });
});

it('handles large max_tokens values', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withMaxTokens(8192)
        ->withPrompt('Who are you?')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $body['max_tokens'] === 8192;
    });
});

it('handles tool choice parameter correctly', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-with-tools-responses');

    $tools = [
        Tool::as('get_weather')
            ->for('useful when you need to search for current weather conditions')
            ->withStringParameter('city', 'The city that you want the weather for')
            ->using(fn (string $city): string => "The weather will be 75° and sunny in {$city}"),
    ];

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withTools($tools)
        ->withToolChoice('get_weather')
        ->withPrompt('What is the weather in Detroit?')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return isset($body['tool_choice'])
            && $body['tool_choice']['type'] === 'function'
            && $body['tool_choice']['function']['name'] === 'get_weather';
    });
});

it('validates response format structure', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-basic-text-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withPrompt('Test')
        ->asStream();

    foreach ($response as $chunk) {
        // Process stream
    }

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        expect($body)->toHaveKeys(['model', 'messages', 'stream', 'max_tokens']);
        expect($body['model'])->toBe('grok-4');
        expect($body['stream'])->toBeTrue();
        expect($body['max_tokens'])->toBe(2048);

        return true;
    });
});

it('can handle thinking/reasoning chunks', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-with-reasoning-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withPrompt('Solve this complex math problem: What is 15 * 23?')
        ->asStream();

    $thinkingContent = '';
    $regularContent = '';
    $thinkingChunks = 0;
    $regularChunks = 0;

    foreach ($response as $chunk) {
        if ($chunk->chunkType === ChunkType::Thinking) {
            $thinkingContent .= $chunk->text;
            $thinkingChunks++;
        } elseif ($chunk->chunkType === ChunkType::Text) {
            $regularContent .= $chunk->text;
            $regularChunks++;
        }
    }

    expect($thinkingChunks)
        ->toBeGreaterThan(0)
        ->and($regularChunks)->toBeGreaterThan(0)
        ->and($thinkingContent)->not
        ->toBeEmpty()
        ->and($regularContent)->not
        ->toBeEmpty()
        ->and($thinkingContent)->toContain('multiply')
        ->and($regularContent)->toContain('345');
});

it('can disable thinking content extraction', function (): void {
    FixtureResponse::fakeResponseSequence('v1/chat/completions', 'xai/stream-with-reasoning-responses');

    $response = Prism::text()
        ->using('xai', 'grok-4')
        ->withProviderOptions([
            'thinking' => [
                'enabled' => false,
            ],
        ])
        ->withPrompt('Solve this complex math problem: What is 15 * 23?')
        ->asStream();

    $thinkingChunks = 0;
    $regularChunks = 0;

    foreach ($response as $chunk) {
        if ($chunk->chunkType === ChunkType::Thinking) {
            $thinkingChunks++;
        } elseif ($chunk->chunkType === ChunkType::Text) {
            $regularChunks++;
        }
    }

    expect($thinkingChunks)->toBe(0); // No thinking chunks when disabled
    expect($regularChunks)->toBeGreaterThan(0); // Still have regular content
});
