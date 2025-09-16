<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Groq\Handlers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Prism\Prism\Audio\AudioResponse;
use Prism\Prism\Audio\SpeechToTextRequest;
use Prism\Prism\Audio\TextResponse;
use Prism\Prism\Audio\TextToSpeechRequest;
use Prism\Prism\Providers\Groq\Concerns\ProcessRateLimits;
use Prism\Prism\Providers\Groq\Maps\TextToSpeechRequestMapper;
use Prism\Prism\ValueObjects\GeneratedAudio;
use Prism\Prism\ValueObjects\Usage;

class Audio
{
    use ProcessRateLimits;

    public function __construct(protected PendingRequest $client) {}

    public function handleTextToSpeech(TextToSpeechRequest $request): AudioResponse
    {
        $mapper = new TextToSpeechRequestMapper($request);

        $response = $this->client->post('audio/speech', $mapper->toPayload());

        if (! $response->successful()) {
            throw new \Exception('Failed to generate audio: '.$response->body());
        }

        $audioContent = $response->body();
        $base64Audio = base64_encode($audioContent);

        return new AudioResponse(
            audio: new GeneratedAudio(
                base64: $base64Audio,
            ),
        );
    }

    public function handleSpeechToText(SpeechToTextRequest $request): TextResponse
    {
        $filename = $this->generateFilename($request->input()->mimeType());

        $response = $this
            ->client
            ->attach(
                'file',
                $request->input()->resource(),
                $filename,
                ['Content-Type' => $request->input()->mimeType()]
            )
            ->post('audio/transcriptions', Arr::whereNotNull([
                'model' => $request->model(),
                'language' => $request->providerOptions('language') ?? null,
                'prompt' => $request->providerOptions('prompt') ?? null,
                'response_format' => $request->providerOptions('response_format') ?? null,
                'temperature' => $request->providerOptions('temperature') ?? null,
            ]));

        if (json_validate($response->body())) {
            $data = $response->json();

            if (! $response->successful()) {
                throw new \Exception('Failed to transcribe audio: '.$response->body());
            }

            return new TextResponse(
                text: $data['text'] ?? '',
                usage: isset($data['usage'])
                    ? new Usage(
                        promptTokens: $data['usage']['prompt_tokens'] ?? 0,
                        completionTokens: $data['usage']['completion_tokens'] ?? 0,
                    )
                    : null,
                additionalContent: $data,
            );
        }

        // Handle other response formats like vtt
        return new TextResponse(
            text: $response->body(),
        );
    }

    protected function generateFilename(?string $mimeType): string
    {
        $extension = match ($mimeType) {
            'audio/flac' => 'flac',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4' => 'mp4',
            'audio/mpga' => 'mpga',
            'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/opus' => 'opus',
            'audio/wav', 'audio/wave' => 'wav',
            'audio/webm' => 'webm',
            default => 'mp3', // Default to mp3 if unknown
        };

        return "audio.{$extension}";
    }
}
