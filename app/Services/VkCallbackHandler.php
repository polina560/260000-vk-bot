<?php

namespace App\Services;

use Generator\Skeleton\skeleton\base\src\VK\CallbackApi\VKCallbackApiServerHandler;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiClient;

class VkCallbackHandler extends VKCallbackApiServerHandler
{

    /**
     * Обработка подтверждения сервера
     */
    public function confirmation(int $group_id, ?string $secret)
    {
        $expectedGroupId = (int) config('services.vk.group_id');
        $expectedSecret = config('services.vk.secret');

        // Проверяем группу и секретный ключ (если он задан)
        if ($group_id === $expectedGroupId &&
            ($expectedSecret === null || $secret === $expectedSecret)) {

            // Возвращаем строку подтверждения
            return config('services.vk.confirm_string');
        }

        return 'error';
    }

    /**
     * Обработка нового сообщения
     */
    public function messageNew(int $group_id, ?string $secret, array $object)
    {
        // Извлекаем текст сообщения и ID получателя
        $messageText = $object['message']['text'] ?? '';
        $peerId = $object['message']['peer_id'] ?? null;

        // Логируем входящее сообщение (полезно для отладки)
        Log::info('VK Message: ', ['text' => $messageText, 'peer_id' => $peerId]);

        // Эхо-ответ: отправляем обратно тот же текст
        if (!empty($messageText) && $peerId) {
            $this->sendMessage($peerId, $messageText);
        }

        // Callback API требует ответить "ok"
        return 'ok';
    }

    /**
     * Отправка сообщения через VK API
     */
    protected function sendMessage(int $peerId, string $text): void
    {
        $vk = new VKApiClient(config('services.vk.version'));
        $accessToken = config('services.vk.group_token');

        try {
            $vk->messages()->send($accessToken, [
                'peer_id' => $peerId,
                'message' => $text,
                'random_id' => rand(1, 1000000),
            ]);
        } catch (\Exception $e) {
            Log::error('VK Send Error: ' . $e->getMessage());
        }
    }
}
