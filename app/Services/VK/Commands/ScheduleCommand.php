<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\AdminUser;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

class ScheduleCommand extends BaseCommand
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
                'command' => CommandType::Schedule->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        // Обновляем команду пользователя
        $user->command = CommandType::Schedule->value;
        if ($user->peer_id != $peerId) {
            $user->peer_id = $peerId;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        Log::info('ScheduleCommand', [
            'user_id' => $userId,
            'state' => $state,
            'prev_state' => $prevState,
            'text' => $text,
            'data' => $data,
        ]);

        // Обработка "Главное меню"
        $textLower = mb_strtolower($text);
        if ($textLower === 'главное меню' || $textLower === 'меню' || $textLower === 'start') {
            $this->resetUserState($user);
            $startCommand = $this->commandFactory->make('start', $peerId, $userId, null);
            if ($startCommand) {
                $startCommand->execute();
            }
            return;
        }

        // Обработка кнопки "Назад"
        if ($textLower === 'назад') {
            $this->handleBack($user, $prevState, $peerId);
            return;
        }

        // обработка по состояниям
        match ($state) {
            UserState::ScheduleWaitText->value => $this->handleWaitText($user, $text, $data),
            UserState::ScheduleConfirm->value => $this->handleConfirm($user, $text, $data),
            default => $this->startScheduleFlow($user, $peerId),
        };
    }

    /**
     * Начало потока: запрос текста об изменениях
     */
    private function startScheduleFlow(TelegramUser $user, int $peerId): void
    {
        $user->state = UserState::ScheduleWaitText->value;
        $user->prev_state = UserState::None->value;
        $user->data = [];
        $user->save();

        $this->sendMessage(
            'Укажи свое новое расписание или то, что изменилось в старом:',
            $this->getBackKeyboard(CommandType::Schedule->value),
            $peerId
        );
    }

    /**
     * Обработка ввода текста об изменениях
     */
    private function handleWaitText(TelegramUser $user, string $text, array $data): void
    {
        $peerId = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Опиши, что изменилось в расписании:',
                $this->getBackKeyboard(CommandType::Schedule->value),
                $peerId
            );
            return;
        }

        if (strlen($text) > 200) {
            $this->sendMessage(
                '❌ Ошибка! Текст слишком длинный, опиши более кратко (до 200 символов):',
                $this->getBackKeyboard(CommandType::Schedule->value),
                $peerId
            );
            return;
        }

        $data['schedule'] = $text;
        $user->prev_state = UserState::ScheduleWaitText->value;
        $user->state = UserState::ScheduleConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "Проверь информацию:\n\n";
        $reply .= "Изменения в расписании:\n";
        $reply .= 'Изменения:' . $data['schedule'] . "\n\n";
        $reply .= "Все верно? Напиши *Да* или *Исправить*";

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(CommandType::Schedule->value),
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

            $msg = "Изменение в расписании\n\n";
            $msg .= "Сотрудник: {$customName}\n";
            $msg .= "Username: @{$username}\n";
            $msg .= "Изменения:\n`{$data['schedule']}`\n";

            $this->sendToAdminWithMarkdown($msg);

            $description = "Изменения:\n`{$data['schedule']}`\n";
            $this->logScheduleChange($user->id, $description, $username);

            $this->resetUserState($user);

            $this->sendMessage(
                'Готово! Информация передана руководству.',
                $this->getMainKeyboard(),
                $peerId
            );
            return;

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::ScheduleWaitText->value;
            $user->prev_state = UserState::ScheduleConfirm->value;
            $user->data = $data; // сохраняем данные для редактирования
            $user->save();

            $this->sendMessage(
                'Хорошо, напиши изменения заново:',
                $this->getBackKeyboard(CommandType::Schedule->value),
                $peerId
            );
            return;
        }

        $this->sendMessage(
            "❓ Напиши *Да* для подтверждения или *Исправить*, чтобы внести правки.",
            $this->getConfirmKeyboard(CommandType::Schedule->value),
            $peerId
        );
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peerId): void
    {
        if ($prevState === UserState::ScheduleWaitText->value) {
            // Если были в wait_text — возвращаемся в начало
            $this->startScheduleFlow($user, $peerId);
            return;
        }

        // Если были в confirm — возвращаемся к вводу текста
        $user->state = UserState::ScheduleWaitText->value;
        $user->prev_state = UserState::ScheduleConfirm->value;
        $user->save();

        $this->sendMessage(
            'Укажи свое новое расписание или то, что изменилось в старом:',
            $this->getBackKeyboard(CommandType::Schedule->value),
            $peerId
        );
    }

    /**
     * Логирование изменения расписания в БД
     */
    private function logScheduleChange(int $userId, string $description, string $username): void
    {
        $log = new UserLog();
        $log->name = $username;
        $log->telegram_user_id = $userId;
        $log->type = "Изменение в расписании";
        $log->description = $description;
        $log->date = now();
        $log->save();
    }


}
