<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VkCallbackHandler;
use Illuminate\Http\Request;

class VkBotController extends Controller
{

    protected $handler;

    public function __construct(VkCallbackHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(Request $request)
    {
        // Получаем JSON-данные от VK
        $data = json_decode($request->getContent(), false);

        if ($data) {
            // Передаём данные в обработчик
            $this->handler->parse($data);
        }

        // Всегда возвращаем "ok" (кроме confirmation, где обработчик сам выводит строку)
        return response('ok');
    }
}
