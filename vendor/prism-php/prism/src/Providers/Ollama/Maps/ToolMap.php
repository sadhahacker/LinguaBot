<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Ollama\Maps;

use Prism\Prism\Tool;

class ToolMap
{
    /**
     * @param  Tool[]  $tools
     * @return array<string, mixed>
     */
    public static function map(array $tools): array
    {
        return array_map(fn (Tool $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => [
                    'type' => 'object',
                    ...$tool->parameters() ? ['properties' => $tool->parametersAsArray()] : [],
                    'required' => $tool->requiredParameters(),
                ],
            ],
        ], $tools);
    }
}
