<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\OpenApi\Attributes\RequestFormData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;

class PasswordResetLinkController extends Controller
{
    #[Post(
        path: '/forgot-password',
        operationId: 'sendPasswordResetLink',
        description: 'Отправка письма со ссылкой на сброс пароля пользователю по email.',
        summary: 'Запрос на сброс пароля',
        tags: ['Auth']
    )]
    #[RequestFormData(
        requiredProps: ['email'],
        properties: [
            new Property(property: 'email', description: 'Email', type: 'string'),
        ]
    )]
    #[Response(
        response: 200,
        description: 'Ссылка для сброса пароля отправлена',
        content: new JsonContent(
            properties: [
                new Property(property: 'status', type: 'string', example: 'Ссылка на сброс пароля была отправлена.'),
            ]
        )
    )]
    #[Response(
        response: 422,
        description: 'Ошибка: Необработанный контент',
        content: new JsonContent(
            properties: [
                new Property(
                    property: 'message',
                    type: 'string',
                    example: 'Значение поля email адрес должно быть действительным электронным адресом.'
                ),
                new Property(
                    property: 'errors',
                    properties: [
                        new Property(
                            property: 'email',
                            type: 'array',
                            items: new Items(
                                type: 'string',
                                example: 'Не удалось найти пользователя с указанным электронным адресом.'
                            )
                        ),
                    ],
                ),
            ]
        )
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }
}
