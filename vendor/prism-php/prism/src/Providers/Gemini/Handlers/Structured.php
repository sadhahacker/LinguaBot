<?php

namespace Prism\Prism\Providers\Gemini\Handlers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Providers\Gemini\Concerns\ValidatesResponse;
use Prism\Prism\Providers\Gemini\Maps\FinishReasonMap;
use Prism\Prism\Providers\Gemini\Maps\MessageMap;
use Prism\Prism\Providers\Gemini\Maps\SchemaMap;
use Prism\Prism\Structured\Request;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Structured\ResponseBuilder;
use Prism\Prism\Structured\Step;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

class Structured
{
    use ValidatesResponse;

    protected ResponseBuilder $responseBuilder;

    public function __construct(protected PendingRequest $client)
    {
        $this->responseBuilder = new ResponseBuilder;
    }

    public function handle(Request $request): StructuredResponse
    {
        $data = $this->sendRequest($request);

        $this->validateResponse($data);

        $responseMessage = new AssistantMessage(data_get($data, 'candidates.0.content.parts.0.text') ?? '');

        $request->addMessage($responseMessage);

        $this->addStep($data, $request);

        return $this->responseBuilder->toResponse();
    }

    /**
     * @return array<string, mixed>
     */
    public function sendRequest(Request $request): array
    {
        $providerOptions = $request->providerOptions();

        $response = $this->client->post(
            "{$request->model()}:generateContent",
            Arr::whereNotNull([
                ...(new MessageMap($request->messages(), $request->systemPrompts()))(),
                'cachedContent' => $providerOptions['cachedContentName'] ?? null,
                'generationConfig' => Arr::whereNotNull([
                    'response_mime_type' => 'application/json',
                    'response_schema' => (new SchemaMap($request->schema()))->toArray(),
                    'temperature' => $request->temperature(),
                    'topP' => $request->topP(),
                    'maxOutputTokens' => $request->maxTokens(),
                    'thinkingConfig' => Arr::whereNotNull([
                        'thinkingBudget' => $providerOptions['thinkingBudget'] ?? null,
                    ]) ?: null,
                ]),
                'safetySettings' => $providerOptions['safetySettings'] ?? null,
            ])
        );

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateResponse(array $data): void
    {
        if (! $data || data_get($data, 'error')) {
            throw PrismException::providerResponseError(vsprintf(
                'Gemini Error: [%s] %s',
                [
                    data_get($data, 'error.code', 'unknown'),
                    data_get($data, 'error.message', 'unknown'),
                ]
            ));
        }

        // Check for token exhaustion patterns
        $finishReason = data_get($data, 'candidates.0.finishReason');
        $content = data_get($data, 'candidates.0.content.parts.0.text', '');
        $thoughtTokens = data_get($data, 'usageMetadata.thoughtsTokenCount', 0);

        if ($finishReason === 'MAX_TOKENS') {
            $promptTokens = data_get($data, 'usageMetadata.promptTokenCount', 0);
            $candidatesTokens = data_get($data, 'usageMetadata.candidatesTokenCount', 0);
            $totalTokens = data_get($data, 'usageMetadata.totalTokenCount', 0);
            $outputTokens = $candidatesTokens - $thoughtTokens;

            // Check if content is empty or likely truncated/invalid JSON
            $isEmpty = in_array(trim((string) $content), ['', '0'], true);
            $isInvalidJson = ! empty($content) && json_decode((string) $content) === null;
            $contentLength = strlen((string) $content);

            if (($isEmpty || $isInvalidJson) && $thoughtTokens > 0) {
                $errorDetail = $isEmpty
                    ? 'no tokens remained for structured output'
                    : "output was truncated at {$contentLength} characters resulting in invalid JSON";

                throw PrismException::providerResponseError(
                    'Gemini hit token limit with high thinking token usage. '.
                    "Token usage: {$promptTokens} prompt + {$thoughtTokens} thinking + {$outputTokens} output = {$totalTokens} total. ".
                    "The {$errorDetail}. ".
                    'Try increasing maxTokens to at least '.($totalTokens + 1000).' (suggested: '.($totalTokens * 2).' for comfortable margin).'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function addStep(array $data, Request $request): void
    {
        $this->responseBuilder->addStep(
            new Step(
                text: data_get($data, 'candidates.0.content.parts.0.text') ?? '',
                finishReason: FinishReasonMap::map(
                    data_get($data, 'candidates.0.finishReason'),
                ),
                usage: new Usage(
                    promptTokens: data_get($data, 'usageMetadata.promptTokenCount', 0),
                    completionTokens: data_get($data, 'usageMetadata.candidatesTokenCount', 0),
                    cacheReadInputTokens: data_get($data, 'usageMetadata.cachedContentTokenCount', null),
                    thoughtTokens: data_get($data, 'usageMetadata.thoughtsTokenCount', null),
                ),
                meta: new Meta(
                    id: data_get($data, 'id', ''),
                    model: data_get($data, 'modelVersion'),
                ),
                messages: $request->messages(),
                systemPrompts: $request->systemPrompts(),
            )
        );
    }
}
