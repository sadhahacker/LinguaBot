<?php

declare(strict_types=1);

namespace Prism\Prism\Images;

use Closure;
use Prism\Prism\Concerns\ChecksSelf;
use Prism\Prism\Concerns\HasProviderOptions;
use Prism\Prism\Contracts\PrismRequest;

class Request implements PrismRequest
{
    use ChecksSelf, HasProviderOptions;

    /**
     * @param  array<string, mixed>  $clientOptions
     * @param  array{0: array<int, int>|int, 1?: Closure|int, 2?: ?callable, 3?: bool}  $clientRetry
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        protected string $model,
        protected string $providerKey,
        protected string $prompt,
        protected array $clientOptions,
        protected array $clientRetry,
        array $providerOptions = [],
    ) {
        $this->providerOptions = $providerOptions;
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

    public function prompt(): string
    {
        return $this->prompt;
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
}
