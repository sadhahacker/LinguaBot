<?php

declare(strict_types=1);

namespace Tests\Providers\Anthropic;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Citations\CitationSourcePositionType;
use Prism\Prism\Enums\Citations\CitationSourceType;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Prism;
use Prism\Prism\Providers\Anthropic\Handlers\Text;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\MessagePartWithCitations;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ProviderRateLimit;
use Prism\Prism\ValueObjects\ProviderTool;
use Tests\Fixtures\FixtureResponse;

beforeEach(function (): void {
    config()->set('prism.providers.anthropic.api_key', env('ANTHROPIC_API_KEY', 'sk-1234'));
});

it('can generate text with a prompt', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-a-prompt');

    $response = Prism::text()
        ->using('anthropic', 'claude-3-5-sonnet-20240620')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->usage->promptTokens)->toBe(11);
    expect($response->usage->completionTokens)->toBe(55);
    expect($response->usage->cacheWriteInputTokens)->toBeNull();
    expect($response->usage->cacheReadInputTokens)->toBeNull();
    expect($response->meta->id)->toBe('msg_01X2Qk7LtNEh4HB9xpYU57XU');
    expect($response->meta->model)->toBe('claude-3-5-sonnet-20240620');
    expect($response->text)->toBe(
        "I am an AI assistant created by Anthropic to be helpful, harmless, and honest. I don't have a physical form or avatar - I'm a language model trained to engage in conversation and help with tasks. How can I assist you today?"
    );
});

it('can generate text with a system prompt', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-system-prompt');

    $response = Prism::text()
        ->using('anthropic', 'claude-3-5-sonnet-20240620')
        ->withSystemPrompt('MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]!')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->usage->promptTokens)->toBe(33);
    expect($response->usage->completionTokens)->toBe(98);
    expect($response->meta->id)->toBe('msg_016EjDAMDeSvG229ZjspjC7J');
    expect($response->meta->model)->toBe('claude-3-5-sonnet-20240620');
    expect($response->text)->toBe(
        'I am Nyx, an ancient and unfathomable entity from the depths of cosmic darkness. My form is beyond mortal comprehension - a writhing mass of tentacles and eyes that would shatter the sanity of those who gaze upon me. I exist beyond the boundaries of time and space as you know them. My knowledge spans eons and transcends human understanding. What brings you to seek audience with one such as I, tiny mortal?'
    );
});

