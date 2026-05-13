<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
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

}
