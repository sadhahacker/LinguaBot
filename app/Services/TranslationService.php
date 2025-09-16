<?php

namespace App\Services;



use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class TranslationService
{
    public function translate(array $data, string $targetLang): array
    {
        $models = [
            ['provider' => Provider::Gemini, 'model' => 'gemini-2.0-flash'],
            ['provider' => Provider::Groq, 'model' => 'openai/gpt-oss-120b'],
            ['provider' => Provider::Groq, 'model' => 'llama-3.1-8b-instant'],
        ];

        // Dynamically create StringSchemas for each key
        $properties = [];
        foreach ($data as $key => $value) {
            $properties[] = new StringSchema(
                $key,
                "Translation of '{$key}' into {$targetLang}"
            );
        }

        $schema = new ObjectSchema(
            name: 'translation',
            description: "Translate each field value into {$targetLang}",
            properties: $properties,
            requiredFields: array_keys($data)
        );

        $jsonText = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $allTranslations = [];

        // Run all models
        foreach ($models as $m) {
            $response = Prism::structured()
                ->using($m['provider'], $m['model'])
                ->withSchema($schema)
                ->withPrompt("
                You are a translation assistant.
                Translate the following JSON values into {$targetLang}.
                Keep the keys the same.

                JSON:
                {$jsonText}
            ")
                ->asStructured();

            $allTranslations[] = $response->structured;
        }

        // Cross-verify translations key by key
        $finalTranslation = [];
        foreach ($data as $key => $_) {
            $votes = array_column($allTranslations, $key);
            $count = array_count_values($votes);
            arsort($count); // Most common first
            $finalTranslation[$key] = array_key_first($count);
        }

        return $finalTranslation;
    }

}
