<?php

declare(strict_types=1);

namespace WarpUI;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\World;
use pocketmine\entity\Location;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase {

    private Config $warps;

    protected function onEnable(): void {
        $this->warps = new Config($this->getDataFolder() . "warps.yml", Config::YAML);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cPlease run this command in-game.");
            return true;
        }

        switch ($command->getName()) {
            case "warp":
                $this->openWarpMenu($sender);
                break;
            case "setwarp":
                if ($sender->hasPermission("warpui.command.setwarp")) {
                    $this->openCreateWarpMenu($sender);
                } else {
                    $sender->sendMessage("§cYou do not have permission to manage warps.");
                }
                break;
        }
        return true;
    }

    /**
     * Main Selection Menu
     */
    public function openWarpMenu(Player $player): void {
        $warps = $this->warps->getAll();
        $names = array_keys($warps);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($names) {
            if ($data === null) return;
            
            // Handle the Admin Tools button if it was clicked
            if ($player->hasPermission("warpui.command.setwarp") && $data === count($names)) {
                $this->openAdminTools($player);
                return;
            }

            $selected = $names[$data];
            $this->openConfirmWarp($player, $selected, $this->warps->get($selected));
        });

        $form->setTitle("§l§bWarpUI");
        $form->setContent("§7Select a destination:");

        foreach ($warps as $name => $data) {
            $desc = $data["desc"] ?? "Tap to teleport";
            $form->addButton("§b$name\n§8$desc");
        }

        if ($player->hasPermission("warpui.command.setwarp")) {
            $form->addButton("§d§lMANAGE WARPS\n§r§8Admin Settings");
        }

        $player->sendForm($form);
    }

    /**
     * Confirmation Menu - Error Fixed Here
     */
    public function openConfirmWarp(Player $player, string $name, array $data): void {
        // FIXED: Removed $player from the 'use' clause as it's already a parameter
        $form = new SimpleForm(function (Player $player, ?int $dataArr) use ($name, $data) {
            if ($dataArr === 0) { // Index 0 is the "Confirm" button
                $this->teleportToWarp($player, $name, $data);
            }
        });

        $desc = $data["desc"] ?? "No description provided.";
        $form->setTitle("§l§bWarpUI: $name");
        $form->setContent("§eDescription:\n§f$desc\n\n§7Teleport to this location?");
        $form->addButton("§l§2CONFIRM");
        $form->addButton("§l§cCANCEL");
        $player->sendForm($form);
    }

    /**
     * Create Warp UI (CustomForm)
     */
    public function openCreateWarpMenu(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null || empty($data[0])) return;

            $warpName = $data[0];
            $description = $data[1] ?? "";
            $pos = $player->getPosition();
            
            $this->warps->set($warpName, [
                "x" => (float)$pos->getX(),
                "y" => (float)$pos->getY(),
                "z" => (float)$pos->getZ(),
                "world" => $pos->getWorld()->getFolderName(),
                "desc" => $description
            ]);
            $this->warps->save();
            
            $player->sendMessage("§a[WarpUI] Created warp §f$warpName");
        });

        $form->setTitle("§l§bWarpUI - Create");
        $form->addInput("Warp Name:", "e.g. Shop");
        $form->addInput("Description:", "e.g. Buy and sell items here");
        $player->sendForm($form);
    }

    /**
     * Admin Utility Methods
     */
    public function openAdminTools(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;
            $data === 0 ? $this->openCreateWarpMenu($player) : $this->openDeleteWarpMenu($player);
        });
        $form->setTitle("§l§bWarpUI - Admin");
        $form->addButton("§2Add New Warp");
        $form->addButton("§cDelete Warp");
        $player->sendForm($form);
    }

    public function openDeleteWarpMenu(Player $player): void {
        $names = array_keys($this->warps->getAll());
        $form = new SimpleForm(function (Player $player, ?int $data) use ($names) {
            if ($data === null) return;
            $this->warps->remove($names[$data]);
            $this->warps->save();
            $player->sendMessage("§e[WarpUI] Warp removed.");
        });
        $form->setTitle("§l§bWarpUI - Delete");
        foreach ($names as $n) $form->addButton("§4Remove: §r$n");
        $player->sendForm($form);
    }

    private function teleportToWarp(Player $player, string $name, array $data): void {
        $wm = $this->getServer()->getWorldManager();
        if (!$wm->isWorldLoaded($data["world"])) {
            $wm->loadWorld($data["world"]);
        }
        $world = $wm->getWorldByName($data["world"]);

        if ($world instanceof World) {
            $player->teleport(new Location((float)$data["x"], (float)$data["y"], (float)$data["z"], $world, 0, 0));
            $player->sendTitle("§aTeleported!", "§7Arrived at $name");
        } else {
            $player->sendMessage("§cWorld '{$data["world"]}' is not available.");
        }
    }
}

