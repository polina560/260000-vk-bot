<?php

namespace App\Services\VK\Commands;

class DelayCommand extends BaseCommand
{

    public function execute(): void
    {
        $message = " Уведомление об опоздании\n\n";


        $this->sendMessage($message);

        // Здесь можно сохранить состояние, что пользователь в процессе заполнения
        // и следующие его сообщения будут относиться к этой категории
    }
}
