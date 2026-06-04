<?php

namespace App\Services\VK;

use App\Enums\CommandType;
use App\Enums\UserState;
use App\Models\TelegramUser;
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
        $user = TelegramUser::firstOrCreate(
            ['form_id' => $fromId],
            [
                'name' => $this->getUserName($fromId),
                'peer_id' => $peerId,
                'state' => UserState::None->value,
                'prev_state' => UserState::None->value,
                'command' => CommandType::Start->value,
                'data' => [],
            ]
        );

        $currentState = $user->state;
        $currentCommand = CommandType::from($user->command)->value;
        Log::info('Начало', [
            'state' => $currentState,
            'command' => $currentCommand,
            'text' => $text,
        ]);

        $isMainMenu = false;
        $textLower = mb_strtolower(trim($text));

        if ($textLower === 'главное меню' || $textLower === 'меню' || $textLower === 'start') {
            $isMainMenu = true;
        }

        // проверяем payload
        if ($payload) {
            $payloadData = json_decode($payload, true);
            $commandFromPayload = $payloadData['command'] ?? $payloadData['action'] ?? '';
            if ($commandFromPayload === 'start' || $commandFromPayload === 'main_menu') {
                $isMainMenu = true;
            }
        }

        if ($isMainMenu) {
//            Log::info('Возврат в главное меню', [
//                'user_id' => $fromId,
//                'previous_state' => $currentState,
//                'previous_command' => $currentCommand,
//            ]);

            // Очищаем состояние пользователя
            $user->command = CommandType::None->value;
            $user->state = UserState::None->value;
            $user->prev_state = UserState::None->value;
            $user->data = null;
            $user->save();

            // Показываем главное меню
            $startCommand = $this->commandFactory->make('start', $peerId, $fromId, null);
            if ($startCommand) {
                $startCommand->execute();
            }

            return;
        }

        $isPayloadEmpty = empty($payload) || $payload === '{}' || $payload === '""';

        // Если есть активное состояние
        if ($currentState !== UserState::None->value && $isPayloadEmpty) {
            //            Log::info('Продолжение диалога', [
            //                'state' => $currentState,
            //                'command' => $user->command,
            //                'text' => $text
            //            ]);

            if ($currentCommand && $currentCommand !== CommandType::None->value) {
                $commandInstance = $this->commandFactory->make(
                    $currentCommand,  // Используем команду из БД
                    $peerId,
                    $fromId,
                    ['text' => $text]
                );
                if ($commandInstance) {
                    $commandInstance->execute();

                    return;
                }
            }

            // если команда не найдена, но есть состояние
            $commandByState = CommandType::from($user->command)->value;
            if ($commandByState) {
                //                Log::info('Команда определена по состоянию', ['command' => $commandByState]);
                $commandInstance = $this->commandFactory->make(
                    $commandByState,
                    $peerId,
                    $fromId,
                    ['text' => $text]
                );
                if ($commandInstance) {
                    $commandInstance->execute();

                    return;
                }
            }
        }

        if ($payload) {
            $payloadData = null;

            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payloadData = $decoded;
                }
            } elseif (is_array($payload)) {
                $payloadData = $payload;
            }

            $command = '';
            if (is_array($payloadData)) {
                $command = $payloadData['command']
                    ?? $payloadData['action']
                    ?? $payloadData['cmd']
                    ?? '';
            }

            //            Log::info('Обработка кнопки', [
            //                'command' => $command,
            //                'payloadData' => $payloadData,
            //                'payload_raw_type' => gettype($payload)
            //            ]);

            if ($command) {
                $commandInstance = $this->commandFactory->make(
                    $command,
                    $peerId,
                    $fromId,
                    $payloadData
                );

                if ($commandInstance) {
                    $commandInstance->execute();

                    return;
                }
            }
        }

        if ($currentState !== UserState::None->value) {
            //            Log::info('Активное состояние, но не обработано ранее', [
            //                'state' => $currentState,
            //                'command' => $currentCommand
            //            ]);

            // Пробуем получить команду по состоянию
            $commandByState = CommandType::from($user->command)->value;
            if ($commandByState) {
                $commandInstance = $this->commandFactory->make(
                    $commandByState,
                    $peerId,
                    $fromId,
                    ['text' => $text]
                );
                if ($commandInstance) {
                    $commandInstance->execute();

                    return;
                }
            }
        }

        // Если ничего не подошло - показываем стартовое меню
        //        Log::info('Показываем стартовое меню');
        $startCommand = $this->commandFactory->make('start', $peerId, $fromId, null);
        if ($startCommand) {
            $startCommand->execute();
        }
    }

    /**
     * Получение имени пользователя по ID
     */
    private function getUserName($userId): string
    {
        try {
            $user = $this->vk->users()->get($this->accessToken, [
                'user_ids' => [$userId],
                'fields' => ['first_name', 'last_name'],
            ]);

            if (!empty($user)) {
                return ($user[0]['first_name'] ?? '').' '.($user[0]['last_name'] ?? '');
            }
        } catch (\Exception $e) {
            Log::error('Ошибка получения имени пользователя: '.$e->getMessage());
        }

        return 'Пользователь';
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
            Log::error('Ошибка отправки админу: '.$e->getMessage());
        }
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
    protected function formatAdminMessage(array $userInfo, string $text, array $attachments): string
    {
        $message = "📨 Новое сообщение от пользователя!\n\n";
        $message .= "👤 Пользователь: {$userInfo['first_name']} {$userInfo['last_name']}\n";
        $message .= "🔗 Ссылка: {$userInfo['link']}\n\n";
        $message .= "💬 Сообщение:\n{$text}\n";

        if (!empty($attachments)) {
            $message .= "\n📎 Вложения: ".count($attachments)." шт.\n";
        }

        $message .= "\n🕐 Время: ".date('d.m.Y H:i:s');

        return $message;
    }

    /**
     * Обработка callback-событий
     */
    public function handleCallbackEvent($eventId, $userId, $peerId, $payload, $conversationMessageId = null)
    {
        //        Log::info('Callback event', [
        //            'event_id' => $eventId,
        //            'user_id' => $userId,
        //            'peer_id' => $peerId,
        //            'payload_type' => gettype($payload),
        //            'payload_raw' => $payload
        //        ]);

        $payloadData = $this->normalizePayload($payload);

        // Извлекаем команду
        $command = $payloadData['command']
            ?? $payloadData['action']
            ?? $payloadData['cmd']
            ?? '';

        //        Log::info('Callback command extracted', [
        //            'command' => $command,
        //            'payloadData' => $payloadData
        //        ]);

        // Создаём и выполняем команду
        if ($command) {
            $commandInstance = $this->commandFactory->make(
                $command,
                $peerId,
                $userId,
                $payloadData
            );

            if ($commandInstance) {
                $commandInstance->execute();
            }
        }

        // Подтверждаем событие
        $this->ackCallbackEvent($eventId, $userId, $peerId);
    }

    private function normalizePayload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (empty($payload)) {
            return [];
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        return ['text' => (string) $payload];
    }

    /**
     * Отправка подтверждения обработки callback-события
     */
    private function ackCallbackEvent($eventId, $userId, $peerId): void
    {
        if (empty($eventId)) {
            //            Log::warning('ackCallbackEvent: пустой event_id');
            return;
        }

        try {
            // Проверяем, что метод существует в клиенте
            $messages = $this->vk->messages();
            if (method_exists($messages, 'sendMessageEventAnswer')) {
                $messages->sendMessageEventAnswer(
                    $this->accessToken,
                    [
                        'event_id' => (string) $eventId,
                        'user_id' => (int) $userId,
                        'peer_id' => (int) $peerId,
                    ]
                );
                //                Log::debug('Callback ack sent', ['event_id' => $eventId]);
            } else {
                Log::debug('sendMessageEventAnswer не доступен в этой версии VK API');
            }
        } catch (\Exception $e) {
            Log::warning('Ошибка ack callback', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
