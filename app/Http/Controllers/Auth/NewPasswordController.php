<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\OpenApi\Attributes\RequestFormData;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;

class NewPasswordController extends Controller
{
    #[Post(
        path: '/reset-password',
        operationId: 'resetPassword',
        description: 'Позволяет пользователю сбросить пароль по предоставленному токену.',
        summary: 'Сброс пароля пользователя по токену',
        tags: ['Auth']
    )]
    #[RequestFormData(
        requiredProps: ['token', 'email', 'password', 'password_confirmation'],
        properties: [
            new Property(property: 'token', description: '', type: 'string'),
            new Property(property: 'email', description: '', type: 'string'),
            new Property(property: 'password', description: '', type: 'string'),
            new Property(property: 'password_confirmation', description: '', type: 'string'),
        ]
    )]
    #[Response(
        response: 200,
        description: 'Пароль был успешно сброшен.',
        content: new JsonContent(
            properties: [
                new Property(property: 'status', type: 'string', example: 'Ваш пароль был сброшен.'),
            ]
        )
    )]
    #[Response(
        response: 422,
        description: 'Ошибка: Необрабатываемое содержимое',
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
                                example: 'Значение поля email адрес должно быть действительным электронным адресом.'
                            )
                        ),
                        new Property(
                            property: 'password',
                            type: 'array',
                            items: new Items(
                                type: 'string',
                                example: 'Количество символов в поле пароль должно быть не меньше 8.'
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
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        /** @var string $status */
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function (mixed $user) use ($request): void {
                assert($user instanceof User);
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }
}
