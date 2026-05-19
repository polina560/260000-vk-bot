<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UserLog\Pages;

use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use App\MoonShine\Resources\UserLog\UserLogResource;
use MoonShine\Support\ListOf;
use Throwable;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Date;
use App\MoonShine\Resources\TelegramUser\TelegramUserResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;

/**
 * @extends FormPage<UserLogResource>
 */
class UserLogFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
			ID::make('id')
				->sortable(),
			Text::make('Name', 'name'),
			Text::make('Type', 'type'),
			Text::make('Description', 'description'),
			Date::make('Date', 'date'),
			BelongsTo::make('TelegramUserId', 'telegramUser', resource: TelegramUserResource::class),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    protected function formButtons(): ListOf
    {
        return parent::formButtons();
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
			'name' => ['string', 'nullable'],
			'type' => ['string', 'nullable'],
			'description' => ['string', 'nullable'],
			'date' => ['string', 'nullable'],
			'telegram_user_id' => ['int', 'required'],
        ];
    }

    /**
     * @param  FormBuilder  $component
     *
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer()
        ];
    }
}
