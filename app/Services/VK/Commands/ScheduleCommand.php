<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\TelegramUser;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

class ScheduleCommand extends BaseCommand
{
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
                'command' => CommandType::Schedule->value,
                'prev_state' => UserState::None->value,
                'data' => [],
            ]
        );

        // Обновляем команду пользователя
        $user->command = CommandType::Schedule->value;
        if ($user->peer_id != $peer_id) {
            $user->peer_id = $peer_id;
        }
        $user->save();

        $state = $user->state;
        $prevState = $user->prev_state;
        $data = $user->getUserData();

        //        Log::info('ScheduleCommand', [
        //            'user_id' => $userId,
        //            'state' => $state,
        //            'prev_state' => $prevState,
        //            'text' => $text,
        //            'data' => $data,
        //        ]);

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
            $this->handleBack($user, $prevState, $peer_id);

            return;
        }

        switch ($state) {
            case UserState::ScheduleWaitText->value:
                $this->handleWaitText($user, $text, $data);
                break;
            case UserState::ScheduleConfirm->value:
                $this->handleConfirm($user, $text, $data);
                break;
            default:
                $this->startScheduleFlow($user, $peer_id);
                break;
        }
    }

    /**
     * Начало потока: запрос текста об изменениях
     */
    private function startScheduleFlow(TelegramUser $user, int $peer_id): void
    {
        $user->state = UserState::ScheduleWaitText->value;
        $user->prev_state = UserState::None->value;
        $user->data = [];
        $user->save();

        $this->sendMessage(
            'Укажи свое новое расписание или то, что изменилось в старом:',
            $this->getBackKeyboard(CommandType::Schedule->value),
            $peer_id
        );
    }

    /**
     * Обработка ввода текста об изменениях
     */
    private function handleWaitText(TelegramUser $user, string $text, array $data): void
    {
        $peer_id = $user->peer_id;

        if (empty($text)) {
            $this->sendMessage(
                '❌ Опиши, что изменилось в расписании:',
                $this->getBackKeyboard(CommandType::Schedule->value),
                $peer_id
            );

            return;
        }

        if (strlen($text) > 200) {
            $this->sendMessage(
                '❌ Ошибка! Текст слишком длинный, опиши более кратко (до 200 символов):',
                $this->getBackKeyboard(CommandType::Schedule->value),
                $peer_id
            );

            return;
        }

        $data['schedule'] = $text;
        $user->prev_state = UserState::ScheduleWaitText->value;
        $user->state = UserState::ScheduleConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "Проверь информацию:\n\n";
        $reply .= 'Изменения в расписании: '.$data['schedule']."\n\n";
        $reply .= "Все верно? Нажми 'Да' или 'Исправить'";

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(CommandType::Schedule->value),
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

            $msg = "ИЗМЕНЕНИЕ В РАСПИСАНИИ\n\n";
            $msg .= "Сотрудник: {$customName}\n";
            $msg .= "Страница ВК: https://vk.com/{$username}\n";
            $msg .= "Изменения: {$data['schedule']}\n";

            $this->sendToAdminWithMarkdown($msg, $peer_id);

            $description = "Изменения:\n`{$data['schedule']}`\n";
            $this->logScheduleChange($user->id, $description, $username);

            $this->resetUserState($user);

            $this->sendMessage(
                'Готово! Информация передана руководству.',
                $this->getMainKeyboard(),
                $peer_id
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
                $peer_id
            );

            return;
        }

        $this->sendMessage(
            '❓ Напиши *Да* для подтверждения или *Исправить*, чтобы внести правки.',
            $this->getConfirmKeyboard(CommandType::Schedule->value),
            $peer_id
        );
    }

    /**
     * Обработка кнопки "Назад"
     */
    private function handleBack(TelegramUser $user, string $prevState, int $peer_id): void
    {
        if ($prevState === UserState::ScheduleWaitText->value) {
            // Если были в wait_text — возвращаемся в начало
            $this->startScheduleFlow($user, $peer_id);

            return;
        }

        // Если были в confirm — возвращаемся к вводу текста
        $user->state = UserState::ScheduleWaitText->value;
        $user->prev_state = UserState::ScheduleConfirm->value;
        $user->save();

        $this->sendMessage(
            'Укажи свое новое расписание или то, что изменилось в старом:',
            $this->getBackKeyboard(CommandType::Schedule->value),
            $peer_id
        );
    }

    /**
     * Логирование изменения расписания в БД
     */
    private function logScheduleChange(int $userId, string $description, string $username): void
    {
        $log = new UserLog;
        $log->name = $username;
        $log->telegram_user_id = $userId;
        $log->type = 'Изменение в расписании';
        $log->description = $description;
        $log->date = now();
        $log->save();
    }
}
