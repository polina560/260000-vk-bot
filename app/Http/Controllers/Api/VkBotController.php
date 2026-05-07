<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VkCallbackHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use VK\Client\VKApiClient;

class VkBotController extends Controller
{

    protected $handler;

    public function __construct(VkCallbackHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(Request $request)
    {
        // Полное отключение Debugbar
        if (function_exists('debugbar')) {
            debugbar()->disable();
        }

        // Альтернативный способ отключения
        if (class_exists('\Barryvdh\Debugbar\Facades\Debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }

        // Отключаем буферизацию вывода Debugbar
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $data = json_decode($request->getContent());

        // Confirmation - возвращаем чистый ответ
        if ($data && $data->type === 'confirmation') {
            // Очищаем все буферы вывода
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Возвращаем только строку
            return config('services.vk.confirm_string', 'ecc1e4ea');
        }

        // Message
        if ($data && $data->type === 'message_new') {
            $text = $data->object->message->text ?? '';
            $peerId = $data->object->message->peer_id ?? null;

            if ($text && $peerId) {
                try {
                    $vk = new VKApiClient(config('services.vk.version', '5.199'));
                    $vk->messages()->send(config('services.vk.group_token'), [
                        'peer_id' => $peerId,
                        'message' => $text,
                        'random_id' => random_int(1, 1000000),
                    ]);
                } catch (\Exception $e) {
                    Log::error('VK Send Error: ' . $e->getMessage());
                }
            }
        }

        return 'ok';
    }

}
