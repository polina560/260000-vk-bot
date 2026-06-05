<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

class SickCommand extends BaseCommand
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
                'command' => CommandType::Sick->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        // Устанавливаем команду
        $user->command = CommandType::Sick->value;

        // Обновляем peer_id если изменился
        if ($user->peer_id != $chat_id) {
            $user->peer_id = $chat_id;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        //        Log::info('SickCommand', [
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

        if (mb_strtolower($text) === 'назад') {
            $this->handleBack($user, $prevState, $chat_id, $data);

            return;
        }

        // Обработка в зависимости от состояния
        switch ($state) {
            case UserState::SickWaitDays->value:
                $this->handleWaitDays($user, $text, $data);
                break;

            case UserState::SickWaitRemote->value:
                $this->handleWaitRemote($user, $text, $data);
                break;

            case UserState::SickWaitComment->value:
                $this->handleWaitComment($user, $text, $data);
                break;

            case UserState::SickConfirm->value:
                $this->handleConfirm($user, $text, $data);
                break;

            default:
                // Начало процесса - запрос количества дней
                $user->state = UserState::SickWaitDays->value;
                $user->prev_state = UserState::None->value;
                $user->data = [];
                $user->save();

                $this->sendMessage(
                    'Подскажи, ориентировочно сколько дней ты будешь отсутствовать?',
                    $this->getBackKeyboard(CommandType::Sick->value),
                    $chat_id
                );
                break;
        }
    }

    /**
     * Обработка ввода количества дней
     */
    private function handleWaitDays(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Пожалуйста, укажи количество дней:',
                $this->getBackKeyboard(CommandType::Sick->value),
                $chat_id
            );

            return;
        }

        // Проверяем, что введено число
        if (!is_numeric($text)) {
            if (mb_strlen($text) > 50) {
                $this->sendMessage(
                    '❌ Ошибка! Слишком много символов, сократи сообщение.',
                    $this->getBackKeyboard(CommandType::Sick->value),
                    $chat_id
                );

                return;
            }
            $this->sendMessage(
                '❌ Ошибка! Укажи количество дней числом (например: 5):',
                $this->getBackKeyboard(CommandType::Sick->value),
                $chat_id
            );

            return;
        }

        $days = (int) $text;

        if ($days < 1 || $days > 99) {
            $this->sendMessage(
                '❌ Ошибка! Укажи число от 1 до 99:',
                $this->getBackKeyboard(CommandType::Sick->value),
                $chat_id
            );

            return;
        }

        $data['days'] = $days;
        $user->prev_state = UserState::SickWaitDays->value;
        $user->state = UserState::SickWaitRemote->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '💻 Будет ли возможность работать из дома?',
            $this->getRemoteWorkKeyboard(),
            $chat_id
        );
    }

    /**
     * Обработка выбора возможности работы из дома
     */
    private function handleWaitRemote(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;
        $textLower = mb_strtolower($text);

        $validOptions = [
            'yes',
            'mb',
            'no',
        ];

        $textMessage = [
            'yes' => 'да, буду работать из дома',
            'mb' => 'могу при необходимости',
            'no' => 'нет возможности/не позволяет состояние работать',
        ];

        if (!in_array($textLower, $validOptions)) {
            $this->sendMessage(
                '❌ Ошибка! Пожалуйста, выбери вариант из предложенных кнопок:',
                $this->getRemoteWorkKeyboard(),
                $chat_id
            );

            return;
        }

        $data['remote'] = $textMessage[$text];
        $user->prev_state = UserState::SickWaitRemote->value;
        $user->state = UserState::SickWaitComment->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Если хочешь, добавь комментарий (или нажми "Пропустить"):',
            $this->getCommentKeyboard(),
            $chat_id
        );
    }

    /**
     * Обработка комментария
     */
    private function handleWaitComment(TelegramUser $user, string $text, array $data): void
    {
        $chat_id = $user->peer_id;
        $textLower = mb_strtolower($text);

        if ($textLower === 'пропустить') {
            $data['comment'] = '';
        } else {
            if (mb_strlen($text) > 100) {
                $this->sendMessage(
                    '❌ Ошибка! Комментарий слишком длинный (максимум 100 символов):',
                    $this->getCommentKeyboard(),
                    $chat_id
                );

                return;
            }
            $data['comment'] = $text;
        }

        $user->prev_state = UserState::SickWaitComment->value;
        $user->state = UserState::SickConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "Проверь информацию:\n\n";
        $reply .= "Дней отсутствия: `{$data['days']}`\n";
        $reply .= "Работа из дома: `{$data['remote']}`\n";
        if (!empty($data['comment'])) {
            $reply .= "Комментарий: `{$data['comment']}`\n";
        }
        $reply .= "\nВсе верно? Напиши 'Да' или 'Исправить'";

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(CommandType::Sick->value),
            $chat_id
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
            $customName = $userInfo['first_name'].' '.$userInfo['last_name'];
            $username = $userInfo['screen_name'] ?: ('id'.$telegram_id);

            // Формируем сообщение для администратора
            $msg = "БОЛЬНИЧНЫЙ\n\n";
            $msg .= "Сотрудник: {$customName}\n";
            $msg .= "Страница ВК: https://vk.com/{$username}\n";
            $msg .= "Дней отсутствия: `{$data['days']}`\n";
            $msg .= "Работа из дома: `{$data['remote']}`\n";
            if (!empty($data['comment'])) {
                $msg .= "Комментарий: `{$data['comment']}`\n";
            }

            // Отправляем администратору
            $this->sendToAdminWithMarkdown($msg);

            $description = "Отсутствие (в днях): `{$data['days']}`\n"
            ."Работа из дома: `{$data['remote']}`\n"
            ."Комментарий: `{$data['comment']}`\n";

            $this->logSickChange($user->id, $description, $username);

            // Очищаем состояние пользователя
            $user->command = CommandType::None->value;
            $user->state = UserState::None->value;
            $user->prev_state = UserState::None->value;
            $user->data = null;
            $user->save();

            // Показываем главное меню
            $this->sendMessage(
                'Готово! Информация о больничном передана руководству.',
                $this->getMainKeyboard(),
                $chat_id
            );

            return;

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::SickWaitDays->value;
            $user->prev_state = UserState::None->value;
            $user->data = [];
            $user->save();

            $this->sendMessage(
                'Начнем заново. Сколько дней будешь отсутствовать?',
                $this->getBackKeyboard(CommandType::Sick->value),
                $chat_id
            );

            return;
        } else {
            $this->sendMessage(
                "Напиши 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                $this->getConfirmKeyboard(CommandType::Sick->value),
                $chat_id
            );

            return;
        }
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peerId, array $data): void
    {
        match ($prevState) {
            UserState::SickWaitDays->value => $this->handleBackFromDays($user, $peerId, $data),
            UserState::SickWaitRemote->value => $this->handleBackFromRemote($user, $peerId, $data),
            UserState::SickWaitComment->value => $this->handleBackFromComment($user, $peerId, $data),
            default => null,
        };
    }

    /**
     * Возврат из ввода количества дней
     */
    private function handleBackFromDays(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::SickWaitDays->value;
        $user->prev_state = UserState::None->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Подскажи, ориентировочно сколько дней ты будешь отсутствовать?',
            $this->getBackKeyboard(CommandType::Sick->value),
            $peerId
        );
    }

    /**
     * Возврат из вопроса о возможности удалённой работы
     */
    private function handleBackFromRemote(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::SickWaitRemote->value;
        $user->prev_state = UserState::None->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            '💻 Будет ли возможность работать из дома?',
            $this->getRemoteWorkKeyboard(),
            $peerId
        );
    }

    /**
     * Возврат из ввода комментария
     */
    private function handleBackFromComment(TelegramUser $user, int $peerId, array $data): void
    {
        $user->state = UserState::SickWaitComment->value;
        $user->prev_state = UserState::None->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Если хочешь, добавь комментарий (или нажми "Пропустить"):',
            $this->getCommentKeyboard(),
            $peerId
        );
    }

    /**
     * Логирование изменения расписания в БД
     */
    private function logSickChange(int $userId, string $description, string $username): void
    {
        $log = new UserLog;
        $log->telegram_user_id = $userId;
        $log->name = $username;
        $log->type = 'Болезнь';
        $log->description = $description;
        $log->date = now();
        $log->save();
    }

    /**
     * Клавиатура для выбора работы из дома
     */
    private function getRemoteWorkKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => 'Да, буду работать из дома',
                            'payload' => json_encode(['command' => 'sick', 'text' => 'yes']),
                        ],
                        'color' => 'positive',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => 'Могу при необходимости',
                            'payload' => json_encode(['command' => 'sick', 'text' => 'mb']),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => 'Нет возможности/не позволяет состояние',
                            'payload' => json_encode(['command' => 'sick', 'text' => 'no']),
                        ],
                        'color' => 'negative',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'sick', 'text' => 'назад']),
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
     * Клавиатура для комментария
     */
    private function getCommentKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '⏭️ Пропустить',
                            'payload' => json_encode(['command' => 'sick', 'text' => 'пропустить']),
                        ],
                        'color' => 'secondary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '◀️ Назад',
                            'payload' => json_encode(['command' => 'sick', 'text' => 'назад']),
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
