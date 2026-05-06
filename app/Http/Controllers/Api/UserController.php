<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class UserController extends Controller
{
    #[Get(
        path: '/profile',
        operationId: 'getProfile',
        description: 'Профиль',
        summary: 'Профиль',
        security: [['bearerAuth' => []]],
        tags: ['User']
    )]
    #[Response(
        response: 200,
        description: 'OK',
        content: new JsonContent(ref: '#/components/schemas/Profile'),
    )]
    public function profile(Request $request): JsonResponse
    {
        return response()->json(ProfileResource::make($request->user()));
    }
}
