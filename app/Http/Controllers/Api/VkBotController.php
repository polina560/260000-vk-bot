<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VK\VkCallbackHandler;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiClient;

class VkBotController extends Controller
{
    protected $handler;

    protected $adminId;

    public function __construct(VkCallbackHandler $handler)
    {
        $this->handler = $handler;
        $this->adminId = config('services.vk.admin_id', '110657666');

    }

    public function handle(Request $request)
    {
        // Отключаем Debugbar
        if (function_exists('debugbar')) {
            debugbar()->disable();
        }

        if (class_exists('\Barryvdh\Debugbar\Facades\Debugbar')) {
            Debugbar::disable();
        }

        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $data = json_decode($request->getContent());

        if ($data && $data->type === 'confirmation') {
            while (ob_get_level()) {
                ob_end_clean();
            }

            return config('services.vk.confirm_string', '');
        }

        // Message
        if ($data && $data->type === 'message_new') {
            $message = $data->object->message;
            $this->handler->handleMessage(
                $message->peer_id ?? null,
                $message->from_id ?? null,
                $message->text ?? '',
                $message->payload ?? null,
                $message->attachments ?? []
            );
        }

        return 'ok';
    }

    /**
     * Обработка сообщения
     */
    protected function handleMessage($data)
    {
        $message = $data->object->message;
        $text = $message->text ?? '';
        $peerId = $message->peer_id ?? null;
        $fromId = $message->from_id ?? null;
        $attachments = $message->attachments ?? [];
        $payload = $message->payload ?? null;

        $vk = new VKApiClient(config('services.vk.version', '5.199'));
        $accessToken = config('services.vk.group_token');

        if ($payload) {
            $payloadData = json_decode($payload, true);

            $command = $payloadData['command'] ?? $payloadData['answer'] ?? '';

            switch ($command) {
                case 'start':
                    $vk->messages()->send($accessToken, [
                        'peer_id' => $peerId,
                        'message' => '👋 Привет! Я бот-помощник. Выберите действие:',
//                        'keyboard' => $keyboard,
                        'random_id' => random_int(1, 1000000)
                    ]);
                    break;
            }
        }

        // Проверяем, что сообщение не от администратора (избегаем зацикливания)
        //        if ($fromId == $this->adminId) {
        //            Log::info('Сообщение от администратора, не пересылаем');
        //            return;
        //        }
        // Получаем информацию о пользователе
        $userInfo = $this->getUserInfo($fromId);

        // Формируем сообщение для администратора
        $adminMessage = $this->formatAdminMessage($userInfo, $text, $attachments);
        //        $adminMessage = $this->formatAdminMessage($text, $attachments);

        // Отправляем сообщение администратору
        $this->sendMessageToAdmin($adminMessage, $attachments);

        // Отвечаем пользователю (опционально)
        //        $this->replyToUser($peerId, $fromId, $text);
    }

    /**
     * Получение информации о пользователе
     */
    protected function getUserInfo($userId)
    {
        try {
            $vk = new VKApiClient(config('services.vk.version', '5.199'));
            $accessToken = config('services.vk.group_token');

            $user = $vk->users()->get($accessToken, [
                'user_ids' => [$userId],
                'fields' => ['first_name', 'last_name', 'screen_name', 'photo_50'],
            ]);

            if (!empty($user)) {
                return [
                    'id' => $userId,
                    'first_name' => $user[0]['first_name'] ?? 'Неизвестно',
                    'last_name' => $user[0]['last_name'] ?? '',
                    'screen_name' => $user[0]['screen_name'] ?? '',
                    'photo' => $user[0]['photo_50'] ?? '',
                    'link' => "https://vk.com/id{$userId}",
                ];
            }
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации о пользователе: '.$e->getMessage());
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
    protected function formatAdminMessage($userInfo, $text, $attachments)
    {
        $message = "📨 Новое сообщение от пользователя!\n\n";
        $message .= "👤 Пользователь: {$userInfo['first_name']} {$userInfo['last_name']}\n";
        //        $message .= "🆔 **ID:** {$userInfo['id']}\n";
        $message .= "🔗 Ссылка: {$userInfo['link']}\n";

        //        if (!empty($userInfo['screen_name'])) {
        //            $message .= "📝 **Screen name:** @{$userInfo['screen_name']}\n";
        //        }

        $message .= "\n💬 Сообщение:\n {$text}\n";

        if (!empty($attachments)) {
            $message .= "\n📎 Вложения: ".count($attachments)." шт.\n";
            foreach ($attachments as $attachment) {
                $message .= "- Тип: {$attachment->type}\n";
            }
        }

        $message .= "\n🕐 Время: ".date('d.m.Y H:i:s');

        return $message;
    }

    /**
     * Отправка сообщения администратору
     */
    protected function sendMessageToAdmin($message, $attachments = [])
    {
        if (empty($this->adminId)) {
            Log::error('ID администратора не указан в конфигурации');

            return;
        }

        try {
            $vk = new VKApiClient(config('services.vk.version', '5.199'));
            $accessToken = config('services.vk.group_token');

            $params = [
                'peer_id' => $this->adminId,
                'message' => $message,
                'random_id' => random_int(1, 1000000),
            ];

            // Если есть вложения, добавляем их (первые 10)
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

            $vk->messages()->send($accessToken, $params);
            Log::info('Сообщение отправлено администратору', ['admin_id' => $this->adminId]);

        } catch (\Exception $e) {
            Log::error('Ошибка отправки сообщения администратору: '.$e->getMessage());
        }
    }

    /**
     * Ответ пользователю
     */
    //    protected function replyToUser($peerId, $userId, $userMessage)
    //    {
    //        try {
    //            $vk = new VKApiClient(config('services.vk.version', '5.199'));
    //            $accessToken = config('services.vk.group_token');
    //
    //            // Варианты ответов пользователю
    //            $responses = [
    //                "Спасибо за сообщение! Я передал его администратору. Ответ придет в ближайшее время.",
    //                "Ваше сообщение получено! Администратор скоро свяжется с вами.",
    //                "Сообщение доставлено администратору. Ожидайте ответа.",
    //                "Благодарю за обращение! Мы ответим вам в ближайшее время."
    //            ];
    //
    //            $reply = $responses[array_rand($responses)];
    //
    //            $vk->messages()->send($accessToken, [
    //                'peer_id' => $peerId,
    //                'message' => $reply,
    //                'random_id' => random_int(1, 1000000),
    //            ]);
    //
    //            Log::info('Ответ отправлен пользователю', ['user_id' => $userId]);
    //
    //        } catch (\Exception $e) {
    //            Log::error('Ошибка отправки ответа пользователю: ' . $e->getMessage());
    //        }
    //    }

}
