<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use MoonShine\UI\Fields\Text;
use Override;

class RowNumber extends Text
{
    protected static int $globalCounter = 0;

    protected ?int $localCounter = null;

    public function __construct(?string $label = null, ?string $column = null)
    {
        parent::__construct($label ?? '#', $column);
        $this->sortable = false; // Порядковый номер не сортируется
    }

    #[Override]
    protected function resolvePreview(): string
    {
        return (string) $this->resolveNumber();
    }

    #[Override]
    protected function resolveRawValue(): int
    {
        return $this->resolveNumber();
    }

    #[Override]
    public function fillData(mixed $value, int $index = 0): static
    {
        $this->localCounter = $index + 1;

        return parent::fillData($value, $index);
    }

    private function resolveNumber(): int
    {
        // Получаем номер страницы и размер страницы для пагинации
        $page = request()->integer('page', 1);
        $perPage = request()->integer('per_page', 25);

        if ($this->localCounter === null) {
            $this->localCounter = ++self::$globalCounter;
        }

        return ($page - 1) * $perPage + $this->localCounter;
    }

    public static function resetCounter(): void
    {
        self::$globalCounter = 0;
    }
}
