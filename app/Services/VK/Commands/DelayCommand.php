<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\AdminUser;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

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
                'command' => CommandType::Delay->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        $user->command = CommandType::Delay->value;
        // Обновляем peer_id если изменился
        if ($user->peer_id != $chat_id) {
            $user->peer_id = $chat_id;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        //        Log::info('DelayCommand', [
        //            'user_id' => $telegram_id,
        //            'state' => $state,
        //            'prev_state' => $prevState,
        //            'text' => $text,
        //            'data' => $data,
        //        ]);

        $textLower = mb_strtolower($text);
        if ($textLower === 'start') {
            $this->resetUserState($user);
            $startCommand = $this->commandFactory->make('start', $chat_id, $telegram_id, null);
            if ($startCommand) {
                $startCommand->execute();
            }

            return;
        }

        if (mb_strtolower($text) === 'назад') {
            $this->handleBack($user, $prevState, $chat_id, $data);

            return;
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
                $this->startDelayFlow($user, $chat_id);
                break;
        }
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peerId, array $data): void
    {
        match ($prevState) {
            UserState::WaitReason->value => $this->handleBackFromReason($user, $peerId, $data),
            UserState::WaitTime->value => $this->handleBackFromTime($user, $peerId, $data),
            default => $this->startDelayFlow($user, $peerId),
        };
    }

    /**
     * возврат к вводу времени
     */
    private function handleBackFromReason(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::WaitTime->value;
        $user->prev_state = UserState::WaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи на сколько минут ты опаздываешь:',
            $this->getTimeKeyboard(CommandType::Delay->value),
            $peerId
        );
    }

    /**
     * возврат в начало (главное меню / сброс)
     */
    private function handleBackFromTime(TelegramUser $user, int $peerId, array $data): void
    {
        $this->resetUserState($user);
    }

    private function startDelayFlow(TelegramUser $user, int $peerId): void
    {
        $user->state = UserState::WaitTime->value;
        $user->prev_state = UserState::None->value;
        $user->data = [];
        $user->save();

        $this->sendMessage(
            'Укажи на сколько минут ты опаздываешь:',
            $this->getTimeKeyboard(CommandType::Delay->value),
            $peerId
        );
    }

    /**
     * Обработка ввода причины опоздания
     */
    private function handleWaitReason(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Пожалуйста, напиши причину опоздания:',
                $this->getBackKeyboard(CommandType::Delay->value),
                $chat_id
            );

            return;
        }

        if (strlen($text) > 150) {
            $this->sendMessage(
                '❌ Ошибка! Укажи более краткую причину опоздания (до 150 символов):',
                $this->getBackKeyboard(CommandType::Delay->value),
                $chat_id
            );

            return;
        }

        $data['reason'] = $text;

        $user->prev_state = UserState::WaitReason->value;
        $user->state = UserState::Confirm->value;
        $user->data = $data;
        $user->save();

        $reply = "Проверь информацию: \n\n";
        $reply .= 'Опоздание: '.($data['delay_minutes'] ?? '?')." мин\n";
        $reply .= 'Причина: '.$data['reason']."\n\n";
        $reply .= "Все верно? Нажми 'Да' или 'Исправить'";

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(CommandType::Delay->value),
            $chat_id
        );
    }

    /**
     * Обработка ввода времени опоздания
     */
    private function handleWaitTime(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'custom') {
            $data['waiting_for_custom'] = true;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                'Введите количество минут вручную (числом):',
                $this->getBackKeyboard(CommandType::Delay->value),
                $chat_id
            );

            return;
        }

        if (!empty($data['waiting_for_custom'])) {
            unset($data['waiting_for_custom']);
        }

        if (!is_numeric($text)) {
            $this->sendMessage(
                '❌ Ошибка! Укажи время опоздания в минутах числом (например: 15):',
                $this->getTimeKeyboard(CommandType::Delay->value),
                $chat_id
            );

            return;
        }

        $minutes = (int) $text;

        if ($minutes < 1 || $minutes > 999) {
            $this->sendMessage(
                '❌ Ошибка! Укажи число от 1 до 999 минут:',
                $this->getTimeKeyboard(CommandType::Delay->value),
                $chat_id
            );

            return;
        }

        $data['delay_minutes'] = $minutes;
        $user->prev_state = UserState::WaitTime->value;
        $user->state = UserState::WaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи причину опоздания:',
            $this->getBackKeyboard(CommandType::Delay->value),
            $chat_id
        );
    }

    /**
     * Обработка подтверждения
     */
    private function handleConfirm(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;
        $telegram_id = $user->form_id;
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'да') {
            // Получаем информацию о пользователе
            $userInfo = $this->getUserInfo();
            $customName = $userInfo['first_name'].' '.$userInfo['last_name'];
            $username = $userInfo['screen_name'] ?: ('id'.$telegram_id);

            // Формируем сообщение для администратора
            $msg = "ОПОЗДАНИЕ\n\n";
            $msg .= "Сотрудник: {$customName}\n";
            $msg .= "Страница ВК: https://vk.com/{$username}\n";
            $msg .= "Опоздание: {$data['delay_minutes']} мин\n";
            $msg .= "Причина: {$data['reason']}\n";

            // Отправляем администратору
            $this->sendToAdminWithMarkdown($msg, $peer_id);

            $description = "Опоздание на: `{$data['delay_minutes']} мин`\n"
                ."Причина: `{$data['reason']}`\n";

            $this->logDelayEvent($user->id, $description, $username);
            $this->resetUserState($user);

            // Показываем главное меню
            $this->sendMessage('Готово! Информация об опоздании передана руководству.', $this->getMainKeyboard(), $peer_id);

            return;

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::WaitTime->value;
            $user->prev_state = UserState::None->value;
            $user->data = [];
            $user->save();

            $this->sendMessage(
                'Начнем заново. Укажи на сколько минут ты опаздываешь:',
                $this->getTimeKeyboard(CommandType::Delay->value),
                $peer_id
            );

            return;
        } else {
            $this->sendMessage(
                "Напиши 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                $this->getConfirmKeyboard(CommandType::Delay->value),
                $peer_id
            );

            return;
        }
    }

    /**
     * Логирование события форс-мажора в БД
     */
    private function logDelayEvent(int $userId, string $description, string $username): void
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
     * Клавиатура с вариантами времени
     */
    private function getTimeKeyboard(string $command): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '10',
                            'payload' => json_encode(['command' => $command, 'text' => '10']),
                        ],
                        'color' => 'primary',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '15',
                            'payload' => json_encode(['command' => $command, 'text' => '15']),
                        ],
                        'color' => 'primary',
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '30',
                            'payload' => json_encode(['command' => $command, 'text' => '30']),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '✏️ Своё значение',
                            'payload' => json_encode(['command' => $command, 'text' => 'custom'], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'secondary',
                    ],
                ],
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


}
