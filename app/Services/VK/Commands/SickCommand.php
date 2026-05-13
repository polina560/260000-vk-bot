<?php

namespace App\Services\VK\Commands;

class SickCommand extends BaseCommand
{

    public function execute(): void
    {
        $message = " Уведомление об болезни\n\n";

        $this->sendMessage($message);

        // Здесь можно сохранить состояние, что пользователь в процессе заполнения
        // и следующие его сообщения будут относиться к этой категории
    }
}
