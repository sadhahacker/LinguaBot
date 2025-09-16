<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\OpenRouter\Concerns;

use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Providers\OpenRouter\Maps\FinishReasonMap;

trait MapsFinishReason
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function mapFinishReason(array $data): FinishReason
    {
        return FinishReasonMap::map(data_get($data, 'choices.0.finish_reason', ''));
    }
}
