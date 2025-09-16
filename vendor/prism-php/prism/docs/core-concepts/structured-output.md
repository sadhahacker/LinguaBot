# Structured Output

Want your AI responses as neat and tidy as a Marie Kondo-approved closet? Structured output lets you define exactly how you want your data formatted, making it perfect for building APIs, processing forms, or any time you need data in a specific shape.

## Quick Start

Here's how to get structured data from your AI:

> [!IMPORTANT]
> **Schema Requirement for OpenAI**: When using OpenAI's structured output (especially strict mode), the root schema must be an `ObjectSchema`. Other schema types (StringSchema, NumberSchema, etc.) can only be used as properties within an ObjectSchema, not as the top-level schema. Other providers may have different requirements.

```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema(
    name: 'movie_review',
    description: 'A structured movie review',
    properties: [
        new StringSchema('title', 'The movie title'),
        new StringSchema('rating', 'Rating out of 5 stars'),
        new StringSchema('summary', 'Brief review summary')
    ],
    requiredFields: ['title', 'rating', 'summary']
);

$response = Prism::structured()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withSchema($schema)
    ->withPrompt('Review the movie Inception')
    ->asStructured();

// Access your structured data
$review = $response->structured;
echo $review['title'];    // "Inception"
echo $review['rating'];   // "5 stars"
echo $review['summary'];  // "A mind-bending..."
```

> [!TIP]
> This is just a basic example of schema usage. Check out our [dedicated schemas guide](/core-concepts/schemas) to learn about all available schema types, nullable fields, and best practices for structuring your data.

## Understanding Output Modes

Different AI providers handle structured output in two main ways:

1. **Structured Mode**: Some providers support strict schema validation, ensuring responses perfectly match your defined structure.
2. **JSON Mode**: Other providers simply guarantee valid JSON output that approximately matches your schema.

> [!NOTE]
> Check your provider's documentation to understand which mode they support. Provider support can vary by model, so always verify capabilities for your specific use case.

## Provider-Specific Options

Providers may offer additional options for structured output:

### OpenAI: Strict Mode
OpenAI supports a "strict mode" for even tighter schema validation:

```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

$response = Prism::structured()
    ->withProviderOptions([
        'schema' => [
            'strict' => true
        ]
    ])
    // ... rest of your configuration
```

### Anthropic: Tool Calling Mode
Anthropic doesn't have native structured output, but Prism provides two approaches. For more reliable JSON parsing, especially with complex content or non-English text, use tool calling mode:

```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

$response = Prism::structured()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withSchema($schema)
    ->withPrompt('天氣怎麼樣？應該穿什麼？') // Chinese text with potential quotes
    ->withProviderOptions(['use_tool_calling' => true])
    ->asStructured();
```

**When to use tool calling mode with Anthropic:**
- Working with non-English content that may contain quotes
- Complex JSON structures that might confuse prompt-based parsing
- When you need the most reliable structured output possible

> [!NOTE]
> Tool calling mode cannot be used with Anthropic's citations feature.

> [!TIP]
> Check the provider-specific documentation pages for additional options and features that might be available for structured output.

## Response Handling

When working with structured responses, you have access to both the structured data and metadata about the generation:

```php
use Prism\Prism\Prism;

$response = Prism::structured()
    ->withSchema($schema)
    ->asStructured();

// Access the structured data as a PHP array
$data = $response->structured;

// Get the raw response text if needed
echo $response->text;

// Check why the generation stopped
echo $response->finishReason->name;

// Get token usage statistics
echo "Prompt tokens: {$response->usage->promptTokens}";
echo "Completion tokens: {$response->usage->completionTokens}";

// Access provider-specific response data
$rawResponse = $response->response;
```

> [!TIP]
> Always validate the structured data before using it in your application:
```php
if ($response->structured === null) {
    // Handle parsing failure
}

if (!isset($response->structured['required_field'])) {
    // Handle missing required data
}
```

## Common Settings

Structured output supports several configuration options to fine-tune your generations:

### Model Configuration
- `maxTokens` - Set the maximum number of tokens to generate
- `temperature` - Control output randomness (provider-dependent)
- `topP` - Alternative to temperature for controlling randomness (provider-dependent)

### Input Methods
- `withPrompt` - Single prompt for generation
- `withMessages` - Message history for more context
- `withSystemPrompt` - System-level instructions

### Request Configuration 
- `withClientOptions` - Set HTTP client options (e.g., timeouts)
- `withClientRetry` - Configure automatic retries on failures
- `usingProviderConfig` - Override provider configuration
- `withProviderOptions` - Set provider-specific options

> [!NOTE]
> Unlike text generation, structured output does not support tools/function calling. For those features, use the text generation API instead.

See the [Text Generation](./text-generation.md) documentation for comparison with standard text generation capabilities.

## Error Handling

When working with structured output, it's especially important to handle potential errors:

```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;

try {
    $response = Prism::structured()
        ->using('anthropic', 'claude-3-sonnet')
        ->withSchema($schema)
        ->withPrompt('Generate product data')
        ->asStructured();
} catch (PrismException $e) {
    // Handle validation or generation errors
    Log::error('Structured generation failed:', [
        'error' => $e->getMessage()
    ]);
}
```

> [!IMPORTANT]
> Always validate the structured response before using it in your application, as different providers may have varying levels of schema adherence.
