<?php

namespace App\MoonShine\Resources\User\Pages;

use App\Models\User;
use App\MoonShine\Resources\User\UserResource;
use Cache;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Override;

/**
 * @extends IndexPage<UserResource>
 */
class UserIndexPage extends IndexPage
{
    #[Override]
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Имя', 'name'),
            Email::make('Email', 'email'),
        ];
    }

    #[Override]
    public function filters(): iterable
    {
        return [
            Text::make('Имя', 'name'),
            Email::make('Email', 'email'),
        ];
    }

    #[AsyncMethod]
    public function sendVerification(CrudRequestContract $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->getResource()?->getItem();
        $cacheKey = 'verification_request_'.$user->id;
        $throttleLimit = 6;
        $throttleTime = 60;

        // Проверяем, был ли запрос отправлен за последние 1 минуту
        $attempts = Cache::get($cacheKey, 0);
        assert(is_int($attempts));

        if ($attempts >= $throttleLimit) {
            return JsonResponse::make()->toast(
                'Вы превысили лимит запросов. Попробуйте позже.',
                ToastType::ERROR,
            );
        }

        if ($user->hasVerifiedEmail()) {
            return JsonResponse::make()->toast('Email уже подтвержден', ToastType::ERROR);
        }

        // Увеличиваем количество попыток и сохраняем в кеше
        Cache::put($cacheKey, $attempts + 1, $throttleTime);

        $user->sendEmailVerificationNotification();

        return JsonResponse::make()->toast('Письмо подтверждения отправлено', ToastType::SUCCESS);
    }

    #[Override]
    protected function buttons(): ListOf
    {
        return parent::buttons()
            ->prepend(ActionButton::make()
                ->square()
                ->method(
                    'sendVerification',
                    fn(mixed $item): array => ['resourceItem' => $item instanceof Model ? $item->getKey() : null],
                )
                ->async()
                ->icon('envelope'));
    }
}