describe('tools', function (): void {
    it('can generate text using multiple tools and multiple steps', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-multiple-tools');

        $tools = [
            Tool::as('weather')
                ->for('useful when you need to search for current weather conditions')
                ->withStringParameter('city', 'the city you want the weather for')
                ->using(fn (string $city): string => 'The weather will be 75° and sunny'),
            Tool::as('search')
                ->for('useful for searching curret events or data')
                ->withStringParameter('query', 'The detailed search query')
                ->using(fn (string $query): string => 'The tigers game is at 3pm in detroit'),
        ];

        $response = Prism::text()
            ->using('anthropic', 'claude-3-5-sonnet-20240620')
            ->withTools($tools)
            ->withMaxSteps(3)
            ->withPrompt('What time is the tigers game today and should I wear a coat?')
            ->asText();

        // Assert tool calls in the first step
        $firstStep = $response->steps[0];
        expect($firstStep->toolCalls)->toHaveCount(1);
        expect($firstStep->toolCalls[0]->name)->toBe('search');
        expect($firstStep->toolCalls[0]->arguments())->toBe([
            'query' => 'Detroit Tigers baseball game time today',
        ]);

        // Assert tool calls in the second step
        $secondStep = $response->steps[1];
        expect($secondStep->toolCalls)->toHaveCount(1);
        expect($secondStep->toolCalls[0]->name)->toBe('weather');
        expect($secondStep->toolCalls[0]->arguments())->toBe([
            'city' => 'Detroit',
        ]);

        // Assert usage
        expect($response->usage->promptTokens)->toBe(1650);
        expect($response->usage->completionTokens)->toBe(307);

        // Assert response
        expect($response->meta->id)->toBe('msg_011fBqNVVh5AwC3uyiq78qrj');
        expect($response->meta->model)->toBe('claude-3-5-sonnet-20240620');

        // Assert final text content
        expect($response->text)->toContain('The Tigers game is scheduled for 3:00 PM today in Detroit');
        expect($response->text)->toContain('it will be 75°F (about 24°C) and sunny');
        expect($response->text)->toContain("you likely won't need a coat");
    });

    it('it handles a provider tool', function (): void {
        config()->set('prism.providers.anthropic.anthropic_beta', 'code-execution-2025-05-22');

        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-provider-tool');

        $response = Prism::text()
            ->using('anthropic', 'claude-3-5-haiku-latest')
            ->withPrompt('Solve the equation 3x + 10 = 14.')
            ->withProviderTools([new ProviderTool(type: 'code_execution_20250522', name: 'code_execution')])
            ->asText();

        expect($response->text)->toContain('4/3');
    });

    it('handles a provider tool with a user defined tool', function (): void {
        config()->set('prism.providers.anthropic.anthropic_beta', 'code-execution-2025-05-22');

        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-provider-tool-and-user-tool');

        $tools = [
            Tool::as('weather')
                ->for('useful when you need to search for current weather conditions')
                ->withStringParameter('city', 'The city that you want the weather for')
                ->using(fn (string $city): string => 'The weather will be 75° and sunny'),
            Tool::as('search')
                ->for('useful for searching curret events or data')
                ->withStringParameter('query', 'The detailed search query')
                ->using(fn (string $query): string => 'The tigers game is at 3pm in detroit'),
        ];

        $response = Prism::text()
            ->using('anthropic', 'claude-3-5-haiku-latest')
            ->withPrompt('If the current temperature in Detroit is X, what is Y in the following equation: 3x + 10 = Y?')
            ->withTools($tools)
            ->withProviderTools([new ProviderTool(type: 'code_execution_20250522', name: 'code_execution')])
            ->withMaxSteps(3)
            ->asText();

        expect($response->text)->toContain('235');
    });

    it('handles specific tool choice', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-required-tool-call');

        $tools = [
            Tool::as('weather')
                ->for('useful when you need to search for current weather conditions')
                ->withStringParameter('city', 'The city that you want the weather for')
                ->using(fn (string $city): string => 'The weather will be 75° and sunny'),
            Tool::as('search')
                ->for('useful for searching curret events or data')
                ->withStringParameter('query', 'The detailed search query')
                ->using(fn (string $query): string => 'The tigers game is at 3pm in detroit'),
        ];

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withPrompt('Do something')
            ->withTools($tools)
            ->withToolChoice('weather')
            ->asText();

        expect($response->toolCalls[0]->name)->toBe('weather');
    });
});

it('can calculate cache usage correctly', function (): void {
    FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/calculate-cache-usage');

    $response = Prism::text()
        ->using('anthropic', 'claude-3-5-sonnet-20240620')
        ->withMessages([
            (new UserMessage('New context'))->withProviderOptions(['cacheType' => 'ephemeral']),
        ])
        ->asText();

    expect($response->usage->cacheWriteInputTokens)->toBe(200);
    expect($response->usage->cacheReadInputTokens)->ToBe(100);
});

it('adds rate limit data to the responseMeta', function (): void {
    $requests_reset = Carbon::now()->addSeconds(30);

    FixtureResponse::fakeResponseSequence(
        'v1/messages',
        'anthropic/generate-text-with-a-prompt',
        [
            'anthropic-ratelimit-requests-limit' => 1000,
            'anthropic-ratelimit-requests-remaining' => 500,
            'anthropic-ratelimit-requests-reset' => $requests_reset->toISOString(),
            'anthropic-ratelimit-input-tokens-limit' => 80000,
            'anthropic-ratelimit-input-tokens-remaining' => 0,
            'anthropic-ratelimit-input-tokens-reset' => Carbon::now()->addSeconds(60)->toISOString(),
            'anthropic-ratelimit-output-tokens-limit' => 16000,
            'anthropic-ratelimit-output-tokens-remaining' => 15000,
            'anthropic-ratelimit-output-tokens-reset' => Carbon::now()->addSeconds(5)->toISOString(),
            'anthropic-ratelimit-tokens-limit' => 96000,
            'anthropic-ratelimit-tokens-remaining' => 15000,
            'anthropic-ratelimit-tokens-reset' => Carbon::now()->addSeconds(5)->toISOString(),
        ]
    );

    $response = Prism::text()
        ->using('anthropic', 'claude-3-5-sonnet-20240620')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->meta->rateLimits)->toHaveCount(4);
    expect($response->meta->rateLimits[0])->toBeInstanceOf(ProviderRateLimit::class);
    expect($response->meta->rateLimits[0]->name)->toEqual('requests');
    expect($response->meta->rateLimits[0]->limit)->toEqual(1000);
    expect($response->meta->rateLimits[0]->remaining)->toEqual(500);
    expect($response->meta->rateLimits[0]->resetsAt)->toEqual($requests_reset);
});

