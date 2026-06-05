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

    /**
     * @throws \JsonException
     */
    public function execute(): void
    {
        $userId = $this->fromId;
        $peer_id = $this->peerId;
        $text = trim($this->payload['text'] ?? '');

        // Получаем или создаем пользователя
        $user = TelegramUser::firstOrCreate(
            ['form_id' => $userId],
            [
                'name' => $this->getUserName(),
                'peer_id' => $peer_id,
                'state' => UserState::None->value,
                'command' => CommandType::ForceMajeure->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        $user->command = CommandType::ForceMajeure->value;
        if ($user->peer_id != $peer_id) {
            $user->peer_id = $peer_id;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        //        Log::info('ForceMajeureCommand', [
        //            'user_id' => $userId,
        //            'state' => $state,
        //            'prev_state' => $prevState,
        //            'text' => $text,
        //            'data' => $data,
        //        ]);

        // Обработка "Главное меню"
        $textLower = mb_strtolower($text);
        if ($textLower === 'start') {
            $this->resetUserState($user);
            $startCommand = $this->commandFactory->make('start', $peer_id, $userId, null);
            if ($startCommand) {
                $startCommand->execute();
            }

            return;
        }

        if ($textLower === 'назад') {
            $this->handleBack($user, $prevState, $peer_id, $data);

            return;
        }

        switch ($state) {
            case UserState::FMWaitType->value:
                $this->handleWaitType($user, $text, $data);
                break;
            case UserState::FMWaitHours->value:
                $this->handleWaitHours($user, $text, $data);
                break;
            case UserState::FMWaitDays->value:
                $this->handleWaitDays($user, $text, $data);
                break;
            case UserState::FMWaitReason->value:
                $this->handleWaitReason($user, $text, $data);
                break;
            case UserState::FMConfirm->value:
                $this->handleConfirm($user, $text, $data);
                break;
            default:
                $this->startForceMajeureFlow($user, $peer_id);
                break;
        }
    }

    /**
     * Начало потока: выбор типа отсутствия
     *
     * @throws \JsonException
     */
    private function startForceMajeureFlow(TelegramUser $user, int $peer_id): void
    {
        $user->state = UserState::FMWaitType->value;
        $user->prev_state = UserState::None->value;
        $user->data = [];
        $user->save();

        $this->sendMessage(
            'Укажи, в каких временных рамках будешь отсутствовать:',
            $this->getTypeKeyboard(),
            $peer_id
        );
    }

    /**
     * Обработка выбора типа отсутствия
     *
     * @throws \JsonException
     */
    private function handleWaitType(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;
        $validTypes = [self::TYPE_HOURS, self::TYPE_ALL_DAY, self::TYPE_SEVERAL_DAYS];

        if (!in_array($text, $validTypes)) {
            $this->sendMessage(
                '❌ Пожалуйста, выбери временной промежуток с помощью кнопок ниже:',
                $this->getTypeKeyboard(),
                $peer_id
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
                'Сколько часов ты будешь отсутствовать? (укажи числом):',
                $this->getTypeKeyboard(),
                $peer_id
            );

            return;
        }

        if ($text === self::TYPE_SEVERAL_DAYS) {
            $user->state = UserState::FMWaitDays->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                'Сколько дней ты будешь отсутствовать? (укажи числом):',
                $this->getTypeKeyboard(),
                $peer_id
            );

            return;
        }

        // Весь день — сразу переходим к причине
        $data['duration'] = self::TYPE_ALL_DAY;
        $user->state = UserState::FMWaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи причину отсутствия:',
            $this->getBackKeyboard(CommandType::ForceMajeure->value),
            $peer_id
        );
    }

    /**
     * Обработка ввода количества часов
     */
    private function handleWaitHours(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;

        if (!is_numeric($text)) {
            $this->sendMessage(
                '❌ Ошибка! Укажи количество часов числом (например: 3):',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );

            return;
        }

        $hours = (int) $text;
        if ($hours < 1 || $hours > 24) {
            $this->sendMessage(
                '❌ Ошибка! Укажи число от 1 до 24:',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );

            return;
        }

        $data['duration'] = $hours.' ч';
        $user->state = UserState::FMWaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи причину отсутствия:',
            $this->getBackKeyboard(CommandType::ForceMajeure->value),
            $peer_id
        );
    }

    /**
     * Обработка ввода количества дней
     */
    private function handleWaitDays(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;

        if (!is_numeric($text)) {
            $this->sendMessage(
                '❌ Ошибка! Укажи количество дней числом (например: 2):',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );

            return;
        }

        $days = (int) $text;
        if ($days < 1 || $days > 30) {
            $this->sendMessage(
                '❌ Ошибка! Укажи число от 1 до 30:',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );

            return;
        }

        $data['duration'] = $days.' дн.';
        $user->state = UserState::FMWaitReason->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи причину отсутствия:',
            $this->getBackKeyboard(CommandType::ForceMajeure->value),
            $peer_id
        );
    }

    /**
     * Обработка ввода причины
     */
    private function handleWaitReason(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Пожалуйста, напиши причину отсутствия:',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );

            return;
        }

        if (strlen($text) > 200) {
            $this->sendMessage(
                '❌ Ошибка! Текст слишком длинный, опиши более кратко (до 200 символов):',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );

            return;
        }

        $data['reason'] = $text;
        $data['prev_state'] = UserState::FMWaitReason->value;
        $user->state = UserState::FMConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "Проверь информацию:\n\n";
        $reply .= "Тип отсутствия: {$data['type']}\n";
        $reply .= "Длительность: {$data['duration']}\n";
        $reply .= "Причина: {$data['reason']}\n\n";
        $reply .= "Все верно? Нажми 'Да' или 'Исправить'";

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(CommandType::ForceMajeure->value),
            $peer_id
        );
    }

    /**
     * Обработка подтверждения
     */
    private function handleConfirm(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;
        $userId = $user->form_id;
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'да') {
            $userInfo = $this->getUserInfo();
            $customName = trim(($userInfo['first_name'] ?? '').' '.($userInfo['last_name'] ?? ''));
            $username = $userInfo['screen_name'] ?: ('id'.$userId);

            $msg = "ФOРС-МАЖОР\n\n";
            $msg .= "Сотрудник: {$customName}\n";
            $msg .= "Страница ВК: https://vk.com/{$username}\n";
            $msg .= "Тип: {$data['type']}\n";
            $msg .= "Длительность: {$data['duration']}\n";
            $msg .= "Причина: {$data['reason']}\n";
            $msg .= 'Время: '.date('d.m.Y H:i:s');

            $this->sendToAdminWithMarkdown($msg, $peer_id);

            $description = "Тип: `{$data['type']}`\n"
                ."Длительность: `{$data['duration']}`\n"
                ."Причина: `{$data['reason']}`\n";

            $this->logForceMajeureEvent($user->id, $description, $username);

            $this->resetUserState($user);

            $this->sendMessage(
                'Готово! Информация передана руководству.',
                $this->getMainKeyboard(),
                $peer_id
            );

            return;

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::FMWaitType->value;
            $user->prev_state = UserState::FMConfirm->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                'Укажи в каких временных рамках будешь отсутствовать:',
                $this->getMainKeyboard(),
                $peer_id
            );

            return;
        }

        $this->sendMessage(
            'Напиши *Да* для подтверждения или *Исправить*, чтобы внести правки.',
            $this->getConfirmKeyboard(CommandType::ForceMajeure->value),
            $peer_id
        );
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peer_id, array $data): void
    {
        match ($prevState) {
            UserState::FMWaitType->value => $this->startForceMajeureFlow($user, $peer_id),
            UserState::FMWaitHours->value,
            UserState::FMWaitDays->value => $this->handleBackFromDuration($user, $peer_id, $data),
            UserState::FMWaitReason->value => $this->handleBackFromReason($user, $peer_id, $data),
            UserState::FMConfirm->value => $this->handleBackFromConfirm($user, $peer_id, $data),
            default => $this->startForceMajeureFlow($user, $peer_id),
        };
    }

    /**
     * Возврат из ввода длительности (часы/дни)
     */
    private function handleBackFromDuration(TelegramUser $user, int $peer_id, array $data): void
    {
        $user->state = UserState::FMWaitType->value;
        $user->prev_state = UserState::FMWaitHours->value; // или WaitDays
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи, в каких временных рамках будешь отсутствовать:',
            $this->getMainKeyboard(),
            $peer_id
        );
    }

    /**
     * Возврат из ввода причины
     */
    private function handleBackFromReason(TelegramUser $user, int $peer_id, array $data): void
    {
        $prevState = $data['prev_state'] ?? UserState::FMWaitType->value;
        $user->state = $prevState;
        $user->prev_state = UserState::WaitReason->value;
        $user->data = $data;
        $user->save();

        if ($prevState === UserState::FMWaitHours->value) {
            $this->sendMessage(
                'Сколько часов ты будешь отсутствовать? (укажи числом):',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );
        } elseif ($prevState === UserState::FMWaitDays->value) {
            $this->sendMessage(
                'Сколько дней ты будешь отсутствовать? (укажи числом):',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );
        } else {
            $this->sendMessage(
                'Укажи причину отсутствия:',
                $this->getBackKeyboard(CommandType::ForceMajeure->value),
                $peer_id
            );
        }
    }

    /**
     * Возврат из подтверждения
     */
    private function handleBackFromConfirm(TelegramUser $user, int $peer_id, array $data): void
    {
        $user->state = UserState::FMWaitReason->value;
        $user->prev_state = UserState::FMConfirm->value;
        $user->data = $data;
        $user->save();

        $this->sendMessage(
            'Укажи причину отсутствия:',
            $this->getBackKeyboard(CommandType::ForceMajeure->value),
            $peer_id
        );
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
                            'label' => 'В рамках нескольких часов',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => self::TYPE_HOURS], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => 'Весь день',
                            'payload' => json_encode(['command' => 'force-majeure', 'text' => self::TYPE_ALL_DAY], JSON_UNESCAPED_UNICODE),
                        ],
                        'color' => 'primary',
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => 'Несколько дней',
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
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
