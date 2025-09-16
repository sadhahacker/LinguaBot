<?php

declare(strict_types=1);

namespace Prism\Prism\Text;

use Closure;
use Prism\Prism\Concerns\ChecksSelf;
use Prism\Prism\Concerns\HasProviderOptions;
use Prism\Prism\Contracts\Message;
use Prism\Prism\Contracts\PrismRequest;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\ProviderTool;

class Request implements PrismRequest
{
    use ChecksSelf, HasProviderOptions;

    /**
     * @param  SystemMessage[]  $systemPrompts
     * @param  array<int, Message>  $messages
     * @param  array<int, Tool>  $tools
     * @param  array<string, mixed>  $clientOptions
     * @param  array{0: array<int, int>|int, 1?: Closure|int, 2?: ?callable, 3?: bool}  $clientRetry
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, ProviderTool>  $providerTools
     */
    public function __construct(
        protected string $model,
        protected string $providerKey,
        protected array $systemPrompts,
        protected ?string $prompt,
        protected array $messages,
        protected int $maxSteps,
        protected ?int $maxTokens,
        protected int|float|null $temperature,
        protected int|float|null $topP,
        protected array $tools,
        protected array $clientOptions,
        protected array $clientRetry,
        protected string|ToolChoice|null $toolChoice,
        array $providerOptions = [],
        protected array $providerTools = [],
    ) {
        $this->providerOptions = $providerOptions;
    }

    public function toolChoice(): string|ToolChoice|null
    {
        return $this->toolChoice;
    }

    /**
     * @return array{0: array<int, int>|int, 1?: Closure|int, 2?: ?callable, 3?: bool} $clientRetry
     */
    public function clientRetry(): array
    {
        return $this->clientRetry;
    }

    /**
     * @return array<string, mixed> $clientOptions
     */
    public function clientOptions(): array
    {
        return $this->clientOptions;
    }

    /**
     * @return Tool[]
     */
    public function tools(): array
    {
        return $this->tools;
    }

    public function topP(): int|float|null
    {
        return $this->topP;
    }

    /**
     * @return array<int,ProviderTool>
     */
    public function providerTools(): array
    {
        return $this->providerTools;
    }

    public function temperature(): int|float|null
    {
        return $this->temperature;
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function maxSteps(): int
    {
        return $this->maxSteps;
    }

    /**
     * @return Message[]
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function prompt(): ?string
    {
        return $this->prompt;
    }

    /**
     * @return SystemMessage[]
     */
    public function systemPrompts(): array
    {
        return $this->systemPrompts;
    }

    #[\Override]
    public function model(): string
    {
        return $this->model;
    }

    public function provider(): string
    {
        return $this->providerKey;
    }

    public function addMessage(Message $message): self
    {
        $this->messages = array_merge($this->messages, [$message]);

        return $this;
    }
}