describe('Anthropic citations', function (): void {
    it('applies the citations request level providerOptions to all documents', function (): void {
        Prism::fake();

        $request = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMessages([
                (new UserMessage(
                    content: 'What color is the grass and sky?',
                    additionalContent: [
                        Document::fromText('The grass is green. The sky is blue.'),
                    ]
                )),
            ])
            ->withProviderOptions(['citations' => true]);

        $payload = Text::buildHttpRequestPayload($request->toRequest());

        expect($payload['messages'])->toBe([[
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'What color is the grass and sky?',
                ],
                [
                    'type' => 'document',
                    'citations' => ['enabled' => true],
                    'source' => [
                        'type' => 'text',
                        'media_type' => 'text/plain',
                        'data' => 'The grass is green. The sky is blue.',
                    ],
                ],
            ],
        ]]);
    });

    it('adds citations to additionalContent on response steps and assistant message for PDF documents', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-pdf-citations');

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMessages([
                (new UserMessage(
                    content: 'What color is the grass and sky?',
                    additionalContent: [
                        Document::fromLocalPath('tests/Fixtures/test-pdf.pdf'),
                    ]
                )),
            ])
            ->withProviderOptions(['citations' => true])
            ->asText();

        expect($response->text)->toEqual('According to the text, the grass is green and the sky is blue.');

        expect($response->additionalContent['citations'])->toHaveCount(5);
        expect($response->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        /** @var MessagePartWithCitations */
        $messagePart = $response->additionalContent['citations'][1];

        expect($messagePart->outputText)->toBe('the grass is green');
        expect($messagePart->citations)->toHaveCount(1);
        expect($messagePart->citations[0]->sourceType)->toBe(CitationSourceType::Document);
        expect($messagePart->citations[0]->sourceText)->toBe('The grass is green. ');
        expect($messagePart->citations[0]->sourceStartIndex)->toBe(1);
        expect($messagePart->citations[0]->sourceEndIndex)->toBe(2);
        expect($messagePart->citations[0]->source)->toBe(0);
        expect($messagePart->citations[0]->sourceTitle)->toBe('All aboout the grass and the sky');
        expect($messagePart->citations[0]->sourcePositionType)->toBe(CitationSourcePositionType::Page);

        expect($response->steps[0]->additionalContent['citations'])->toHaveCount(5);
        expect($response->steps[0]->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        expect($response->messages->last()->additionalContent['citations'])->toHaveCount(5);
        expect($response->messages->last()->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);
    });

    it('adds citations to additionalContent on response steps and assistant message for text documents', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-text-document-citations');

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMessages([
                (new UserMessage(
                    content: 'What color is the grass and sky?',
                    additionalContent: [
                        Document::fromText('The grass is green. The sky is blue.'),
                    ]
                )),
            ])
            ->withProviderOptions(['citations' => true])
            ->asText();

        expect($response->text)->toBe("According to the documents:\nThe grass is green and the sky is blue.");

        expect($response->additionalContent['citations'])->toHaveCount(5);
        expect($response->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        /** @var MessagePartWithCitations */
        $messagePart = $response->additionalContent['citations'][1];

        expect($messagePart->outputText)->toBe('The grass is green');
        expect($messagePart->citations)->toHaveCount(1);
        expect($messagePart->citations[0]->sourceType)->toBe(CitationSourceType::Document);
        expect($messagePart->citations[0]->sourceText)->toBe('The grass is green. ');
        expect($messagePart->citations[0]->sourceStartIndex)->toBe(0);
        expect($messagePart->citations[0]->sourceEndIndex)->toBe(20);
        expect($messagePart->citations[0]->source)->toBe(0);
        expect($messagePart->citations[0]->sourceTitle)->toBe('All aboout the grass and the sky');
        expect($messagePart->citations[0]->sourcePositionType)->toBe(CitationSourcePositionType::Character);

        expect($response->steps[0]->additionalContent['citations'])->toHaveCount(5);
        expect($response->steps[0]->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        expect($response->messages->last()->additionalContent['citations'])->toHaveCount(5);
        expect($response->messages->last()->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);
    });

    it('adds citations to additionalContent on response steps and assistant message for custom content documents', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-custom-content-document-citations');

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMessages([
                (new UserMessage(
                    content: 'What color is the grass and sky?',
                    additionalContent: [
                        Document::fromChunks(['The grass is green.', 'The sky is blue.']),
                    ]
                )),
            ])
            ->withProviderOptions(['citations' => true])
            ->asText();

        expect($response->text)->toBe('According to the documents, the grass is green and the sky is blue.');

        expect($response->additionalContent['citations'])->toHaveCount(5);
        expect($response->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        /** @var MessagePartWithCitations */
        $messagePart = $response->additionalContent['citations'][1];

        expect($messagePart->outputText)->toBe('the grass is green');
        expect($messagePart->citations)->toHaveCount(1);
        expect($messagePart->citations[0]->sourceType)->toBe(CitationSourceType::Document);
        expect($messagePart->citations[0]->sourceText)->toBe('The grass is green.');
        expect($messagePart->citations[0]->sourceStartIndex)->toBe(0);
        expect($messagePart->citations[0]->sourceEndIndex)->toBe(1);
        expect($messagePart->citations[0]->source)->toBe(0);
        expect($messagePart->citations[0]->sourceTitle)->toBeNull();
        expect($messagePart->citations[0]->sourcePositionType)->toBe(CitationSourcePositionType::Chunk);

        expect($response->steps[0]->additionalContent['citations'])->toHaveCount(5);
        expect($response->steps[0]->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        expect($response->messages->last()->additionalContent['citations'])->toHaveCount(5);
        expect($response->messages->last()->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);
    });

    it('adds citations to additionalContent on response steps and assistant message for the web search tool', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-web-search-citations');

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-haiku-latest')
            ->withPrompt('What is the weather going to be like in London today?')
            ->withProviderTools([new ProviderTool(type: 'web_search_20250305', name: 'web_search')])
            ->asText();

        $citationChunk = Arr::first(
            $response->additionalContent['citations'],
            fn (MessagePartWithCitations $part): bool => $part->citations !== [] && $part->citations[0]->sourceType === CitationSourceType::Url
        );

        expect($citationChunk->outputText)->toContain('temperatures');
        expect($citationChunk->citations)->toHaveCount(1);
        expect($citationChunk->citations[0]->sourceType)->toBe(CitationSourceType::Url);
        expect($citationChunk->citations[0]->sourceText)->toContain('temperatures');
        expect($citationChunk->citations[0]->sourceTitle)->toContain('Weather');
        expect($citationChunk->citations[0]->source)->toBe('https://www.easeweather.com/europe/united-kingdom/england/greater-london/london/july');

        expect($response->steps[0]->additionalContent['citations'])->toHaveCount(13);
        expect($response->steps[0]->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);

        expect($response->messages->last()->additionalContent['citations'])->toHaveCount(13);
        expect($response->messages->last()->additionalContent['citations'][0])->toBeInstanceOf(MessagePartWithCitations::class);
    });

    it('can handle citations on on a previous assistant message with a document', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/generate-text-with-text-document-citations-on-followup');

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMessages([
                (new UserMessage(
                    content: 'What color is the grass and sky?',
                    additionalContent: [
                        Document::fromText('The grass is green. The sky is blue.', 'All aboout the grass and the sky'),
                    ]
                )),
            ])
            ->withProviderOptions(['citations' => true])
            ->asText();

        $responseTwo = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMessages([
                ...$response->steps->last()->messages,
                new UserMessage('Is the source you have cited reliable?'),
            ])
            ->asText();

        expect($responseTwo->text)->toContain('spelling error');
    });
});

