<?php

namespace App\Services\VK\Commands;



class StartCommand extends BaseCommand
{
    public function execute(): void
    {

        $message = "👋 Привет! Я бот-помощник.\n\n";
        $message .= "Выберите действие на клавиатуре:\n";
        $message .= "• Опоздание - сообщить об опоздании\n";
        $message .= "• Заболел - сообщить о больничном\n";
        $message .= "• Выхожу с больничного - сообщить о выходе\n";
        $message .= "• Изменения в расписании - для студентов\n";
        $message .= "• Форс-мажор - экстренные ситуации\n";
        $message .= "• Другое - другие вопросы\n";


        $keyboard = $this->getMainKeyboard();
        $this->sendMessage($message, $keyboard);
    }
}
