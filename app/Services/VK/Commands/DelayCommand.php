<?php

namespace App\Services\VK\Commands;

use App\Enums\UserState;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiClient;

class DelayCommand extends BaseCommand
{
    public function execute(): void
    {
        $telegram_id = $this->fromId;
        $chat_id = $this->peerId;
        $text = trim($this->payload['text'] ?? '');

        // Получаем или создаем пользователя
        $user = TelegramUser::firstOrCreate(
            ['form_id' => $telegram_id],
            [
                'name' => $this->getUserName(),
                'peer_id' => $chat_id,
                'state' => UserState::None->value,
                'prev_state' => UserState::None->value,
                'data' => []
            ]
        );

        // Обновляем peer_id если изменился
        if ($user->peer_id != $chat_id) {
            $user->peer_id = $chat_id;
        }

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        Log::info('DelayCommand', [
            'user_id' => $telegram_id,
            'state' => $state,
            'prev_state' => $prevState,
            'text' => $text,
            'data' => $data
        ]);

        // Обработка команды "Главное меню"
        if (mb_strtolower($text) === 'главное меню') {
            $user->state = UserState::None->value;
            $user->prev_state = UserState::None->value;
            $user->data = null;
            $user->save();
            $this->showMainMenu($chat_id);
            return;
        }

        // Обработка кнопки "Назад"
        if (mb_strtolower($text) === 'назад') {
            if ($prevState === UserState::WaitReason->value) {
                $user->state = UserState::WaitReason->value;
                $user->save();
                $this->sendMessageWithKeyboard(
                    $chat_id,
                    "📝 Укажи причину опоздания:",
                    $this->getBackKeyboard()
                );
                return;
            }

            if ($prevState === UserState::WaitTime->value) {
                $user->state = UserState::WaitTime->value;
                $user->save();
                $this->sendMessageWithKeyboard(
                    $chat_id,
                    '⏰ Укажи на сколько минут ты опаздываешь:',
                    $this->getTimeKeyboard()
                );
                return;
            }
        }

        // Обработка в зависимости от состояния
        switch ($state) {
            case UserState::WaitReason->value:
                $this->handleWaitReason($user, $text, $data);
                break;

            case UserState::WaitTime->value:
                $this->handleWaitTime($user, $text, $data);
                break;

            case UserState::Confirm->value:
                $this->handleConfirm($user, $text, $data);
                break;

            default:
                // Начало процесса - запрос времени опоздания
                $user->state = UserState::WaitTime->value;
                $user->prev_state = UserState::None->value;
                $user->data = [];
                $user->save();

                $this->sendMessageWithKeyboard(
                    $chat_id,
                    '⏰ Укажи на сколько минут ты опаздываешь:',
                    $this->getTimeKeyboard()
                );
                break;
        }
    }

    /**
     * Получение имени пользователя
     */
    private function getUserName(): string
    {
        $userInfo = $this->getUserInfo();
        return ($userInfo['first_name'] ?? 'Пользователь') . ' ' . ($userInfo['last_name'] ?? '');
    }