describe('Anthropic extended thinking', function (): void {
    it('can use extending thinking', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/text-with-extending-thinking');

        $response = Prism::text()
            ->using('anthropic', 'claude-3-7-sonnet-latest')
            ->withPrompt('What is the meaning of life, the universe and everything in popular fiction?')
            ->withProviderOptions(['thinking' => ['enabled' => true]])
            ->asText();

        $expected_thinking = "This is a reference to Douglas Adams' popular science fiction series \"The Hitchhiker's Guide to the Galaxy\" where the supercomputer Deep Thought was built to calculate \"the Answer to the Ultimate Question of Life, the Universe, and Everything.\" After 7.5 million years of computation, it famously determined the answer to be \"42\" - a deliberately anticlimactic and absurd response that has become a significant pop culture reference.\n\nBeyond the Hitchhiker's reference, the question of life's meaning appears in many works of fiction across different media, with various philosophical approaches.\n\nI should note this humorous 42 reference while also mentioning how other fictional works have approached this philosophical question.";
        $expected_signature = 'EuYBCkQYAiJAQ7ZOmBu5pa8U03x/RN5+Gs3tyKXFYcruUfnC8X/4AKBpJmB8qX+nQQ9atvYOXLD/mUAClCRZEaxt2fyEvdxnhRIMfFi6CLULECysli0mGgy5JRaOXL06fVJndm8iMD2T+D8dSIFJuctCnVeFKZme2TfIPIH+UMFO33a0ojzUq2VYy8+RzKkH7WYK9+580ipQ4yDVegd/67LKRtfb574HOHqwlPcfEbeiJuFuHrayoqK8KS2ltGYRckVGH6lNH46zUyjGaD2z3nZeti8UjmgnfMWRpjUmv0TWWGtrCKRoHGQ=';

        expect($response->text)->toBe("In popular fiction, the most famous answer to this question comes from Douglas Adams' \"The Hitchhiker's Guide to the Galaxy,\" where a supercomputer named Deep Thought calculates for 7.5 million years and determines that the answer is simply \"42.\" This deliberately absurd response has become an iconic joke about the futility of seeking simple answers to profound existential questions.\n\nBeyond this humorous reference, fiction explores life's meaning in countless ways:\n- Finding purpose through love and human connection (seen in works like \"The Good Place\")\n- The pursuit of knowledge and understanding (as in \"Contact\" by Carl Sagan)\n- Creating your own meaning in an indifferent universe (explored in existentialist fiction)\n- Religious or spiritual fulfillment (depicted in works like \"Life of Pi\")\n\nWhat makes this question compelling in fiction is that there's never a definitive answer - just different perspectives that reflect our own search for meaning.");
        expect($response->additionalContent['thinking'])->toBe($expected_thinking);
        expect($response->additionalContent['thinking_signature'])->toBe($expected_signature);

        expect($response->steps->last()->messages[1])
            ->additionalContent->thinking->toBe($expected_thinking)
            ->additionalContent->thinking_signature->toBe($expected_signature);
    });

    it('can override budget tokens', function (): void {
        $response = Prism::text()
            ->using('anthropic', 'claude-3-7-sonnet-latest')
            ->withPrompt('What is the meaning of life, the universe and everything in popular fiction?')
            ->withProviderOptions([
                'thinking' => [
                    'enabled' => true,
                    'budgetTokens' => 2048,
                ],
            ]);

        $payload = Text::buildHttpRequestPayload($response->toRequest());

        expect(data_get($payload, 'thinking.budget_tokens'))->toBe(2048);
    });

    it('can use extending thinking with tool calls', function (): void {
        FixtureResponse::fakeResponseSequence('v1/messages', 'anthropic/text-with-extending-thinking-and-tool-calls');

        $tools = [
            Tool::as('weather')
                ->for('useful when you need to search for current weather conditions')
                ->withStringParameter('city', 'the city you want the weather for')
                ->using(fn (string $city): string => 'The weather will be 75° and sunny'),
            Tool::as('search')
                ->for('useful for searching curret events or data')
                ->withStringParameter('query', 'The detailed search query')
                ->using(fn (string $query): string => 'The tigers game is at 3pm in detroit'),
        ];

        $response = Prism::text()
            ->using('anthropic', 'claude-3-7-sonnet-latest')
            ->withTools($tools)
            ->withMaxSteps(3)
            ->withPrompt('What time is the tigers game today and should I wear a coat?')
            ->withProviderOptions(['thinking' => ['enabled' => true]])
            ->asText();

        $expected_thinking = "The user is asking about:\n1. The time of the Tigers game today (likely referring to a sports team, probably Detroit Tigers baseball)\n2. Whether they should wear a coat (which relates to weather conditions)\n\nFor the first question, I need to search for the Tigers game schedule for today. For the second question, I need to check the weather in the relevant location.\n\nHowever, I'm missing some information:\n- The user hasn't specified which Tigers team they're referring to (though Detroit Tigers is most likely)\n- The user hasn't specified their location, which I need for the weather check\n\nI'll need to search for the Tigers game information first, and then check the weather in the appropriate location (likely Detroit if it's a home game).";
        $expected_signature = 'EuYBCkQYAiJAY1corUurDaKsURSV32GUvrp4ZySJDYJXGHIBx2aPaphiKr+Kcenv2gTcLxAvkU5zUxek2mX3GGkrp8XlN2qJAhIM7v4WGU9Wwfpn8qu1Ggzd9cK0sZX2z6qEbaciMKAfMsaYMc9zVHF1Y2qY+iC35WGiXAnEAZk+KBNGCo0V+t/U1bzJGhAigvTRKkDKpipQDXkfw+XdPzHh+VGFXut2TIPatMN5UrE1CvR+GtQT1cscbxBnuiXFwgs3B/QPlC2/l2VloajCHeYVaHqY3MIXiTyqe4HAyt51Go1Xt1ydVaY=';

        expect($response->text)->toBe("The Detroit Tigers game is today at 3pm in Detroit. The weather in Detroit will be 75° and sunny, so you likely won't need a coat. It's a warm, pleasant day - just a light jacket or sweater might be enough if you tend to get cold at outdoor events, but generally, these are comfortable conditions.");

        expect($response->steps->first())
            ->additionalContent->thinking->toBe($expected_thinking)
            ->additionalContent->thinking_signature->toBe($expected_signature);

        expect($response->steps->first()->messages[1])
            ->additionalContent->thinking->toBe($expected_thinking)
            ->additionalContent->thinking_signature->toBe($expected_signature);
    });
});

