<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TextResource;
use App\Models\Text;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;

class TextController extends Controller
{
    #[Get(
        path: '/texts',
        operationId: 'text-index',
        description: 'Возвращает полный список текстов',
        summary: 'Список текстов',
        tags: ['Text']
    )]
    #[Response(
        response: 200,
        description: 'OK',
        content: new JsonContent(properties: [
            new Property(property: 'data', type: 'array', items: new Items(ref: '#/components/schemas/Text')),
        ])
    )]
    public function index(): JsonResponse
    {
        return response()->json(TextResource::collection(Text::all()));
    }
}
