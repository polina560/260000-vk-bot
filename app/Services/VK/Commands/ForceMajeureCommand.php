<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

class ForceMajeureCommand extends BaseCommand
{
    /**
     * Основные состояния для форс-мажора
     */
    private const TYPE_HOURS = 'В рамках нескольких часов';

    private const TYPE_ALL_DAY = 'Весь день';

    private const TYPE_SEVERAL_DAYS = 'Несколько дней';

    public function execute(): void
    {
        $userId = $this->fromId;
        $peerId = $this->peerId;
        $text = trim($this->payload['text'] ?? '');

        // Получаем или создаем пользователя
        $user = TelegramUser::firstOrCreate(
            ['form_id' => $userId],
            [
                'name' => $this->getUserName(),
                'peer_id' => $peerId,
                'state' => UserState::None->value,
                'command' => CommandType::ForceMajeure->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        $user->command = CommandType::ForceMajeure->value;
        if ($user->peer_id != $peerId) {
            $user->peer_id = $peerId;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        Log::info('ForceMajeureCommand', [
            'user_id' => $userId,
            'state' => $state,
            'prev_state' => $prevState,
            'text' => $text,
            'data' => $data,
        ]);

        // 🔹 Обработка "Главное меню"
        $textLower = mb_strtolower($text);
        if ($textLower === 'главное меню' || $textLower === 'меню' || $textLower === 'start') {
            $this->resetUserState($user);
            $startCommand = $this->commandFactory->make('start', $peerId, $userId, null);
            if ($startCommand) {
                $startCommand->execute();
            }

            return;
        }

        // 🔹 Обработка кнопки "Назад"
        if ($textLower === 'назад') {
            $this->handleBack($user, $prevState, $peerId, $data);

            return;
        }

        // 🔹 FSM: обработка по состояниям
        match ($state) {
            UserState::FMWaitType->value => $this->handleWaitType($user, $text, $data),
            UserState::FMWaitHours->value => $this->handleWaitHours($user, $text, $data),
            UserState::FMWaitDays->value => $this->handleWaitDays($user, $text, $data),
            UserState::FMWaitReason->value => $this->handleWaitReason($user, $text, $data),
            UserState::FMConfirm->value => $this->handleConfirm($user, $text, $data),
            default => $this->startForceMajeureFlow($user, $peerId),
        };
    }

    /**
     * Начало потока: выбор типа отсутствия
     */
    private function startForceMajeureFlow(TelegramUser $user, int $peerId): void
    {
        $user->state = UserState::FMWaitType->value;
        $user->prev_state = UserState::None->value;
        $user->data = [];
        $user->save();

        $this->sendMessage(
            '⚠️ Укажи, в каких временных рамках будешь отсутствовать:',
            $this->getTypeKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка выбора типа отсутствия
     */
    private function handleWaitType(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;
        $validTypes = [self::TYPE_HOURS, self::TYPE_ALL_DAY, self::TYPE_SEVERAL_DAYS];

        if (!in_array($text, $validTypes)) {
            $this->sendMessage(
                '❌ Пожалуйста, выбери временной промежуток с помощью кнопок ниже:',
                $this->getTypeKeyboard(),
                $peerId
            );

            return;
        }

        $data['type'] = $text;
        $data['prev_state'] = UserState::FMWaitType->value;

        if ($text === self::TYPE_HOURS) {
            $user->state = UserState::FMWaitHours->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                '⏰ Сколько часов ты будешь отсутствовать? (укажи числом):',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        if ($text === self::TYPE_SEVERAL_DAYS) {
            $user->state = UserState::FMWaitDays->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                '📅 Сколько дней ты будешь отсутствовать? (укажи числом):',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        // Весь день — сразу переходим к причине
        $data['duration'] = self::TYPE_ALL_DAY;
        $user->state = UserState::FMWaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '📝 Укажи причину отсутствия:',
            $this->getBackKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка ввода количества часов
     */
    private function handleWaitHours(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;

        if (!is_numeric($text)) {
            $this->sendMessage(
                '❌ Ошибка! Укажи количество часов числом (например: 3):',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        $hours = (int) $text;
        if ($hours < 1 || $hours > 24) {
            $this->sendMessage(
                '❌ Ошибка! Укажи число от 1 до 24:',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        $data['duration'] = $hours.' ч';
        $user->state = UserState::FMWaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '📝 Укажи причину отсутствия:',
            $this->getBackKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка ввода количества дней
     */
    private function handleWaitDays(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;

        if (!is_numeric($text)) {
            $this->sendMessage(
                '❌ Ошибка! Укажи количество дней числом (например: 2):',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        $days = (int) $text;
        if ($days < 1 || $days > 30) {
            $this->sendMessage(
                '❌ Ошибка! Укажи число от 1 до 30:',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        $data['duration'] = $days.' дн.';
        $user->state = UserState::FMWaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '📝 Укажи причину отсутствия:',
            $this->getBackKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка ввода причины
     */
    private function handleWaitReason(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Пожалуйста, напиши причину отсутствия:',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        if (strlen($text) > 200) {
            $this->sendMessage(
                '❌ Ошибка! Текст слишком длинный, опиши более кратко (до 200 символов):',
                $this->getBackKeyboard(),
                $peerId
            );

            return;
        }

        $data['reason'] = $text;
        $data['prev_state'] = UserState::FMWaitReason->value;
        $user->state = UserState::FMConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "📋 Проверь информацию:\n\n";
        $reply .= "⚠️ Тип отсутствия: {$data['type']}\n";
        $reply .= "⏱️ Длительность: {$data['duration']}\n";
        $reply .= "📝 Причина: {$data['reason']}\n\n";
        $reply .= '✅ Все верно? Напиши *Да* или *Исправить*';

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка подтверждения
     */
    private function handleConfirm(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;
        $userId = $user->form_id;
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'да') {
            $userInfo = $this->getUserInfo();
            $customName = trim(($userInfo['first_name'] ?? '').' '.($userInfo['last_name'] ?? ''));
            $username = $userInfo['screen_name'] ?: ('id'.$userId);

            $msg = "🚨 Ффорс-мажор\n\n";
            $msg .= "👤 Сотрудник: {$customName}\n";
            $msg .= "📱 Username: @{$username}\n";
            $msg .= "⚠️ Тип: `{$data['type']}`\n";
            $msg .= "⏱️ Длительность: `{$data['duration']}`\n";
            $msg .= "📝 Причина: `{$data['reason']}`\n";
            $msg .= '🕐 Время: '.date('d.m.Y H:i:s');

            $this->sendToAdminWithMarkdown($msg);

            $description = "Тип: `{$data['type']}`\n"
            ."Длительность: `{$data['duration']}`\n"
                ."Причина: `{$data['reason']}`\n";

            $this->logForceMajeureEvent($user->id, $description, $username);

            $this->resetUserState($user);

            $this->sendMessage(
                '✅ Готово! Информация передана руководству.',
                $this->getKeyboardStart(),
                $peerId
            );

            return;

        } elseif ($textLower === 'исправить') {
            // Возврат к началу: выбор типа
            $user->state = UserState::FMWaitType->value;
            $user->prev_state = UserState::FMConfirm->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                '🔄 Укажи в каких временных рамках будешь отсутствовать:',
                $this->getTypeKeyboard(),
                $peerId
            );

            return;
        }

        $this->sendMessage(
            '❓ Напиши *Да* для подтверждения или *Исправить*, чтобы внести правки.',
            $this->getConfirmKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peerId, array $data): void
    {
        match ($prevState) {
            UserState::FMWaitType->value => $this->startForceMajeureFlow($user, $peerId),
            UserState::FMWaitHours->value,
            UserState::FMWaitDays->value => $this->handleBackFromDuration($user, $peerId, $data),
            UserState::FMWaitReason->value => $this->handleBackFromReason($user, $peerId, $data),
            UserState::FMConfirm->value => $this->handleBackFromConfirm($user, $peerId, $data),
            default => $this->startForceMajeureFlow($user, $peerId),
        };
    }

    /**
     * Возврат из ввода длительности (часы/дни)
     */
    private function handleBackFromDuration(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::FMWaitType->value;
        $user->prev_state = UserState::FMWaitHours->value; // или WaitDays
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '⚠️ Укажи, в каких временных рамках будешь отсутствовать:',
            $this->getTypeKeyboard(),
            $peerId
        );
    }

    /**
     * Возврат из ввода причины
     */
    private function handleBackFromReason(TelegramUser $user, int $peerId, array $data): void
    {
        // Возвращаемся к вводу длительности
        $prevState = $data['prev_state'] ?? UserState::FMWaitType->value;
        $user->state = $prevState;
        $user->prev_state = UserState::WaitReason->value;
        $user->data = $data;
        $user->save();

        if ($prevState === UserState::FMWaitHours->value) {
            $this->sendMessage(
                '⏰ Сколько часов ты будешь отсутствовать? (укажи числом):',
                $this->getBackKeyboard(),
                $peerId
            );
        } elseif ($prevState === UserState::FMWaitDays->value) {
            $this->sendMessage(
                '📅 Сколько дней ты будешь отсутствовать? (укажи числом):',
                $this->getBackKeyboard(),
                $peerId
            );
        } else {
            // Если был "Весь день" — сразу к причине
            $this->sendMessage(
                '📝 Укажи причину отсутствия:',
                $this->getBackKeyboard(),
                $peerId
            );
        }
    }

    /**
     * Возврат из подтверждения
     */
    private function handleBackFromConfirm(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::FMWaitReason->value;
        $user->prev_state = UserState::FMConfirm->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '📝 Укажи причину отсутствия:',
            $this->getBackKeyboard(),
            $peerId
        );
    }

    /**
     * Сброс состояния пользователя
     */
    private function resetUserState(TelegramUser $user): void
    {
        $user->command = CommandType::None->value;
        $user->state = UserState::None->value;
        $user->prev_state = UserState::None->value;
        $user->data = null;
        $user->save();
    }

    /**
     * Логирование события форс-мажора в БД
     */
    private function logForceMajeureEvent(int $userId, string $description, string $username): void
    {
        $log = new UserLog;
        $log->name = $username;
        $log->telegram_user_id = $userId;
        $log->type = 'Форс-мажор';
        $log->description = $description;
        $log->date = now();
        $log->save();
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
                'parse_mode' => 'markdown',
            ]);
            Log::info('Force majeure message sent to admin');
        } catch (\Exception $e) {
            Log::error('Ошибка отправки админу: '.$e->getMessage());
        }
    }

    /**
     * Получение имени пользователя
     */
    private function getUserName(): string
    {
        $userInfo = $this->getUserInfo();

        return ($userInfo['first_name'] ?? 'Пользователь').' '.($userInfo['last_name'] ?? '');
    }

    // 🔽 КЛАВИАТУРЫ (VK Format) 🔽

    /**
     * Клавиатура выбора типа отсутствия
     */
    private function getTypeKeyboard(): string
    {
        return json_encode([
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '⏱️ В рамках нескольких часов',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => self::TYPE_HOURS], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🌞 Весь день',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => self::TYPE_ALL_DAY], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '📅 Несколько дней',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => self::TYPE_SEVERAL_DAYS], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'secondary',
                    ],
                ],
            ],
            'one_time' => false,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Клавиатура с кнопкой "Назад"
     */
    private function getBackKeyboard(): string
    {
        return json_encode([
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => 'назад'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'secondary',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'secondary',
                    ],
                ],
            ],
            'one_time' => false,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Клавиатура подтверждения
     */
    private function getConfirmKeyboard(): string
    {
        return json_encode([
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✅ Да',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => 'да'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'positive',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✏️ Исправить',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => 'исправить'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'negative',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏠 Главное меню',
                            'payload' => json_encode(['command' => 'start'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'secondary',
                    ],
                ],
            ],
            'one_time' => false,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Главное меню
     */
    private function getKeyboardStart(): string
    {
        return json_encode([
            'buttons' => [
                [
                    ['action' => ['type' => 'text', 'label' => '🚗 Опоздание', 'payload' => json_encode(['command' => 'delay'], JSON_UNESCAPED_UNICODE)], 'color' => 'primary'],
                    ['action' => ['type' => 'text', 'label' => '🤒 Заболел', 'payload' => json_encode(['command' => 'sick'], JSON_UNESCAPED_UNICODE)], 'color' => 'secondary'],
                ],
                [
                    ['action' => ['type' => 'text', 'label' => '🏥 Выхожу с больничного', 'payload' => json_encode(['command' => 'return-sick'], JSON_UNESCAPED_UNICODE)], 'color' => 'positive'],
                    ['action' => ['type' => 'text', 'label' => '📅 Изменения в расписании', 'payload' => json_encode(['command' => 'schedule'], JSON_UNESCAPED_UNICODE)], 'color' => 'primary'],
                ],
                [
                    ['action' => ['type' => 'text', 'label' => '⚠️ Форс-мажор', 'payload' => json_encode(['command' => 'force-majeure'], JSON_UNESCAPED_UNICODE)], 'color' => 'negative'],
                    ['action' => ['type' => 'text', 'label' => '💬 Другое', 'payload' => json_encode(['command' => 'other'], JSON_UNESCAPED_UNICODE)], 'color' => 'primary'],
                ],
            ],
            'one_time' => false,
        ], JSON_UNESCAPED_UNICODE);
    }
}
