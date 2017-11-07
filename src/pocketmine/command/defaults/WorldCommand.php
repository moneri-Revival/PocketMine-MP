<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WorldCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			"%pocketmine.command.world.description",
			"/$name <world> | /$name <player> <world> | /$name list"
		);
		$this->setPermission("pocketmine.command.world");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		$argCount = count($args);

		if($argCount === 2){
			$target = $sender->getServer()->getPlayer($args[0]);
			$levelName = $args[1];
		}elseif($argCount === 1){
			if($args[0] === "list"){
				$this->listWorlds($sender);
				return true;
			}else{
				$target = $sender;
				$levelName = $args[0];
			}
		}else{
			throw new InvalidCommandSyntaxException();
		}

		if(!($target instanceof Player)){
			$sender->sendMessage(TextFormat::RED . "Target must be a player");
			return false;
		}

		do{
			$level = $sender->getServer()->getLevelByName($levelName);
			if($level !== null){
				break;
			}

			if($sender->getServer()->loadLevel($levelName)){
				continue;
			}

			$sender->sendMessage(TextFormat::RED . "World '$levelName' not found. Please ensure you use the folder name.");
			return false;
		}while(true);

		$target->teleport($level->getSafeSpawn());
		Command::broadcastCommandMessage($sender, "Teleported " . $target->getName() . " to " . $level->getName() . " (" . $level->getFolderName() . ")");
		return true;
	}

	private function listWorlds(CommandSender $sender) : void{
		$sender->sendMessage("Available (loaded) worlds:");
		foreach($sender->getServer()->getLevels() as $level){
			$sender->sendMessage("- " . $level->getName() . " (" . $level->getFolderName() . ")");
		}
	}
}