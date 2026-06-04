<?php

namespace App\Services\VK\Commands;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\AdminUser;
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
            $this->handleBack($user, $prevState, $peerId, $data);

            return;
        }

        // обработка по состояниям
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
            'Укажи, что хочешь сообщить:',
            $this->getBackKeyboard(CommandType::Other->value),
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
                $this->getBackKeyboard(CommandType::Other->value),
                $peerId
            );

            return;
        }

        if (strlen($text) > 200) {
            $this->sendMessage(
                '❌ Ошибка! Текст слишком длинный, сократи его (до 200 символов):',
                $this->getBackKeyboard(CommandType::Other->value),
                $peerId
            );

            return;
        }

        $data['text'] = $text;
        $data['prev_state'] = UserState::OtherWaitText->value;
        $user->state = UserState::OtherConfirm->value;
        $user->data = $data;
        $user->save();

        $reply = "Проверь информацию:\n\n";
        $reply .= $data['text']."\n\n";
        $reply .= 'Все верно? Напиши *Да* или *Исправить*';

        $this->sendMessage(
            $reply,
            $this->getConfirmKeyboard(CommandType::Other->value),
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

            $msg = "ОПОВЕЩЕНИЕ\n\n";
            $msg .= "Сотрудник: {$customName}\n";
            $msg .= "Страница ВК: https://vk.com/{$username}\n";
            $msg .= "Сообщение:\n`{$data['text']}`\n";

            $this->sendToAdminWithMarkdown($msg);

            $description = "Сообщение:\n`{$data['text']}`\n";
            $this->logOtherEvent($user->id, $msg, $description);

            $this->resetUserState($user);

            $this->sendMessage(
                'Готово! Информация передана руководству.',
                $this->getMainKeyboard(),
                $peerId
            );

            return;

        } elseif ($textLower === 'исправить') {
            $user->state = UserState::OtherWaitText->value;
            $user->prev_state = UserState::OtherConfirm->value;
            $user->data = $data;
            $user->save();

            $this->sendMessage(
                'Хорошо, напиши сообщение заново:',
                $this->getBackKeyboard(CommandType::Other->value),
                $peerId
            );

            return;
        }

        $this->sendMessage(
            'Напиши *Да* для подтверждения или *Исправить*, чтобы внести правки.',
            $this->getConfirmKeyboard(CommandType::Other->value),
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
            'Укажи, что хочешь сообщить:',
            $this->getBackKeyboard(CommandType::Other->value),
            $peerId
        );
    }

    /**
     * Логирование события "другое" в БД
     */
    private function logOtherEvent(int $userId, string $description, string $username): void
    {
        $log = new UserLog;
        $log->name = $username;
        $log->telegram_user_id = $userId;
        $log->type = 'Другое';
        $log->description = $description;
        $log->date = now();
        $log->save();
    }


}
