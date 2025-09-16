<?php

declare(strict_types=1);

namespace Tests\Providers\OpenAI;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Enums\StructuredMode;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Prism;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Tests\Fixtures\FixtureResponse;

it('returns structured output', function (): void {
    FixtureResponse::fakeResponseSequence('v1/responses', 'openai/structured-structured-mode');

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    $response = Prism::structured()
        ->withSchema($schema)
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->asStructured();

    expect($response->structured)->toBeArray();
    expect($response->structured)->toHaveKeys([
        'weather',
        'game_time',
        'coat_required',
    ]);
    expect($response->structured['weather'])->toBeString();
    expect($response->structured['game_time'])->toBeString();
    expect($response->structured['coat_required'])->toBeBool();
});

it('returns structured output using json mode', function (): void {
    FixtureResponse::fakeResponseSequence('v1/responses', 'openai/structured-json-mode');

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    $response = Prism::structured()
        ->withSchema($schema)
        ->using(Provider::OpenAI, 'gpt-4-turbo')
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->asStructured();

    expect($response->structured)->toBeArray();
    expect($response->structured)->toHaveKeys([
        'weather',
        'game_time',
        'coat_required',
    ]);
    expect($response->structured['weather'])->toBeString();
    expect($response->structured['game_time'])->toBeString();
    expect($response->structured['coat_required'])->toBeBool();
});

it('schema strict defaults to null', function (): void {
    FixtureResponse::fakeResponseSequence('v1/responses', 'openai/strict-schema-defaults');

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
    );

    $response = Prism::structured()
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withSchema($schema)
        ->withSystemPrompt('The game time is 3pm and the weather is 80° and sunny')
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(array_keys(data_get($body, 'text.format')))->not->toContain('strict');

        return true;
    });
});

it('uses meta to define strict mode', function (): void {
    FixtureResponse::fakeResponseSequence(
        'v1/responses',
        'openai/strict-schema-setting-set'
    );

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    $response = Prism::structured()
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withSchema($schema)
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->withProviderOptions([
            'schema' => ['strict' => true],
        ])
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(data_get($body, 'text.format.strict'))->toBeTrue();

        return true;
    });
});

it('throws an exception when there is a refusal', function (): void {
    $this->expectException(PrismException::class);
    $this->expectExceptionMessage('OpenAI Refusal: Could not process your request');

    Http::fake([
        'v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'type' => 'refusal',
                    'refusal' => 'Could not process your request',
                ]],
            ]],
        ]),
    ]);

    Http::preventStrayRequests();

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    Prism::structured()
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withSchema($schema)
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(data_get($body, 'text.format.strict'))->toBeTrue();

        return true;
    });
});

it('throws an exception for o1 models', function (string $model): void {
    $this->expectException(PrismException::class);
    $this->expectExceptionMessage(sprintf('Structured output is not supported for %s', $model));

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
    );

    Prism::structured()
        ->using(Provider::OpenAI, $model)
        ->withSchema($schema)
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->asStructured();
})->with([
    'o1-mini',
    'o1-mini-2024-09-12',
    'o1-preview',
    'o1-preview-2024-09-12',
]);

it('sets usage correctly with automatic caching', function (): void {
    FixtureResponse::fakeResponseSequence(
        'v1/responses',
        'openai/structured-cache-usage-automatic-caching',
    );

    $schema = new ObjectSchema(
        name: 'output',
        description: 'the output object',
        properties: [
            new StringSchema('answer', 'Your answer'),
        ],
        requiredFields: ['answer']
    );

    $prompt = fake()->paragraphs(40, true);

    Prism::structured()
        ->using('openai', 'gpt-4o')
        ->withPrompt($prompt)
        ->withSchema($schema)
        ->usingStructuredMode(StructuredMode::Structured)
        ->asStructured();

    $two = Prism::structured()
        ->using('openai', 'gpt-4o')
        ->withPrompt($prompt)
        ->withSchema($schema)
        ->usingStructuredMode(StructuredMode::Structured)
        ->asStructured();

    expect($two->usage)
        ->promptTokens->toEqual(1531 - 1408)
        ->completionTokens->toEqual(583)
        ->cacheWriteInputTokens->toEqual(null)
        ->cacheReadInputTokens->toEqual(1408);
});

it('uses meta to provide previous response id', function (): void {
    FixtureResponse::fakeResponseSequence(
        'v1/responses',
        'openai/structured-structured-mode'
    );

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    Prism::structured()
        ->withSchema($schema)
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withPrompt('What time is the game we talked about and should I wear a coat?')
        ->withProviderOptions([
            'previous_response_id' => 'resp_foo',
        ])
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(data_get($body, 'previous_response_id'))->toBe('resp_foo');

        return true;
    });
});

it('uses meta to set auto truncation', function (): void {
    FixtureResponse::fakeResponseSequence(
        'v1/responses',
        'openai/structured-structured-mode'
    );

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    Prism::structured()
        ->withSchema($schema)
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withPrompt('What time is the game we talked about and should I wear a coat?')
        ->withProviderOptions([
            'truncation' => 'auto',
        ])
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(data_get($body, 'truncation'))->toBe('auto');

        return true;
    });
});

it('uses meta to define strict mode as false', function (): void {
    FixtureResponse::fakeResponseSequence(
        'v1/responses',
        'openai/structured-structured-mode'
    );

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    Prism::structured()
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withSchema($schema)
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->withProviderOptions([
            'schema' => ['strict' => false],
        ])
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(data_get($body, 'text.format.strict'))->toBeFalse();

        return true;
    });
});

it('uses meta to define strict mode as null', function (): void {
    FixtureResponse::fakeResponseSequence(
        'v1/responses',
        'openai/structured-structured-mode'
    );

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    Prism::structured()
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withSchema($schema)
        ->withPrompt('What time is the tigers game today and should I wear a coat?')
        ->withProviderOptions()
        ->asStructured();

    Http::assertSent(function (Request $request): true {
        $body = json_decode($request->body(), true);

        expect(array_keys(data_get($body, 'text.format')))->not->toContain('strict');

        return true;
    });
});

it('sends reasoning effort when defined', function (): void {
    FixtureResponse::fakeResponseSequence('v1/responses', 'openai/structured-reasoning-effort');

    $schema = new ObjectSchema(
        'output',
        'the output object',
        [
            new StringSchema('weather', 'The weather forecast'),
            new StringSchema('game_time', 'The tigers game time'),
            new BooleanSchema('coat_required', 'whether a coat is required'),
        ],
        ['weather', 'game_time', 'coat_required']
    );

    Prism::structured()
        ->using('openai', 'gpt-5')
        ->withPrompt('Who are you?')
        ->withProviderOptions([
            'reasoning' => [
                'effort' => 'low',
            ],
        ])
        ->withSchema($schema)
        ->asStructured();

    Http::assertSent(fn (Request $request): bool => $request->data()['reasoning']['effort'] === 'low');
});
