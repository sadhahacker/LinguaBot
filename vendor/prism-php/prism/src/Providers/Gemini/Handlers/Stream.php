<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gemini\Handlers;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Prism\Prism\Concerns\CallsTools;
use Prism\Prism\Enums\ChunkType;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Exceptions\PrismChunkDecodeException;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Providers\Gemini\Maps\FinishReasonMap;
use Prism\Prism\Providers\Gemini\Maps\MessageMap;
use Prism\Prism\Providers\Gemini\Maps\ToolChoiceMap;
use Prism\Prism\Providers\Gemini\Maps\ToolMap;
use Prism\Prism\Text\Chunk;
use Prism\Prism\Text\Request;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\Usage;
use Psr\Http\Message\StreamInterface;
use Throwable;

class Stream
{
    use CallsTools;

    public function __construct(
        protected PendingRequest $client,
        #[\SensitiveParameter] protected string $apiKey,
    ) {}

    /**
     * @return Generator<Chunk>
     */
    public function handle(Request $request): Generator
    {
        $response = $this->sendRequest($request);

        yield from $this->processStream($response, $request);
    }

    /**
     * @return Generator<Chunk>
     */
    protected function processStream(Response $response, Request $request, int $depth = 0): Generator
    {
        // Prevent infinite recursion with tool calls
        if ($depth >= $request->maxSteps()) {
            throw new PrismException('Maximum tool call chain depth exceeded');
        }

        $text = '';
        $toolCalls = [];

        while (! $response->getBody()->eof()) {
            $data = $this->parseNextDataLine($response->getBody());

            // Skip empty data
            if ($data === null) {
                continue;
            }

            // Process tool calls
            if ($this->hasToolCalls($data)) {
                $toolCalls = $this->extractToolCalls($data, $toolCalls);

                // Check if this is the final part of the tool calls
                if ($this->mapFinishReason($data) === FinishReason::ToolCalls) {
                    yield from $this->handleToolCalls($request, $text, $toolCalls, $depth, $data);
                }

                continue;
            }

            // Handle content
            $content = data_get($data, 'candidates.0.content.parts.0.text') ?? '';
            $text .= $content;

            $finishReason = $this->mapFinishReason($data);

            yield new Chunk(
                text: $content,
                finishReason: $finishReason !== FinishReason::Unknown ? $finishReason : null,
                // gemini writes metadata in each chunk
                meta: new Meta(
                    id: data_get($data, 'responseId'),
                    model: data_get($data, 'modelVersion'),
                ),
                usage: $this->extractUsage($data, $request),
            );
        }
    }

