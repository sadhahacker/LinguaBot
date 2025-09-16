<?php

namespace Prism\Prism\Providers\Gemini\Maps;

use Prism\Prism\Contracts\Schema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;

class SchemaMap
{
    public function __construct(
        private readonly Schema $schema,
    ) {}

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $schemaArray = $this->schema->toArray();

        // Remove unsupported fields
        unset($schemaArray['additionalProperties'], $schemaArray['description'], $schemaArray['name']);

        return array_merge(
            array_filter([
                ...$schemaArray,
                'type' => $this->mapType(),
            ]),
            array_filter([
                'items' => property_exists($this->schema, 'items') && $this->schema->items
                    ? (new self($this->schema->items))->toArray()
                    : null,
                'properties' => $this->schema instanceof ObjectSchema && property_exists($this->schema, 'properties')
                    ? array_reduce($this->schema->properties, function (array $carry, Schema $property): array {
                        // Use property name as the key, but do NOT include "name" inside the value
                        $carry[$property->name()] = (new self($property))->toArray();

                        return $carry;
                    }, [])
                    : null,
                'nullable' => property_exists($this->schema, 'nullable') && $this->schema->nullable
                    ? true
                    : null,
            ])
        );
    }

    protected function mapType(): string
    {
        if ($this->schema instanceof ArraySchema) {
            return 'array';
        }
        if ($this->schema instanceof BooleanSchema) {
            return 'boolean';
        }
        if ($this->schema instanceof NumberSchema) {
            return 'number';
        }
        if ($this->schema instanceof ObjectSchema) {
            return 'object';
        }

        return 'string';
    }
}
