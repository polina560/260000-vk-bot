<?php

namespace App\OpenApi\Attributes;

use Attribute;
use OpenApi\Attributes\AdditionalProperties;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
class RequestFormData extends RequestBody
{
    /**
     * {@inheritDoc}
     *
     * @param  list<string>|null  $requiredProps
     * @param  list<Property>|null  $properties
     */
    public function __construct(
        object|string|null $ref = null,
        ?string $request = null,
        ?string $description = null,
        ?bool $required = null,
        ?array $requiredProps = null,
        ?array $properties = null,
        ?array $x = null,
        ?array $attachables = null,
        ?AdditionalProperties $additionalProperties = null
    ) {
        $schema = new Schema(
            required: $requiredProps,
            properties: $properties,
            type: 'object',
            additionalProperties: $additionalProperties
        );

        $content = [new MediaType(mediaType: 'multipart/form-data', schema: $schema)];

        parent::__construct($ref, $request, $description, $required, $content, $x, $attachables);
    }
}