    /**
     * @return array<string, mixed>|null Parsed JSON data or null if line should be skipped
     */
    protected function parseNextDataLine(StreamInterface $stream): ?array
    {
        $line = $this->readLine($stream);

        if (! str_starts_with($line, 'data:')) {
            return null;
        }

        $line = trim(substr($line, strlen('data: ')));

        if ($line === '' || $line === '[DONE]') {
            return null;
        }

        try {
            return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new PrismChunkDecodeException('Gemini', $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    protected function extractToolCalls(array $data, array $toolCalls): array
    {
        $parts = data_get($data, 'candidates.0.content.parts', []);

        foreach ($parts as $index => $part) {
            if (isset($part['functionCall'])) {
                $toolCalls[$index]['name'] = data_get($part, 'functionCall.name');
                $toolCalls[$index]['arguments'] = data_get($part, 'functionCall.args', '');
            }
        }

        return $toolCalls;
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @param  array<string, mixed>  $data
     * @return Generator<Chunk>
     */
    protected function handleToolCalls(
        Request $request,
        string $text,
        array $toolCalls,
        int $depth,
        array $data
    ): Generator {
        $toolCalls = $this->mapToolCalls($toolCalls);

        yield new Chunk(
            text: '',
            toolCalls: $toolCalls,
            meta: new Meta(
                id: data_get($data, 'responseId'),
                model: data_get($data, 'modelVersion'),
            ),
            chunkType: ChunkType::ToolCall,
            usage: $this->extractUsage($data, $request),
        );

        $toolResults = $this->callTools($request->tools(), $toolCalls);

        yield new Chunk(
            text: '',
            toolResults: $toolResults,
            meta: new Meta(
                id: data_get($data, 'responseId'),
                model: data_get($data, 'modelVersion'),
            ),
            chunkType: ChunkType::ToolResult,
            usage: $this->extractUsage($data, $request),
        );

        $request->addMessage(new AssistantMessage($text, $toolCalls));
        $request->addMessage(new ToolResultMessage($toolResults));

        $nextResponse = $this->sendRequest($request);
        yield from $this->processStream($nextResponse, $request, $depth + 1);
    }

    /**
     * Convert raw tool call data to ToolCall objects.
     *
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, ToolCall>
     */
    protected function mapToolCalls(array $toolCalls): array
    {
        return collect($toolCalls)
            ->map(fn ($toolCall): ToolCall => new ToolCall(
                empty($toolCall['id']) ? 'gm-'.Str::random(20) : $toolCall['id'],
                data_get($toolCall, 'name'),
                data_get($toolCall, 'arguments'),
            ))
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hasToolCalls(array $data): bool
    {
        $parts = data_get($data, 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractUsage(array $data, Request $request): Usage
    {
        $providerOptions = $request->providerOptions();

        return new Usage(
            promptTokens: isset($providerOptions['cachedContentName'])
                ? (data_get($data, 'usageMetadata.promptTokenCount', 0) - data_get($data, 'usageMetadata.cachedContentTokenCount', 0))
                : data_get($data, 'usageMetadata.promptTokenCount', 0),
            completionTokens: data_get($data, 'usageMetadata.candidatesTokenCount', 0),
            cacheReadInputTokens: data_get($data, 'usageMetadata.cachedContentTokenCount', null),
            thoughtTokens: data_get($data, 'usageMetadata.thoughtsTokenCount', null),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mapFinishReason(array $data): FinishReason
    {
        $finishReason = data_get($data, 'candidates.0.finishReason');

        if (! $finishReason) {
            return FinishReason::Unknown;
        }

        $isToolCall = $this->hasToolCalls($data);

        return FinishReasonMap::map($finishReason, $isToolCall);
    }

    protected function sendRequest(Request $request): Response
    {
        $providerOptions = $request->providerOptions();

        if ($request->tools() !== [] && ($providerOptions['searchGrounding'] ?? false)) {
            throw new PrismException('Use of search grounding with custom tools is not currently supported by Prism.');
        }

        $tools = match (true) {
            $providerOptions['searchGrounding'] ?? false => [
                [
                    'google_search' => (object) [],
                ],
            ],
            $request->tools() !== [] => ['function_declarations' => ToolMap::map($request->tools())],
            default => [],
        };

        return $this->client
            ->withOptions(['stream' => true])
            ->post(
                "{$request->model()}:streamGenerateContent?alt=sse",
                Arr::whereNotNull([
                    ...(new MessageMap($request->messages(), $request->systemPrompts()))(),
                    'cachedContent' => $providerOptions['cachedContentName'] ?? null,
                    'generationConfig' => Arr::whereNotNull([
                        'temperature' => $request->temperature(),
                        'topP' => $request->topP(),
                        'maxOutputTokens' => $request->maxTokens(),
                        'thinkingConfig' => Arr::whereNotNull([
                            'thinkingBudget' => $providerOptions['thinkingBudget'] ?? null,
                        ]) ?: null,
                    ]),
                    'tools' => $tools !== [] ? $tools : null,
                    'tool_config' => $request->toolChoice() ? ToolChoiceMap::map($request->toolChoice()) : null,
                    'safetySettings' => $providerOptions['safetySettings'] ?? null,
                ])
            );
    }

    protected function readLine(StreamInterface $stream): string
    {
        $buffer = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);

            if ($byte === '') {
                return $buffer;
            }

            $buffer .= $byte;

            if ($byte === "\n") {
                break;
            }
        }

        return $buffer;
    }
}
