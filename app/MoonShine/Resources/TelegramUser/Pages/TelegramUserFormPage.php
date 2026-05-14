<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TelegramUser\Pages;

use App\Enums\CommandType;
use App\Enums\UserState;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use App\MoonShine\Resources\TelegramUser\TelegramUserResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Enum;
use Throwable;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Number;

/**
 * @extends FormPage<TelegramUserResource>
 */
class TelegramUserFormPage extends FormPage
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
			Text::make('PeerId', 'peer_id'),
			Text::make('FormId', 'form_id'),
            Enum::make('State', 'state')
                ->attach(UserState::class),
            Enum::make('Command', 'command')
                ->attach(CommandType::class)
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
			'peer_id' => ['string', 'nullable'],
			'form_id' => ['string', 'nullable'],
			'state' => ['int', 'required'],
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
