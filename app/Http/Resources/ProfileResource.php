<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Override;

/**
 * @mixin User
 */
#[Schema(schema: 'Profile', properties: [
    new Property(property: 'name', description: 'Имя пользователя', type: 'string'),
    new Property(property: 'email', description: 'Email пользователя', type: 'string'),
])]
class ProfileResource extends JsonResource
{
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