    /**
     * Обработка ввода причины опоздания
     */
    private function handleWaitReason(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;

        if (empty($text)) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "❌ Пожалуйста, напиши причину опоздания:",
                $this->getBackKeyboard()
            );
            return;
        }

        if (strlen($text) > 150) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "❌ Ошибка! Укажи более краткую причину опоздания (до 150 символов):",
                $this->getBackKeyboard()
            );
            return;
        }

        $data['reason'] = $text;

        $user->prev_state = UserState::WaitReason->value;
        $user->state = UserState::Confirm->value;
        $user->data = $data;
        $user->save();

        $reply = "📋 *Проверь информацию:*\n\n";
        $reply .= "⏰ Опоздание: " . ($data['delay_minutes'] ?? '?') . " мин\n";
        $reply .= "📝 Причина: " . $data['reason'] . "\n\n";
        $reply .= "✅ Все верно? Напиши 'Да' или 'Исправить'";

        $this->sendMessageWithKeyboard(
            $chat_id,
            $reply,
            $this->getConfirmKeyboard()
        );
    }

    /**
     * Обработка ввода времени опоздания
     */
    private function handleWaitTime(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;

        // Проверка на числовое значение
        if (!is_numeric($text)) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "❌ Ошибка! Укажи время опоздания в минутах числом (например: 15):",
                $this->getTimeKeyboard()
            );
            return;
        }

        $minutes = (int)$text;

        if ($minutes < 1 || $minutes > 999) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "❌ Ошибка! Укажи число от 1 до 999 минут:",
                $this->getTimeKeyboard()
            );
            return;
        }

        $data['delay_minutes'] = $minutes;

        $user->prev_state = UserState::WaitTime->value;
        $user->state = UserState::WaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessageWithKeyboard(
            $chat_id,
            "📝 Укажи причину опоздания:",
            $this->getBackKeyboard()
        );
    }

    /**
     * Обработка подтверждения
     */
    private function handleConfirm(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;
        $telegram_id = $user->form_id;
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'да') {
            // Получаем информацию о пользователе
            $userInfo = $this->getUserInfo();
            $customName = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
            $username = $userInfo['screen_name'] ?: ('id' . $telegram_id);

            // Формируем сообщение для администратора
            $msg = "🚨 *НОВОЕ ОПОЗДАНИЕ*\n\n";
            $msg .= "👤 Сотрудник: {$customName}\n";
            $msg .= "📱 Username: @{$username}\n";
            $msg .= "⏰ Опоздание: `{$data['delay_minutes']} мин`\n";
            $msg .= "📝 Причина: `{$data['reason']}`\n";
            $msg .= "🕐 Время: " . date('d.m.Y H:i:s');

            // Отправляем администратору
            $this->sendToAdminWithMarkdown($msg);

            // Сохраняем в лог
            Log::info('Запись об опоздании', [
                'user_id' => $telegram_id,
                'custom_name' => $customName,
                'delay_minutes' => $data['delay_minutes'],
                'reason' => $data['reason']
            ]);

            // Очищаем состояние пользователя
            $user->state = UserState::None->value;
            $user->prev_state = UserState::None->value;
            $user->data = null;
            $user->save();

            // Отправляем подтверждение пользователю
            $this->sendMessage($chat_id, "✅ Готово! Информация об опоздании передана руководству.");

            // Показываем главное меню
            $this->showMainMenu($chat_id);

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::WaitTime->value;
            $user->prev_state = UserState::None->value;
            $user->data = [];
            $user->save();

            $this->sendMessageWithKeyboard(
                $chat_id,
                "🔄 Начнем заново. Укажи на сколько минут ты опаздываешь:",
                $this->getTimeKeyboard()
            );
        } else {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "❓ Напиши 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                $this->getConfirmKeyboard()
            );
        }
    }

    /**
     * Отправка сообщения администратору с Markdown
     */
    private function sendToAdminWithMarkdown(string $message): void
    {
        $adminId = config('services.vk.admin_id');

        if (!$adminId) {
            Log::error('ID администратора не указан');
            return;
        }

        try {
            $this->vk->messages()->send($this->accessToken, [
                'peer_id' => $adminId,
                'message' => $message,
                'random_id' => random_int(1, 1000000),
                'parse_mode' => 'markdown'
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки сообщения админу: ' . $e->getMessage());
        }
    }

    /**
     * Показать главное меню
     */
    private function showMainMenu($chat_id): void
    {
        $startCommand = new StartCommand($this->vk, $this->accessToken, $chat_id, $this->fromId, null);
        $startCommand->execute();
    }

    /**
     * Клавиатура с вариантами времени
     */
    private function getTimeKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '10',
                            'payload' => json_encode(['command' => 'delay', 'text' => '10'])
                        ],
                        'color' => 'primary'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '15',
                            'payload' => json_encode(['command' => 'delay', 'text' => '15'])
                        ],
                        'color' => 'primary'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '30',
                            'payload' => json_encode(['command' => 'delay', 'text' => '30'])
                        ],
                        'color' => 'primary'
                    ]
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✏️ Своё значение',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'custom'])
                        ],
                        'color' => 'secondary'
                    ]
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'назад'])
                        ],
                        'color' => 'secondary'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start'])
                        ],
                        'color' => 'secondary'
                    ]
                ]
            ],
            'one_time' => false
        ];

        return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Клавиатура для возврата назад
     */
    private function getBackKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'назад'])
                        ],
                        'color' => 'secondary'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start'])
                        ],
                        'color' => 'secondary'
                    ]
                ]
            ],
            'one_time' => false
        ];

        return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Клавиатура для подтверждения
     */
    private function getConfirmKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✅ Да',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'да'])
                        ],
                        'color' => 'positive'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✏️ Исправить',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'исправить'])
                        ],
                        'color' => 'negative'
                    ]
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start'])
                        ],
                        'color' => 'secondary'
                    ]
                ]
            ],
            'one_time' => false
        ];

        return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Отправка сообщения с клавиатурой
     */
    private function sendMessageWithKeyboard($chat_id, $message, $keyboard): void
    {
        $this->sendMessage($message, $keyboard);
    }
}
