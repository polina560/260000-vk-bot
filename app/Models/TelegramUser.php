<?php

declare(strict_types=1);

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
	protected $table =  'telegram_user';

    protected $fillable = [
		'name',
		'peer_id',
		'form_id',
		'state',
        'command',
        'prev_state',
        'data',
        'last_activity'
    ];

    protected $casts = [
        'state' => 'integer',
        'data' => 'array',
        'last_activity' => 'datetime'
    ];

    public function setUserData(array $data): void
    {
        $this->data = $data;
        $this->save();
    }

    public function getUserData(): array
    {
        return $this->data ?? [];
    }

    /**
     * Проверить состояние
     */
    public function isState(int $state): bool
    {
        return $this->state === $state;
    }

    /**
     * Установить состояние
     */
    public function setState(int $state, int $prevState = null): void
    {
        $this->state = $state;
        if ($prevState !== null) {
            $this->prev_state = $prevState;
        }
        $this->save();
    }
}