describe('exceptions', function (): void {
    it('throws a RateLimitException if the Anthropic responds with a 429', function (): void {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response(
                status: 429,
            ),
        ])->preventStrayRequests();

        Prism::text()
            ->using('anthropic', 'claude-3-5-sonnet-20240620')
            ->withPrompt('Hello world!')
            ->asText();

    })->throws(PrismRateLimitedException::class);

    it('sets the correct data on the RateLimitException', function (): void {
        $requests_reset = Carbon::now()->addSeconds(30);

        Http::fake([
            'https://api.anthropic.com/*' => Http::response(
                status: 429,
                headers: [
                    'anthropic-ratelimit-requests-limit' => 1000,
                    'anthropic-ratelimit-requests-remaining' => 500,
                    'anthropic-ratelimit-requests-reset' => $requests_reset->toISOString(),
                    'anthropic-ratelimit-input-tokens-limit' => 80000,
                    'anthropic-ratelimit-input-tokens-remaining' => 0,
                    'anthropic-ratelimit-input-tokens-reset' => Carbon::now()->addSeconds(60)->toISOString(),
                    'anthropic-ratelimit-output-tokens-limit' => 16000,
                    'anthropic-ratelimit-output-tokens-remaining' => 15000,
                    'anthropic-ratelimit-output-tokens-reset' => Carbon::now()->addSeconds(5)->toISOString(),
                    'anthropic-ratelimit-tokens-limit' => 96000,
                    'anthropic-ratelimit-tokens-remaining' => 15000,
                    'anthropic-ratelimit-tokens-reset' => Carbon::now()->addSeconds(5)->toISOString(),
                    'retry-after' => 40,
                ]
            ),
        ])->preventStrayRequests();

        try {
            Prism::text()
                ->using('anthropic', 'claude-3-5-sonnet-20240620')
                ->withPrompt('Hello world!')
                ->asText();
        } catch (PrismRateLimitedException $e) {
            expect($e->retryAfter)->toEqual(40);
            expect($e->rateLimits)->toHaveCount(4);
            expect($e->rateLimits[0])->toBeInstanceOf(ProviderRateLimit::class);
            expect($e->rateLimits[0]->name)->toEqual('requests');
            expect($e->rateLimits[0]->limit)->toEqual(1000);
            expect($e->rateLimits[0]->remaining)->toEqual(500);
            expect($e->rateLimits[0]->resetsAt)->toEqual($requests_reset);

            expect($e->rateLimits[1]->name)->toEqual('input-tokens');
            expect($e->rateLimits[1]->limit)->toEqual(80000);
            expect($e->rateLimits[1]->remaining)->toEqual(0);
        }
    });

    it('throws an overloaded exception if the Anthropic responds with a 529', function (): void {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response(
                status: 529,
            ),
        ])->preventStrayRequests();

        Prism::text()
            ->using('anthropic', 'claude-3-5-sonnet-20240620')
            ->withPrompt('Hello world!')
            ->asText();

    })->throws(PrismProviderOverloadedException::class);

    it('throws a request too large exception if the Anthropic responds with a 413', function (): void {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response(
                status: 413,
            ),
        ])->preventStrayRequests();

        Prism::text()
            ->using('anthropic', 'claude-3-5-sonnet-20240620')
            ->withPrompt('Hello world!')
            ->asText();

    })->throws(PrismRequestTooLargeException::class);
});
