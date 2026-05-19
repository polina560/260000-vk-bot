<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

class OtherCommand extends BaseCommand
{
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
                'command' => CommandType::Other->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        $user->command = CommandType::Other->value;
        if ($user->peer_id != $peerId) {
            $user->peer_id = $peerId;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        Log::info('OtherCommand', [
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
            UserState::OtherWaitText->value => $this->handleWaitText($user, $text, $data),
            UserState::OtherConfirm->value => $this->handleConfirm($user, $text, $data),
            default => $this->startOtherFlow($user, $peerId),
        };
    }

    /**
     * Начало потока: запрос текста сообщения
     */
    private function startOtherFlow(TelegramUser $user, int $peerId): void
    {
        $user->state = UserState::OtherWaitText->value;
        $user->prev_state = UserState::None->value;
        $user->data = [];
        $user->save();

        $this->sendMessage(
            '💬 Укажи, что хочешь сообщить:',
            $this->getBackKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка ввода текста сообщения
     */
    private function handleWaitText(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Пожалуйста, напиши своё сообщение:',
                $this->getBackKeyboard(),
                $peerId
            );
            return;
        }

        if (strlen($text) > 200) {
            $this->sendMessage(
                '❌ Ошибка! Текст слишком длинный, сократи его (до 200 символов):',
                $this->getBackKeyboard(),
                $peerId
            );
            return;
        }

        $data['text'] = $text;
        $data['prev_state'] = UserState::OtherWaitText->value;
        $user->state = UserState::OtherConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "📋 Проверь информацию:\n\n";
        $reply .= $data['text'] . "\n\n";
        $reply .= "✅ Все верно? Напиши *Да* или *Исправить*";

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
            $customName = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
            $username = $userInfo['screen_name'] ?: ('id' . $userId);

            $msg = "ℹ️ *Просто оповещение*\n\n";
            $msg .= "👤 Сотрудник: {$customName}\n";
            $msg .= "🔗 Ссылка: https://vk.com/{$username}\n";
            $msg .= "💬 Сообщение:\n`{$data['text']}`\n";
            $msg .= '🕐 Время: ' . date('d.m.Y H:i:s');

            $this->sendToAdminWithMarkdown($msg);
            $this->logOtherEvent($userId, $msg);

            $this->resetUserState($user);

            $this->sendMessage(
                '✅ Готово! Информация передана руководству.',
                $this->getKeyboardStart(),
                $peerId
            );
            return;

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::OtherWaitText->value;
            $user->prev_state = UserState::OtherConfirm->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                '🔄 Хорошо, напиши сообщение заново:',
                $this->getBackKeyboard(),
                $peerId
            );
            return;
        }

        $this->sendMessage(
            "❓ Напиши *Да* для подтверждения или *Исправить*, чтобы внести правки.",
            $this->getConfirmKeyboard(),
            $peerId
        );
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peerId, array $data): void
    {
        if ($prevState === UserState::OtherWaitText->value) {
            // Если были в wait_text — возвращаемся к началу
            $this->startOtherFlow($user, $peerId);
            return;
        }

        // Если были в confirm — возвращаемся к вводу текста
        $user->state = UserState::OtherWaitText->value;
        $user->prev_state = UserState::OtherConfirm->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '💬 Укажи, что хочешь сообщить:',
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
     * Логирование события "другое" в БД
     */
    private function logOtherEvent(int $userId, string $description): void
    {
        $log = new UserLog();
        $log->telegram_user_id = $userId;
        $log->type = "Другое";
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
            Log::info('Other message sent to admin');
        } catch (\Exception $e) {
            Log::error('Ошибка отправки админу: ' . $e->getMessage());
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

    // 🔽 КЛАВИАТУРЫ (VK Format) 🔽

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
                            'payload' => json_encode(['command' => 'other', 'text' => 'назад'], JSON_UNESCAPED_UNICODE),
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
                            'payload' => json_encode(['command' => 'other', 'text' => 'да'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'positive',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✏️ Исправить',
                            'payload' => json_encode(['command' => 'other', 'text' => 'исправить'], JSON_UNESCAPED_UNICODE),
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
