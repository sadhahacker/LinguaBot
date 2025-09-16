# Gemini

## Configuration

```php
'gemini' => [
    'api_key' => env('GEMINI_API_KEY', ''),
    'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
],
```

## Search grounding

Google Gemini offers built-in search grounding capabilities that allow your AI to search the web for real-time information. This is a provider tool that uses Google's search infrastructure. For more information about the difference between custom tools and provider tools, see [Tools & Function Calling](/core-concepts/tools-function-calling#provider-tools).

You may enable Google search grounding on text requests using withProviderTools:

```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\ProviderTool;

$response = Prism::text()
    ->using(Provider::Gemini, 'gemini-2.0-flash')
    ->withPrompt('What is the stock price of Google right now?')
    // Enable search grounding
    ->withProviderTools([
            new ProviderTool('google_search')
        ])
    ->asText();
```

If you use search groundings, Google require you meet certain [display requirements](https://ai.google.dev/gemini-api/docs/grounding/search-suggestions).

The data you need to meet these display requirements, and to build e.g. footnote functionality will be saved to the response's `additionalContent` property.

```php
// The Google supplied and styled widget to click through to results.
$response->additionalContent['searchEntryPoint'];

// The search queries made by the model
$response->additionalContent['searchQueries'];

// The citations data is available as an array of MessagePartWithCitations
$response->additionalContent['citations'];
```

`citations` is an array of `MessagePartWithCitations`, which you can use to build up footnotes as follows:

```php
use Prism\Prism\ValueObjects\MessagePartWithCitations;
use Prism\Prism\ValueObjects\Citation;

$text = '';
$footnotes = [];

$footnoteId = 1;

/** @var MessagePartWithCitations $part */
foreach ($response->additionalContent['citations'] as $part) {
    $text .= $part->outputText;
    
    /** @var Citation $citation */
    foreach ($part->citations as $citation) {
        $footnotes[] = [
            'id' => $footnoteId,
            'title' => $citation->sourceTitle,
            'uri' => $citation->source,
        ];

        $text .= '<sup><a href="#footnote-'.$footnoteId.'">'.$footnoteId.'</a></sup>';

        $footnoteId++;
    }
}

// Pass $text and $footnotes to your frontend.
```

## Caching

Prism supports Gemini prompt caching, though due to Gemini requiring you first upload the cached content, it works a little differently to other providers.

To store content in the cache, use the Gemini provider cache method as follows:

```php

use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Providers\Gemini\Gemini;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/** @var Gemini */
$provider = Prism::provider(Provider::Gemini);

$object = $provider->cache(
    model: 'gemini-1.5-flash-002',
    messages: [
        new UserMessage('', [
            Document::fromLocalPath('tests/Fixtures/long-document.pdf'),
        ]),
    ],
    systemPrompts: [
        new SystemMessage('You are a legal analyst.'),
    ],
    ttl: 60
);
```

Then reference that object's name in your request using withProviderOptions:

```php
$response = Prism::text()
    ->using(Provider::Gemini, 'gemini-1.5-flash-002')
    ->withProviderOptions(['cachedContentName' => $object->name])
    ->withPrompt('In no more than 100 words, what is the document about?')
    ->asText();
```

## Embeddings

You can customize your Gemini embeddings request with additional parameters using `->withProviderOptions()`.

### Title

You can add a title to your embedding request. Only applicable when TaskType is `RETRIEVAL_DOCUMENT`

```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

Prism::embeddings()
    ->using(Provider::Gemini, 'text-embedding-004')
    ->fromInput('The food was delicious and the waiter...')
    ->withProviderOptions(['title' => 'Restaurant Review'])
    ->asEmbeddings();
```

### Task Type

Gemini allows you to specify the task type for your embeddings to optimize them for specific use cases:

```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

Prism::embeddings()
    ->using(Provider::Gemini, 'text-embedding-004')
    ->fromInput('The food was delicious and the waiter...')
    ->withProviderOptions(['taskType' => 'RETRIEVAL_QUERY'])
    ->asEmbeddings();
```

[Available task types](https://ai.google.dev/api/embeddings#tasktype)

### Output Dimensionality

You can control the dimensionality of your embeddings:

```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

Prism::embeddings()
    ->using(Provider::Gemini, 'text-embedding-004')
    ->fromInput('The food was delicious and the waiter...')
    ->withProviderOptions(['outputDimensionality' => 768])
    ->asEmbeddings();
```

### Thinking Mode

Gemini 2.5 series models use an internal "thinking process" during response generation. Thinking is on by default as these models have the ability to automatically decide when and how much to think based on the prompt. If you would like to customize how many tokens the model may use for thinking, or disable thinking altogether, utilize the `withProviderOptions()` method, and pass through an array with a key value pair with `thinkingBudget` and an integer representing the budget of tokens. Set this value to `0` to disable thinking.

```php
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

$response = Prism::text()
    ->using(Provider::Gemini, 'gemini-2.5-flash-preview')
    ->withPrompt('Explain the concept of Occam\'s Razor and provide a simple, everyday example.')
    // Set thinking budget
    ->withProviderOptions(['thinkingBudget' => 300])
    ->asText();
```

> [!NOTE]
> Do not specify a `thinkingBudget` on 2.0 or prior series Gemini models as your request will fail.

## Media Support

Gemini has robust support for processing multimedia content:

### Video Analysis

Gemini can process and analyze video content including standard video files and YouTube videos. Prism implements this through the `Video` value object which maps to Gemini's video processing capabilities.

```php
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\Enums\Provider;

$response = Prism::text()
    ->using(Provider::Gemini, 'gemini-1.5-flash')
    ->withMessages([
        new UserMessage(
            'What is happening in this video?',
            additionalContent: [
                Video::fromUrl('https://example.com/sample-video.mp4'),
            ],
        ),
    ])
    ->asText();
```

### YouTube Integration

Gemini has special support for YouTube videos. You can easily `analyze/summarize` YouTube content by providing the URL:

```php
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\Enums\Provider;

$response = Prism::text()
    ->using(Provider::Gemini, 'gemini-1.5-flash')
    ->withMessages([
        new UserMessage(
            'Summarize this YouTube video:',
            additionalContent: [
                Video::fromUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            ],
        ),
    ])
    ->asText();
```

### Audio Processing

Gemini can analyze audio files for various tasks like transcription, content analysis, and audio scene understanding. The implementation in Prism uses the `Audio` value object which is specifically designed for Gemini's audio processing capabilities.

```php
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\Enums\Provider;

$response = Prism::text()
    ->using(Provider::Gemini, 'gemini-1.5-flash')
    ->withMessages([
        new UserMessage(
            'Transcribe this audio file:',
            additionalContent: [
                Audio::fromLocalPath('/path/to/audio.mp3'),
            ],
        ),
    ])
    ->asText();
```

## Image Generation

Prism supports Gemini image generation through Imagen and Gemini models. See Gemini [image generation docs](https://ai.google.dev/gemini-api/docs/image-generation) for full usage.

### Supported Models

| Model                                       | Description                                        |
| ------------------------------------------- | -------------------------------------------------- |
| `gemini-2.0-flash-preview-image-generation` | Experimental gemini image generation model.        |
| `imagen-4.0-generate-001`                   | Latest Imagen model. Good for HD image generation. |
| `imagen-4.0-ultra-generate-001`             | Highest quality images, only one image per request |
| `imagen-4.0-fast-generate-001`              | Fastest Imagen 4 model                             |
| `imagen-3.0-generate-002`                   | Imagen 3                                           |

### Basic Usage

```php
$response = Prism::image()
    ->using(Provider::Gemini, 'gemini-2.0-flash-preview-image-generation')
    ->withPrompt('Generate an image of ducklings wearing rubber boots')
    ->generate();

file_put_contents('image.png', base64_decode($response->firstImage()->base64));

// gemini models return usage and metadata
echo $response->usage->promptTokens;
echo $response->meta->id;
```

### Image Editing with Gemini

```php
$originalImage = fopen('image/boots.png', 'r');

$response = Prism::image()
    ->using(Provider::Gemini, 'gemini-2.0-flash-preview-image-generation')
    ->withPrompt('Actually, could we make those boots red?')
    ->withProviderOptions([
        'image' => $originalImage,
        'image_mime_type' => 'image/png',
    ])
    ->generate();

file_put_contents('new-boots.png', base64_decode($response->firstImage()->base64));
```

### Image options for Imagen models

```php
$response = Prism::image()
    ->using(Provider::Gemini, 'imagen-4.0-generate-001')
    ->withPrompt('Generate an image of a magnificent building falling into the ocean')
    ->withProviderOptions([
        'n' => 3,                               // number of images to generate
        'size' => '2K',                         // 1K (default), 2K
        'aspect_ratio' => '16:9',               // 1:1 (default), 3:4, 4:3, 9:16, 16:9
        'person_generation' => 'dont_allow',    // dont_allow, allow_adult, allow_all
    ])
    ->generate();
```

Note:

- Imagen 4 Ultra can only generate 1 image at a time.
- An empty response is sent if the prompt is in violation of the person_generation policy, causing Prism to throw an Exception.

### Response Format

All generated images are returned as base64 encoded strings.
