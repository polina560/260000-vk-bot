<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\AdminUser;
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
    protected function resetUserState(TelegramUser $user): void
    {
        $user->command = CommandType::None->value;
        $user->state = UserState::None->value;
        $user->prev_state = UserState::None->value;
        $user->data = null;
        $user->save();
    }

    /**
     * Клавиатура главного меню
     */
    protected function getMainKeyboard(): string
    {
        return json_encode([
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🚗 Опоздание',
                            'payload' => json_encode(['command' => 'delay']),
                        ],
                        'color' => 'primary',
                    ],

                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '📅 Изменения в расписании',
                            'payload' => json_encode(['command' => 'schedule']),
                        ],
                        'color' => 'primary',
                    ],

                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🤒 Заболел',
                            'payload' => json_encode(['command' => 'sick']),
                        ],
                        'color' => 'primary',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏥 Выхожу с больничного',
                            'payload' => json_encode(['command' => 'return-sick']),
                        ],
                        'color' => 'primary',
                    ],

                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '⚠️ Форс-мажор',
                            'payload' => json_encode(['command' => 'force-majeure']),
                        ],
                        'color' => 'primary',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '💬 Другое',
                            'payload' => json_encode(['command' => 'other']),
                        ],
                        'color' => 'primary',
                    ],
                ],
            ],
            'one_time' => false,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Клавиатура для возврата назад
     */
    protected function getBackKeyboard(string $command): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => $command, 'text' => 'назад']),
                        ],
                        'color' => 'secondary',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start']),
                        ],
                        'color' => 'secondary',
                    ],
                ],
            ],
            'one_time' => false,
        ];

        return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Клавиатура для подтверждения
     */
    protected function getConfirmKeyboard(string $command): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✅ Да',
                            'payload' => json_encode(['command' => $command, 'text' => 'да']),
                        ],
                        'color' => 'positive',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✏️ Исправить',
                            'payload' => json_encode(['command' => $command, 'text' => 'исправить']),
                        ],
                        'color' => 'negative',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start']),
                        ],
                        'color' => 'secondary',
                    ],
                ],
            ],
            'one_time' => false,
        ];

        return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Отправка сообщения администратору с Markdown
     */
    protected function sendToAdminWithMarkdown(string $message, ?int $peer_id = null): void
    {
        foreach (AdminUser::all() as $admin) {
            if (!$admin->peer_id) {
                Log::error('ID администратора не указан');
                return;
            }
            if ($admin->peer_id == $peer_id) {
                Log::error('Пользователь является админом');
                continue;
            }
            try {
                $this->vk->messages()->send($this->accessToken, [
                    'peer_id' => $admin->peer_id,
                    'message' => $message,
                    'random_id' => random_int(1, 1000000),
                    'parse_mode' => 'markdown',
                ]);
            } catch (\Exception $e) {
                Log::error('Ошибка отправки сообщения админу: '.$e->getMessage());
            }
        }
    }

}
