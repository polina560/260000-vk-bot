<?php

namespace App\Services\VK\Commands;



class StartCommand extends BaseCommand
{
    public function execute(): void
    {
        $keyboard = $this->getKeyboard();

        $message = "👋 Привет! Я бот-помощник.\n\n";
        $message .= "Выберите действие на клавиатуре:\n";
        $message .= "• Опоздание - сообщить об опоздании\n";
        $message .= "• Заболел - сообщить о больничном\n";
        $message .= "• Выхожу с больничного - сообщить о выходе\n";
        $message .= "• Изменения в расписании - для студентов\n";
        $message .= "• Форс-мажор - экстренные ситуации\n";
        $message .= "• Другое - другие вопросы\n";


        $this->sendMessage($message, $keyboard);
    }

    private function getKeyboard(): string
    {
        $keyboard = [
            'buttons' => [
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🚗 Опоздание',
                            'payload' => json_encode(['command' => 'delay'])
                        ],
                        'color' => 'primary'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🤒 Заболел',
                            'payload' => json_encode(['command' => 'sick'])
                        ],
                        'color' => 'secondary'
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '🏥 Выхожу с больничного',
                            'payload' => json_encode(['command' => 'return-sick'])
                        ],
                        'color' => 'positive'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '📅 Изменения в расписании',
                            'payload' => json_encode(['command' => 'schedule'])
                        ],
                        'color' => 'primary'
                    ],
                ],
                [
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '⚠️ Форс-мажор',
                            'payload' => json_encode(['command' => 'force-majeure'])
                        ],
                        'color' => 'negative'
                    ],
                    [
                        'action' => [
                            'type' => 'text',
                            'label' => '💬 Другое',
                            'payload' => json_encode(['command' => 'other'])
                        ],
                        'color' => 'primary'
                    ],
                ],
            ],
            'one_time' => false,
        ];

        return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }
}
