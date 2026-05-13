<?php

namespace App\Services\VK;

use App\Services\VK\Commands\CommandFactory;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiClient;

class VkCallbackHandler
{
    protected VKApiClient $vk;
    protected string $accessToken;
    protected CommandFactory $commandFactory;
    protected int $adminId;

    public function __construct()
    {
        $this->vk = new VKApiClient(config('services.vk.version', '5.199'));
        $this->accessToken = config('services.vk.group_token');
        $this->commandFactory = new CommandFactory($this->vk, $this->accessToken);
        $this->adminId = config('services.vk.admin_id', 110657666);
    }

    /**
     * Обработка подтверждения сервера
     */
    public function confirmation(int $group_id, ?string $secret)
    {
        $expectedGroupId = (int) config('services.vk.group_id');
        $expectedSecret = config('services.vk.secret');

        if ($group_id === $expectedGroupId &&
            ($expectedSecret === null || $secret === $expectedSecret)) {

            return config('services.vk.confirm_string');
        }

        return 'error';
    }

    public function handleMessage($peerId, $fromId, $text, $payload = null, $attachments = [])
    {
        // Проверяем, что сообщение не от администратора
//        if ($fromId == $this->adminId) {
//            Log::info('Сообщение от администратора, не обрабатываем');
//            return;
//        }

        // Обработка нажатий на кнопки (payload)
        if ($payload) {
            $payloadData = json_decode($payload, true);
            $command = $payloadData['command'] ?? $payloadData['action'] ?? '';

            Log::info('Обработка кнопки', ['command' => $command]);

            $commandInstance = $this->commandFactory->make($command, $peerId, $fromId, $payloadData);
            if ($commandInstance) {
                $commandInstance->execute();
                return;
            }
        }

//        // Обработка текстовых команд
//        $commandName = $this->parseCommand($text);
//
//        if ($commandName) {
//            Log::info('Обработка текстовой команды', ['command' => $commandName]);
//
//            $commandInstance = $this->commandFactory->make($commandName, $peerId, $fromId, ['text' => $text]);
//            if ($commandInstance) {
//                $commandInstance->execute();
//                return;
//            }
//        }

        // Если не команда - пересылаем админу
        $this->forwardToAdmin($peerId, $fromId, $text, $attachments);
    }

    /**
     * Парсинг текстовых команд
     */
//    protected function parseCommand($text): ?string
//    {
//        $textLower = mb_strtolower(trim($text));
//
//        $commandsMap = [
//            'start' => ['/start', 'start', 'начать', 'меню', 'старт', 'привет'],
//            'help' => ['/help', 'help', 'помощь'],
//            'about' => ['/about', 'about', 'о нас', 'инфо'],
//            'support' => ['поддержка', 'support'],
//        ];
//
//        foreach ($commandsMap as $command => $variants) {
//            if (in_array($textLower, $variants)) {
//                return $command;
//            }
//        }
//
//        return null;
//    }

    /**
     * Пересылка сообщения администратору
     */
    protected function forwardToAdmin($peerId, $fromId, $text, $attachments = [])
    {
        $userInfo = $this->getUserInfo($fromId);
        $adminMessage = $this->formatAdminMessage($userInfo, $text, $attachments);

        $this->sendToAdmin($adminMessage, $attachments);

        // Отправляем подтверждение пользователю
        $this->sendConfirmationToUser($peerId);
    }

    /**
     * Отправка сообщения администратору
     */
    protected function sendToAdmin(string $message, array $attachments = []): void
    {
        try {
            $params = [
                'peer_id' => $this->adminId,
                'message' => $message,
                'random_id' => random_int(1, 1000000),
            ];

            if (!empty($attachments)) {
                $attachmentStrings = [];
                foreach (array_slice($attachments, 0, 10) as $attachment) {
                    if ($attachment->type === 'photo') {
                        $photo = $attachment->photo;
                        if (!empty($photo->sizes)) {
                            $maxSize = end($photo->sizes);
                            $attachmentStrings[] = "photo{$photo->owner_id}_{$photo->id}";
                        }
                    }
                }
                if (!empty($attachmentStrings)) {
                    $params['attachment'] = implode(',', $attachmentStrings);
                }
            }

            $this->vk->messages()->send($this->accessToken, $params);
            Log::info('Сообщение отправлено администратору');

        } catch (\Exception $e) {
            Log::error('Ошибка отправки админу: ' . $e->getMessage());
        }
    }

    /**
     * Отправка подтверждения пользователю
     */
    protected function sendConfirmationToUser($peerId): void
    {
        $this->vk->messages()->send($this->accessToken, [
            'peer_id' => $peerId,
            'message' => "✅ Сообщение отправлено администратору!\n\nОтвет придет в ближайшее время.",
            'random_id' => random_int(1, 1000000)
        ]);
    }

    /**
     * Получение информации о пользователе
     */
    protected function getUserInfo($userId): array
    {
        try {
            $user = $this->vk->users()->get($this->accessToken, [
                'user_ids' => [$userId],
                'fields' => ['first_name', 'last_name', 'screen_name', 'photo_50'],
            ]);

            if (!empty($user)) {
                return [
                    'id' => $userId,
                    'first_name' => $user[0]['first_name'] ?? 'Неизвестно',
                    'last_name' => $user[0]['last_name'] ?? '',
                    'screen_name' => $user[0]['screen_name'] ?? '',
                    'link' => "https://vk.com/id{$userId}",
                ];
            }
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации о пользователе: ' . $e->getMessage());
        }

        return [
            'id' => $userId,
            'first_name' => 'Пользователь',
            'last_name' => '',
            'screen_name' => '',
            'link' => "https://vk.com/id{$userId}",
        ];
    }

    /**
     * Форматирование сообщения для администратора
     */
    protected function formatAdminMessage(array $userInfo, string $text, array $attachments): string
    {
        $message = "📨 Новое сообщение от пользователя!\n\n";
        $message .= "👤 Пользователь: {$userInfo['first_name']} {$userInfo['last_name']}\n";
        $message .= "🔗 Ссылка: {$userInfo['link']}\n\n";
        $message .= "💬 Сообщение:\n{$text}\n";

        if (!empty($attachments)) {
            $message .= "\n📎 Вложения: " . count($attachments) . " шт.\n";
        }

        $message .= "\n🕐 Время: " . date('d.m.Y H:i:s');

        return $message;
    }
}
