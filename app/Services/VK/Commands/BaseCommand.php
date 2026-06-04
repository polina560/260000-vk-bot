<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiClient;

abstract class BaseCommand
{

    protected VKApiClient $vk;
    protected string $accessToken;
    protected int $peerId;
    protected int $fromId;
    protected ?array $payload;

    public function __construct(VKApiClient $vk, string $accessToken, int $peerId, int $fromId, ?array $payload = null)
    {
        $this->vk = $vk;
        $this->accessToken = $accessToken;
        $this->peerId = $peerId;
        $this->fromId = $fromId;
        $this->payload = $payload;
    }

    /**
     * Выполнение команды
     */
    abstract public function execute(): void;

    /**
     * Отправка сообщения
     */
    protected function sendMessage(string $message, ?string $keyboard = null, ?int $customPeerId = null): void
    {
        $peerId = $customPeerId ?? $this->peerId;

        try {
            $params = [
                'peer_id' => $peerId,
                'message' => $message,
                'random_id' => random_int(1, 1000000)
            ];

            if ($keyboard) {
                $params['keyboard'] = $keyboard;
            }

            $this->vk->messages()->send($this->accessToken, $params);

        } catch (\Exception $e) {
            Log::error('Ошибка отправки сообщения: ' . $e->getMessage());
        }
    }

    /**
     * Получение информации о пользователе
     */
    protected function getUserInfo(): array
    {
        try {
            $user = $this->vk->users()->get($this->accessToken, [
                'user_ids' => [$this->fromId],
                'fields' => ['first_name', 'last_name', 'screen_name', 'photo_50'],
            ]);

            if (!empty($user)) {
                return [
                    'id' => $this->fromId,
                    'first_name' => $user[0]['first_name'] ?? 'Неизвестно',
                    'last_name' => $user[0]['last_name'] ?? '',
                    'screen_name' => $user[0]['screen_name'] ?? '',
                    'link' => "https://vk.com/id{$this->fromId}",
                ];
            }
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации о пользователе: ' . $e->getMessage());
        }

        return [
            'id' => $this->fromId,
            'first_name' => 'Пользователь',
            'last_name' => '',
            'screen_name' => '',
            'link' => "https://vk.com/id{$this->fromId}",
        ];
    }

    /**
     * Получение имени пользователя
     */
    protected function getUserName(): string
    {
        $userInfo = $this->getUserInfo();

        return ($userInfo['first_name'] ?? 'Пользователь').' '.($userInfo['last_name'] ?? '');
    }

    /**
     * Сброс состояния пользователя
     */
    public function resetUserState(TelegramUser $user): void
    {
        $user->command = CommandType::None->value;
        $user->state = UserState::None->value;
        $user->prev_state = UserState::None->value;
        $user->data = null;
        $user->save();
    }

}
