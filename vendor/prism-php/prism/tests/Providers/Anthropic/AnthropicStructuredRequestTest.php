<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Tests\Fixtures\FixtureResponse;

it('sends correct basic structured generation payload', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'person',
        'A person object',
        [
            'name' => new StringSchema('name', 'The person\'s name'),
        ],
        ['name']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([
            new UserMessage('Create a person named John'),
        ])
        ->withSchema($schema)
        ->asStructured();

    Http::assertSent(function (Request $request) use ($schema): bool {
        $payload = $request->data();

        expect($payload)->toHaveKeys(['model', 'messages', 'max_tokens']);
        expect($payload['model'])->toBe('claude-3-5-haiku-latest');

        expect($payload['messages'])->toBe([
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Create a person named John',
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Respond with ONLY JSON (i.e. not in backticks or a code block, with NO CONTENT outside the JSON) that matches the following schema: \n ".json_encode($schema->toArray(), JSON_PRETTY_PRINT).' ',
                    ],
                ],
            ],
        ]);

        expect($payload['max_tokens'])->toBe(2048);

        return true;
    });
});

it('sends correct system prompt in payload', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'response',
        'A response object',
        [
            'message' => new StringSchema('message', 'The response message'),
        ],
        ['message']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withSystemPrompt('You are a helpful assistant.')
        ->withMessages([new UserMessage('Hello')])
        ->withSchema($schema)
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('system');
        expect($payload['system'])->toBe([
            [
                'type' => 'text',
                'text' => 'You are a helpful assistant.',
            ],
        ]);

        return true;
    });
});

it('sends correct schema in payload using tool mode', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'weather',
        'Weather information',
        [
            'temperature' => new StringSchema('temperature', 'Current temperature'),
            'condition' => new StringSchema('condition', 'Weather condition'),
        ],
        ['temperature', 'condition']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([new UserMessage('What is the weather?')])
        ->withSchema($schema)
        ->withProviderOptions(['use_tool_calling' => true])
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('tools');
        expect($payload['tools'])->toHaveCount(1);
        expect($payload['tools'][0])->toBe([
            'name' => 'output_structured_data',
            'description' => 'Output data in the requested structure',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'temperature' => [
                        'description' => 'Current temperature',
                        'type' => 'string',
                    ],
                    'condition' => [
                        'description' => 'Weather condition',
                        'type' => 'string',
                    ],
                ],
                'required' => ['temperature', 'condition'],
                'additionalProperties' => false,
            ],
        ]);

        expect($payload)->toHaveKey('tool_choice');
        expect($payload['tool_choice'])->toBe([
            'type' => 'tool',
            'name' => 'output_structured_data',
        ]);

        return true;
    });
});

it('sends correct temperature and top_p in payload', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'data',
        'Data object',
        [
            'value' => new StringSchema('value', 'A value'),
        ],
        ['value']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([new UserMessage('Generate data')])
        ->withSchema($schema)
        ->usingTemperature(0.7)
        ->usingTopP(0.9)
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('temperature');
        expect($payload)->toHaveKey('top_p');
        expect($payload['temperature'])->toBe(0.7);
        expect($payload['top_p'])->toBe(0.9);

        return true;
    });
});

it('sends correct max_tokens in payload', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'item',
        'An item',
        [
            'name' => new StringSchema('name', 'Item name'),
        ],
        ['name']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([new UserMessage('Create an item')])
        ->withSchema($schema)
        ->withMaxTokens(1000)
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload['max_tokens'])->toBe(1000);

        return true;
    });
});

it('sends correct thinking mode in payload', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured-with-extending-thinking');

    $schema = new ObjectSchema(
        'calculation',
        'Math calculation result',
        [
            'result' => new StringSchema('result', 'The calculation result'),
        ],
        ['result']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([new UserMessage('Solve this math problem: 2+2')])
        ->withSchema($schema)
        ->withProviderOptions([
            'thinking' => [
                'enabled' => true,
                'budgetTokens' => 2048,
            ],
        ])
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('thinking');
        expect($payload['thinking'])->toBe([
            'type' => 'enabled',
            'budget_tokens' => 2048,
        ]);

        return true;
    });
});

it('sends correct mcp_servers', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'simple',
        'Simple object',
        [
            'data' => new StringSchema('data', 'Some data'),
        ],
        ['data']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([new UserMessage('Generate simple data')])
        ->withSchema($schema)
        ->withProviderOptions([
            'mcp_servers' => [
                [
                    'name' => 'external-mcp',
                    'type' => 'url',
                    'url' => 'https://mcp-server.co/mcp',
                ],
            ],
        ])
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('mcp_servers');
        expect($payload['mcp_servers'])->toBe([
            [
                'name' => 'external-mcp',
                'type' => 'url',
                'url' => 'https://mcp-server.co/mcp',
            ],
        ]);

        return true;
    });
});

it('omits null values from payload', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/structured');

    $schema = new ObjectSchema(
        'simple',
        'Simple object',
        [
            'data' => new StringSchema('data', 'Some data'),
        ],
        ['data']
    );

    Prism::structured()
        ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
        ->withMessages([new UserMessage('Generate simple data')])
        ->withSchema($schema)
        ->asStructured();

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        expect($payload)->not->toHaveKey('system');
        expect($payload)->not->toHaveKey('thinking');
        expect($payload)->not->toHaveKey('temperature');
        expect($payload)->not->toHaveKey('top_p');
        expect($payload)->not->toHaveKey('mcp_servers');

        return true;
    });
});
