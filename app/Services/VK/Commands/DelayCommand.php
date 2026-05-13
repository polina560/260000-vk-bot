<?php

namespace App\Services\VK\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use VK\Client\VKApiClient;

class DelayCommand extends BaseCommand
{
    // Ключи для хранения состояний в кэше
    private const STATE_KEY = 'vk_user_state_';
    private const USER_DATA_KEY = 'vk_user_data_';

    public function execute(): void
    {
        $telegram_id = $this->fromId;
        $chat_id = $this->peerId;
        $text = trim($this->payload['text'] ?? '');

        // Получаем текущее состояние пользователя
        $state = $this->getUserState($telegram_id);
        $data = $this->getUserData($telegram_id);

        // Обработка команды "Главное меню"
        if (mb_strtolower($text) === 'главное меню') {
            $this->clearUserState($telegram_id);
            $this->showMainMenu($chat_id);
            return;
        }

        // Обработка кнопки "Назад"
        if (mb_strtolower($text) === 'назад') {
            $prevState = $data['prev_state'] ?? null;

            if ($prevState === 'wait_reason') {
                $this->setUserState($telegram_id, 'wait_reason', $data);
                $this->sendMessageWithKeyboard(
                    $chat_id,
                    "Укажи причину опоздания:",
                    $this->getBackKeyboard()
                );
                return;
            }

            if ($prevState === 'wait_time') {
                $this->setUserState($telegram_id, 'wait_time', $data);
                $this->sendMessageWithKeyboard(
                    $chat_id,
                    'Укажи на сколько минут ты опаздываешь:',
                    $this->getTimeKeyboard()
                );
                return;
            }
        }

        // Обработка в зависимости от состояния
        switch ($state) {
            case 'wait_reason':
                $this->handleWaitReason($chat_id, $telegram_id, $text, $data);
                break;

            case 'wait_time':
                $this->handleWaitTime($chat_id, $telegram_id, $text, $data);
                break;

            case 'confirm':
                $this->handleConfirm($chat_id, $telegram_id, $text, $data);
                break;

            default:
                // Начало процесса - запрос времени опоздания
                $this->setUserState($telegram_id, 'wait_time', []);
                $this->sendMessageWithKeyboard(
                    $chat_id,
                    'Укажи на сколько минут ты опаздываешь:',
                    $this->getTimeKeyboard()
                );
                break;
        }
    }

    /**
     * Обработка ввода причины опоздания
     */
    private function handleWaitReason($chat_id, $telegram_id, $text, $data): void
    {
        if (strlen($text) > 150) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "Ошибка! Укажи более краткую причину опоздания:",
                $this->getBackKeyboard()
            );
            return;
        }

        $data['reason'] = $text;
        $data['prev_state'] = 'wait_reason';
        $this->setUserState($telegram_id, 'confirm', $data);

        $reply = "Проверь информацию: \n";
        $reply .= "Опоздание: " . ($data['delay_minutes'] ?? '?') . " мин\n";
        $reply .= "Причина: " . $data['reason'] . "\n";
        $reply .= "Все верно? Напиши 'Да' или 'Исправить'";

        $this->sendMessageWithKeyboard(
            $chat_id,
            $reply,
            $this->getConfirmKeyboard()
        );
    }

    /**
     * Обработка ввода времени опоздания
     */
    private function handleWaitTime($chat_id, $telegram_id, $text, $data): void
    {
        // Проверка на числовое значение
        if (!is_numeric($text)) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "Ошибка! Укажи время опоздания в минутах числом:",
                $this->getTimeKeyboard()
            );
            return;
        }

        $minutes = (int)$text;

        if ($minutes < 1 || $minutes > 999) {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "Ошибка! Укажи число не большее 999 минут:",
                $this->getTimeKeyboard()
            );
            return;
        }

        $data['delay_minutes'] = $minutes;
        $data['prev_state'] = 'wait_time';
        $this->setUserState($telegram_id, 'wait_reason', $data);

        $this->sendMessageWithKeyboard(
            $chat_id,
            "Укажи причину опоздания:",
            $this->getBackKeyboard()
        );
    }

    /**
     * Обработка подтверждения
     */
    private function handleConfirm($chat_id, $telegram_id, $text, $data): void
    {
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'да') {
            // Получаем информацию о пользователе
            $userInfo = $this->getUserInfo();
            $customName = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
            $username = $userInfo['screen_name'] ?: ('id' . $telegram_id);

            // Формируем сообщение для администратора
            $msg = "🚨 *Опоздание*\n";
            $msg .= $customName . " (@" . $username . ")\n";
            $msg .= "Опоздание на: `" . $data['delay_minutes'] . " мин`\n";
            $msg .= "Комментарий: `" . $data['reason'] . "`\n";

            // Отправляем администратору
            $this->sendToAdminWithMarkdown($msg);

            // Сохраняем в базу данных (если есть)
            $this->saveToDatabase($telegram_id, $customName, $data);

            // Очищаем состояние пользователя
            $this->clearUserState($telegram_id);

            // Отправляем подтверждение пользователю
            $this->sendMessage($chat_id, "Готово! Информация передана руководству");

            // Показываем главное меню
            $this->showMainMenu($chat_id);

        } elseif ($textLower === 'исправить') {
            $this->setUserState($telegram_id, 'wait_time', $data);
            $this->sendMessageWithKeyboard(
                $chat_id,
                "Начнем заново. Укажи на сколько минут ты опаздываешь:",
                $this->getTimeKeyboard()
            );
        } else {
            $this->sendMessageWithKeyboard(
                $chat_id,
                "Напиши 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
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
                'parse_mode' => 'markdown' // В VK поддерживается markdown
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки сообщения админу: ' . $e->getMessage());
        }
    }

    /**
     * Сохранение в базу данных
     */
    private function saveToDatabase($userId, $customName, $data): void
    {
        // Если у вас есть подключение к БД
        // Здесь можно сохранить информацию в таблицу user_event_log
        Log::info('Запись об опоздании', [
            'user_id' => $userId,
            'custom_name' => $customName,
            'delay_minutes' => $data['delay_minutes'],
            'reason' => $data['reason']
        ]);
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
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'назад'])
                        ],
                        'color' => 'secondary'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'delay', 'text' => 'главное меню'])
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
                            'payload' => json_encode(['command' => 'delay', 'text' => 'главное меню'])
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
                            'payload' => json_encode(['command' => 'delay', 'text' => 'главное меню'])
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

    /**
     * Получение состояния пользователя из кэша
     */
    private function getUserState($userId): ?string
    {
        return Cache::get(self::STATE_KEY . $userId);
    }

    /**
     * Получение данных пользователя из кэша
     */
    private function getUserData($userId): array
    {
        return Cache::get(self::USER_DATA_KEY . $userId, []);
    }

    /**
     * Установка состояния пользователя
     */
    private function setUserState($userId, $state, $data = []): void
    {
        Cache::put(self::STATE_KEY . $userId, $state, now()->addHours(1));
        if (!empty($data)) {
            Cache::put(self::USER_DATA_KEY . $userId, $data, now()->addHours(1));
        }
    }

    /**
     * Очистка состояния пользователя
     */
    private function clearUserState($userId): void
    {
        Cache::forget(self::STATE_KEY . $userId);
        Cache::forget(self::USER_DATA_KEY . $userId);
    }
}
