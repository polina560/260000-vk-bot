<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\OpenApi\Attributes\RequestFormData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;

class RegisteredUserController extends Controller
{
    #[Post(
        path: '/register',
        operationId: 'registerUser',
        description: 'Регистрация нового пользователя',
        summary: 'Регистрация',
        tags: ['Auth']
    )]
    #[RequestFormData(
        requiredProps: ['name', 'email', 'password', 'password_confirmation'],
        properties: [
            new Property(property: 'name', description: 'Имя пользователя', type: 'string'),
            new Property(property: 'email', description: 'Email', type: 'string'),
            new Property(property: 'password', description: 'Пароль', type: 'string'),
            new Property(property: 'password_confirmation', description: 'Подтверждение пароля', type: 'string'),
        ]
    )]
    #[Response(
        response: 201,
        description: 'Успешная регистрация',
        content: new JsonContent(
            properties: [
                new Property(property: 'message', type: 'string', example: 'User Created'),
                new Property(property: 'profile', ref: '#/components/schemas/Profile', type: 'object'),
            ],
        )
    )]
    #[Response(
        response: 422,
        description: 'Ошибка валидации',
        content: new JsonContent(
            properties: [
                new Property(
                    property: 'message', type: 'string', example: 'Такое значение поля email адрес уже существует.',
                ),
                new Property(
                    property: 'errors',
                    properties: [
                        new Property(
                            property: 'email',
                            type: 'array',
                            items: new Items(
                                type: 'string',
                                example: 'Значение поля email адрес должно быть действительным электронным адресом.',
                            ),
                        ),
                        new Property(
                            property: 'password',
                            type: 'array',
                            items: new Items(
                                type: 'string',
                                example: 'Количество символов в поле пароль должно быть не меньше 8.',
                            ),
                        ),
                    ],
                ),
            ],
        )
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return response()->json([
            'message' => 'User Created',
            'token' => $user->createToken('token for '.$user->email)->plainTextToken,
            'profile' => ProfileResource::make($user),
        ], 201);
    }
}
