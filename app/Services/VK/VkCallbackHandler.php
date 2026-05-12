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

    public function __construct()
    {
        $this->vk = new VKApiClient(config('services.vk.version', '5.199'));
        $this->accessToken = config('services.vk.group_token');
        $this->commandFactory = new CommandFactory($this->vk, $this->accessToken);
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
        // Обработка нажатий на кнопки
        if ($payload) {
            $payloadData = json_decode($payload, true);
            $command = $payloadData['command'] ?? $payloadData['action'] ?? '';

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
//            $commandInstance = $this->commandFactory->make($commandName, $peerId, $fromId, ['text' => $text]);
//            if ($commandInstance) {
//                $commandInstance->execute();
//                return;
//            }
//        }
//
//        // Если не команда - пересылаем админу
//        $this->forwardToAdmin($peerId, $fromId, $text, $attachments);
    }


//    /**
//     * Обработка нового сообщения
//     */
//    public function messageNew(int $group_id, ?string $secret, array $object)
//    {
//        // Извлекаем текст сообщения и ID получателя
//        $messageText = $object['message']['text'] ?? '';
//        $peerId = $object['message']['peer_id'] ?? null;
//
//        // Логируем входящее сообщение (полезно для отладки)
//        Log::info('VK Message: ', ['text' => $messageText, 'peer_id' => $peerId]);
//
//        // Эхо-ответ: отправляем обратно тот же текст
//        if (!empty($messageText) && $peerId) {
//            $this->sendMessage($peerId, $messageText);
//        }
//
//        return 'ok';
//    }
//
//    /**
//     * Отправка сообщения через VK API
//     */
//    protected function sendMessage(int $peerId, string $text): void
//    {
//        $vk = new VKApiClient(config('services.vk.version'));
//        $accessToken = config('services.vk.group_token');
//
//        try {
//            $vk->messages()->send($accessToken, [
//                'peer_id' => $peerId,
//                'message' => $text,
//                'random_id' => rand(1, 1000000),
//            ]);
//        } catch (\Exception $e) {
//            Log::error('VK Send Error: '.$e->getMessage());
//        }
//    }
}
