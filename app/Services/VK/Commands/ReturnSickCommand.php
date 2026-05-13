<?php

namespace App\Services\VK\Commands;

class ReturnSickCommand extends BaseCommand
{

    public function execute(): void
    {
        $message = " Уведомление о выходе с больничного\n\n";

        $this->sendMessage($message);

        // Здесь можно сохранить состояние, что пользователь в процессе заполнения
        // и следующие его сообщения будут относиться к этой категории
    }
}
