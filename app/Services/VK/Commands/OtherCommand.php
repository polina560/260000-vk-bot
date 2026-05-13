<?php

namespace App\Services\VK\Commands;

class OtherCommand extends BaseCommand
{

    public function execute(): void
    {
        $message = "Уведомление об иной причине \n\n";


        $this->sendMessage($message);

        // Здесь можно сохранить состояние, что пользователь в процессе заполнения
        // и следующие его сообщения будут относиться к этой категории
    }
}
