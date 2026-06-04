<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VK\VkCallbackHandler;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;


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
        } elseif ($data && $data->type === 'message_event') {
            $event = $data->object;

            $this->handler->handleCallbackEvent(
                $event->event_id ?? null,
                $event->user_id ?? null,
                $event->peer_id ?? null,
                $event->payload ?? null,
                $event->conversation_message_id ?? null
            );
        }

        return 'ok';
    }

}
