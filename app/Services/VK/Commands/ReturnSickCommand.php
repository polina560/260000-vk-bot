<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\AdminUser;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

class ReturnSickCommand extends BaseCommand
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
                'command' => CommandType::None->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        // Проверяем, что команда именно ReturnSick
        if ($user->command !== CommandType::ReturnSick->value && $user->state !== UserState::None->value) {
//            Log::warning('ReturnSickCommand: пользователь в другой команде', [
//                'user_id' => $telegram_id,
//                'current_command' => $user->command,
//                'current_state' => $user->state,
//            ]);

            return;
        }

        // Устанавливаем команду
        $user->command = CommandType::ReturnSick->value;

        // Обновляем peer_id если изменился
        if ($user->peer_id != $chat_id) {
            $user->peer_id = $chat_id;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

//        Log::info('ReturnSickCommand', [
//            'user_id' => $telegram_id,
//            'state' => $state,
//            'prev_state' => $prevState,
//            'text' => $text,
//            'data' => $data,
//        ]);

        $textLower = mb_strtolower($text);
        if ($textLower === 'главное меню' || $textLower === 'меню' || $textLower === 'start') {
            $this->resetUserState($user);
            $startCommand = $this->commandFactory->make('start', $chat_id, $telegram_id, null);
            if ($startCommand) {
                $startCommand->execute();
            }

            return;
        }

        if (mb_strtolower($text) === 'back') {
            $this->handleBack($user, $prevState, $chat_id, $data);

            return;
        }

        // Обработка в зависимости от состояния
        switch ($state) {
            case UserState::ReturnSickWaitData->value:
                $this->handleWaitData($user, $text, $data);
                break;

            case UserState::ReturnSickWaitManualDate->value:
                $this->handleWaitManualDate($user, $text, $data);
                break;

            default:
                // Начало процесса - запрос даты
                $user->state = UserState::ReturnSickWaitData->value;
                $user->prev_state = UserState::None->value;
                $user->data = [];
                $user->save();

                $this->sendMessage(
                    'Укажи дату выхода с больничного:',
                    $this->getDateKeyboard(),
                    $chat_id
                );
                break;
        }
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peerId, array $data): void
    {
        match ($prevState) {
            UserState::ReturnSickWaitData->value       => $this->handleBackFromWaitData($user, $peerId, $data),
            UserState::ReturnSickWaitManualDate->value => $this->handleBackFromManualDate($user, $peerId, $data),
            default                                    => null,
        };
    }

    /**
     * Возврат из ожидания даты выхода с больничного (кнопки)
     */
    private function handleBackFromWaitData(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::ReturnSickWaitData->value;
        $user->prev_state = UserState::None->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи дату выхода с больничного:',
            $this->getDateKeyboard(),
            $peerId
        );
    }

    /**
     * Возврат из ожидания ручного ввода даты
     */
    private function handleBackFromManualDate(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::ReturnSickWaitManualDate->value;
        $user->prev_state = UserState::None->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Напиши дату выхода с больничного в формате ДД.ММ',
            $this->getBackKeyboard(CommandType::ReturnSick->value),
            $peerId
        );
    }

    /**
     * Обработка выбора даты
     */
    private function handleWaitData(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;
        $telegram_id = $user->form_id;
        $textLower = $text;

        // Получаем выбранную дату
        $date = null;

        Log::info('TEXTLOWER', [
            'user_id' => $telegram_id,
            'textLower' => $textLower,
        ]);

        // Проверяем payload для кнопок
        if (isset($textLower)) {

            if ($textLower === 'today') {
                $date = date('d.m');
            } elseif ($textLower === 'tomorrow') {
                $date = date('d.m', strtotime('+1 day'));
            } elseif ($textLower === 'manual') {
                // Переход к ручному вводу
                $data['prev_state'] = UserState::ReturnSickWaitData->value;
                $user->state = UserState::ReturnSickWaitManualDate->value;
                $user->data = $data;
                $user->save();

                $this->sendMessage(
                    "Напиши дату выхода с больничного в формате ДД.ММ\n\nНапример: 15.05",
                    $this->getBackKeyboard(CommandType::ReturnSick->value),
                    $chat_id
                );

                return;
            }

        }

        // Если дата не выбрана - ошибка
        if (!$date) {
            Log::warning('Неверный выбор даты', ['text' => $text, 'payload' => $this->payload]);
            $this->sendMessage(
                '❌ Пожалуйста, выбери дату с помощью кнопок ниже:',
                $this->getDateKeyboard(),
                $chat_id
            );

            return;
        }

        $userInfo = $this->getUserInfo();
        $customName = $user->name;
        $username = $userInfo['screen_name'] ?: ('id'.$user->form_id);

        $msg = "ВЫХОД С БОЛНИЧНОГО\n\n";
        $msg .= "Сотрудник: {$customName}\n";
        $msg .= "Страница ВК: https://vk.com/{$username}\n";
        $msg .= "Дата выхода: `{$date}`\n";
        $msg .= 'Время: '.date('d.m.Y H:i:s');
        // Отправляем админу
        $this->sendToAdminWithMarkdown($msg);

        // Сохраняем в лог
        Log::info('Запись о выходе с больничного', [
            'user_id' => $telegram_id,
            'user_name' => $user->name,
            'date' => $date,
        ]);

        // Очищаем состояние
        $user->command = CommandType::None->value;
        $user->state = UserState::None->value;
        $user->prev_state = UserState::None->value;
        $user->data = null;
        $user->save();

        // Показываем главное меню
        $this->sendMessage(
            'Готово! Информация о выходе с больничного передана руководству.',
            $this->getMainKeyboard(),
            $chat_id
        );
    }

    /**
     * Обработка ручного ввода даты
     */
    private function handleWaitManualDate(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;
        $telegram_id = $user->form_id;

        // Проверяем формат даты ДД.ММ
        if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])$/', $text)) {
            $this->sendMessage(
                "❌ Неверный формат. Введи дату в формате ДД.ММ\n\nНапример: 15.05",
                $this->getBackKeyboard(CommandType::ReturnSick->value),
                $chat_id
            );

            return;
        }

        [$day, $month] = explode('.', $text);
        $year = date('Y');

        // Проверяем существование даты
        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            $this->sendMessage(
                '❌ Такой даты не существует. Попробуй снова.',
                $this->getBackKeyboard(CommandType::ReturnSick->value),
                $chat_id
            );

            return;
        }

        // Проверяем, что дата не в прошлом
        $inputDate = strtotime("$year-$month-$day");
        $today = strtotime(date('Y-m-d'));

        if ($inputDate < $today) {
            $this->sendMessage(
                '❌ Дата должна быть сегодня или позже.',
                $this->getBackKeyboard(CommandType::ReturnSick->value),
                $chat_id
            );

            return;
        }

        $date = $text;
        $userInfo = $this->getUserInfo();
        $customName = $user->name;
        $username = $userInfo['screen_name'] ?: ('id'.$user->form_id);

        $msg = "Выход с больничного\n\n";
        $msg .= "Сотрудник: {$customName}\n";
        $msg .= "Username: @{$username}\n";
        $msg .= "Дата выхода: `{$date}`\n";
        // Отправляем админу
        $this->sendToAdminWithMarkdown($msg);

        $description = "Дата выхода: `{$date}`\n";
        $this->logReturnSickEvent($user->id, $description, $username);

        // Очищаем состояние
        $user->command = CommandType::None->value;
        $user->state = UserState::None->value;
        $user->prev_state = UserState::None->value;
        $user->data = null;
        $user->save();

        // Показываем главное меню
        $this->sendMessage(
            'Готово! Информация о выходе с больничного передана руководству.',
            $this->getMainKeyboard(),
            $chat_id
        );
    }

    /**
     * Логирование события "другое" в БД
     */
    private function logReturnSickEvent(int $userId, string $description, string $username): void
    {
        $log = new UserLog;
        $log->name = $username;
        $log->telegram_user_id = $userId;
        $log->type = 'Выход с больничного';
        $log->description = $description;
        $log->date = now();
        $log->save();
    }


    /**
     * Клавиатура выбора даты
     */
    private function getDateKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'callback',
                            'label' => 'Сегодня',
                            'payload' => json_encode(['command' => 'return-sick', 'text' => 'today']),
                        ],
                        'color' => 'primary',
                    ],
                    [
                        'action' => [
                            'type' => 'callback',
                            'label' => 'Завтра',
                            'payload' => json_encode(['command' => 'return-sick', 'text' => 'tomorrow']),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'callback',
                            'label' => 'Выбрать дату вручную',
                            'payload' => json_encode(['command' => 'return-sick', 'text' => 'manual']),
                        ],
                        'color' => 'secondary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'callback',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'return-sick', 'text' => 'back']),
                        ],
                        'color' => 'secondary',
                    ],
                    [
                        'action' => [
                            'type' => 'callback',
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
