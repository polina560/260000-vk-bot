<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;

class VerifyEmailController extends Controller
{
    #[Get(
        path: '/verify-email/{id}/{hash}',
        operationId: 'verifyEmail',
        description: 'Пользователь подтверждает свой email через ссылку с уникальным токеном.',
        summary: 'Подтверждение email адреса',
        security: [['bearerAuth' => []]],
        tags: ['Auth']
    )]
    #[Parameter(name: 'id', description: 'ID пользователя для подтверждения email', in: 'path', required: true, schema: new Schema(
        type: 'string'
    ))]
    #[Parameter(name: 'hash', description: 'Хеш, генерируемый для подтверждения email', in: 'path', required: true, schema: new Schema(
        type: 'string'
    ))]
    #[Parameter(name: 'expires', description: 'Дата истечения срока действия подписи (UNIX timestamp)', in: 'query', required: true, schema: new Schema(
        type: 'integer'
    ))]
    #[Parameter(name: 'signature', description: 'Подпись для верификации', in: 'query', required: true, schema: new Schema(
        type: 'string'
    ))]
    #[Response(
        response: 200,
        description: 'Email успешно подтвержден.',
        content: new JsonContent(
            properties: [
                new Property(property: 'status', type: 'string', example: 'Успешно подтверждено.'),
            ]
        )
    )]
    #[Response(
        response: 403,
        description: 'Ссылка для подтверждения email недействительна или устарела',
        content: new JsonContent(
            properties: [
                new Property(
                    property: 'status',
                    type: 'string',
                    example: 'Эта ссылка для подтверждения email больше недействительна. Пожалуйста, запросите новое письмо с подтверждением.'
                ),
            ]
        )
    )]
    public function __invoke(EmailVerificationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            // return redirect()->intended(
            //     config('app.frontend_url').'/dashboard?verified=1'
            // );

            return response()->json(['status' => 'Уже подтверждено']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // return redirect()->intended(
        //     config('app.frontend_url').'/dashboard?verified=1'
        // );

        return response()->json(['status' => 'Успешно подтверждено']);
    }
}
