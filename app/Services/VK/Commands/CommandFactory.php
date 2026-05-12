<?php

namespace App\Services\VK\Commands;

use VK\Client\VKApiClient;

class CommandFactory
{

    protected VKApiClient $vk;
    protected string $accessToken;

    private array $commands = [
        'start' => StartCommand::class,
//        'help' => HelpCommand::class,
//        'about' => AboutCommand::class,
//        'support' => SupportCommand::class,
//        'tenders' => TendersCommand::class, // Добавьте свой класс
    ];

    public function __construct(VKApiClient $vk, string $accessToken)
    {
        $this->vk = $vk;
        $this->accessToken = $accessToken;
    }

    public function make(string $command, int $peerId, int $fromId, ?array $payload = null): ?BaseCommand
    {
        if (!isset($this->commands[$command])) {
            return null;
        }

        $commandClass = $this->commands[$command];

        return new $commandClass($this->vk, $this->accessToken, $peerId, $fromId, $payload);
    }

    public function getAvailableCommands(): array
    {
        return array_keys($this->commands);
    }
}
