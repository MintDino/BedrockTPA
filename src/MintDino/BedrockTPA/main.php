<?php

declare(strict_types=1);

namespace MintDino\BedrockTPA;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

class Main extends PluginBase {

    private array $requests = [];
    private array $cooldowns = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getLogger()->info("BedrockTPA enabled.");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return true;
        }

        $msgs = $this->getConfig()->get("messages");

        switch ($command->getName()) {

            case "tpa":
                if (empty($args[0])) {
                    $sender->sendMessage("§cUsage: /tpa <player>");
                    return true;
                }

                $target = $this->getServer()->getPlayerExact($args[0]);
                if (!$target) {
                    $sender->sendMessage("§cPlayer not found.");
                    return true;
                }

                if ($sender->getName() === $target->getName()) {
                    $sender->sendMessage("§cYou cannot send a TPA to yourself.");
                    return true;
                }

                $cd = $this->cooldowns[$sender->getName()] ?? 0;
                if ($cd > time()) {
                    $left = $cd - time();
                    $sender->sendMessage(str_replace("{seconds}", (string)$left, $msgs["on-cooldown"]));
                    return true;
                }

                $this->requests[$target->getName()] = $sender->getName();

                $sender->sendMessage(str_replace("{player}", $target->getName(), $msgs["request-sent"]));
                $target->sendMessage(str_replace("{player}", $sender->getName(), $msgs["request-received"]));

                $this->cooldowns[$sender->getName()] = time() + (int)$this->getConfig()->get("cooldown");

                $timeout = (int)$this->getConfig()->get("request-timeout");
                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($target, $msgs): void {
                    if (isset($this->requests[$target->getName()])) {
                        unset($this->requests[$target->getName()]);
                        $target->sendMessage($msgs["timed-out"]);
                    }
                }), $timeout * 20);

                return true;

            case "tpaccept":
                $receiver = $sender->getName();
                if (!isset($this->requests[$receiver])) {
                    $sender->sendMessage($msgs["no-request"]);
                    return true;
                }

                $fromPlayer = Server::getInstance()->getPlayerExact($this->requests[$receiver]);
                unset($this->requests[$receiver]);

                if ($fromPlayer !== null) {
                    $fromPlayer->teleport($sender->getLocation());
                    $fromPlayer->sendMessage($msgs["accepted"]);
                }

                $sender->sendMessage($msgs["accepted"]);
                return true;
          
            case "tpdeny":
                $receiver = $sender->getName();
                if (!isset($this->requests[$receiver])) {
                    $sender->sendMessage($msgs["no-request"]);
                    return true;
                }

                $fromPlayer = Server::getInstance()->getPlayerExact($this->requests[$receiver]);
                unset($this->requests[$receiver]);

                $sender->sendMessage($msgs["denied"]);
                if ($fromPlayer !== null) {
                    $fromPlayer->sendMessage($msgs["denied"]);
                }

                return true;
        }

        return false;
    }
}
