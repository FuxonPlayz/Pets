<?php
namespace LostTeam;

use LostTeam\task\PetsTick;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\Utils;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase implements Listener {
	public static $pet, $petState, $isPetChanging, $type;
	public $pets, $petType, $wishPet, $current, $namehold = null;
	public function onEnable() {
		$this->update();
		Entity::registerEntity(ChickenPet::class);
		Entity::registerEntity(WolfPet::class);
		Entity::registerEntity(PigPet::class);
//		Entity::registerEntity(BlazePet::class);
//		Entity::registerEntity(MagmaPet::class);
		Entity::registerEntity(RabbitPet::class);
//		Entity::registerEntity(BatPet::class);
		Entity::registerEntity(SilverfishPet::class);
//		Entity::registerEntity(BlockPet::class);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new PetsTick($this), 20*15); //run each minute for random pet messages
		$this->getLogger()->notice(TF::GREEN."Enabled!");
	}
	public function update() {
		$this->saveResource("auto-update.yml");
    		$update = new Config($this->getDataFolder()."auto-update.yml", Config::YAML);
		if($update->get("enabled")){
			try{
				$url = "https://lostTeam.github.io/plugins/Pets/api/?version=".$this->getDescription()->getVersion();
				$content = Utils::getURL($url);
				$data = json_decode($content, true);
				if($data["update-available"] === true){
					$this->getLogger()->notice("New version of Pets Plugin was released. Version : ".$data["new-version"]);
					if($update->get("force-update") and $this->isPhar()){
						$address = file_get_contents($data["download-address"]);
						$e = explode("/", $data["download-address"]);
						$filename = end($e);
						file_put_contents($this->getDataFolder()."../".$filename, $address);
						if($this->isPhar()){
							$file = substr($this->getFile(), 7, -1);
							@unlink($file);
						}
						$this->getLogger()->notice("Pets Plugin was updated automatically to version ".$data["new-version"]);
            					$this->getServer()->shutdown();
            					return;
					}
				}else{
					$this->getLogger()->notice("Pets Plugin is currently up-to-date.");
				}
        if($data["notice"] !== "") {
          $this->getLogger()->notice($data["notice"]);
        }
			}catch(\Exception $e){
				$this->getLogger()->error("Error while retrieving data from server : \n".$e);
			}
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if(strtolower($command) === "pet" or strtolower($command) === "pets") {
			if(!$sender instanceof Player) {
				$sender->sendMessage("Only Players can use this plugin");
				return true;
			}
			if (!isset($args[0])) {
				if($sender->hasPermission('pet.command.help')) {
					$sender->sendMessage(TF::YELLOW."=======".TF::BLUE."Pets".TF::YELLOW."=======");
					$sender->sendMessage(TF::YELLOW."/pets help");
					$sender->sendMessage(TF::YELLOW."/pets cycle");
					$sender->sendMessage(TF::YELLOW."/pets name <Pet Name>");
					$sender->sendMessage(TF::YELLOW."/pets list");
					$sender->sendMessage(TF::YELLOW."/pets clear");
					return true;
				}else{
					$sender->sendMessage(TF::RED . "You do not have permission to use this command");
				}
				return true;
			}
			switch (strtolower($args[0])) {
				case "name":
				case "setname":
					if(!$sender->hasPermission('pet.command.name')) {
						$sender->sendMessage(TF::RED . "You do not have permission to use this command");
						return true;
					}
					if (isset($args[1])) {
						$this->getPet($sender)->setNameTag($args[1]);
						$this->getPet($sender)->setName($args[1]);
						$sender->sendMessage("Name now set to: ".$args[1]);
					}
					break;
				case "cycle":
					if(!$sender->hasPermission('pet.command.cycle')) {
						$sender->sendMessage(TF::RED . "You do not have permission to use this command");
						return true;
					}
					$types = array("ChickenPet","PigPet","WolfPet","RabbitPet","SilverfishPet",);
					$new = null;
					if(!isset($this->current[$sender->getName()])) {
						$this->current[$sender->getName()] = 0;
					}
					if($this->current[$sender->getName()] != count($types)-1) {
						$new = $this->current[$sender->getName()]+1;
					}else{
						$new = 0;
					}
					if($this->getPet($sender) !== null) {
						if($this->getPet($sender)->getNameTag() !== $this->getPet($sender)->getName()) {
							$this->namehold = $this->getPet($sender)->getNameTag();
						}
					}
					$this->changePet($sender, $types[$new]);
					if($this->namehold !== null) {
						$this->getPet($sender)->setName($this->namehold);
						$this->getPet($sender)->setNameTag($this->namehold);
					}
					$this->namehold = null;
					break;
				case "list":
					if(!$sender->hasPermission('pet.command.help')) {
						$sender->sendMessage(TF::RED . "You do not have permission to use this command");
						return true;
					}
					$sender->sendMessage(TF::YELLOW."=======".TF::BLUE."Pets List".TF::YELLOW."=======");
					$n = null;
					foreach($this->getServer()->getLevels() as $level) {
						foreach($level->getEntities() as $entity) {
							if($entity instanceof Pets) {
								if($entity->getNameTag() == $entity->getName()) {
									$sender->sendMessage($entity->getOwner()."s pet");
								}else {
									$sender->sendMessage($entity->getNameTag());
								}
								$n+=1;
							}
						}
					}
					$sender->sendMessage(TF::YELLOW."Total Pet Count is ".TF::BLUE.TF::BOLD.$n);
					break;
				case "clear":
					$n = null;
					foreach($this->getServer()->getLevels() as $level) {
						foreach($level->getEntities() as $entity) {
							if($entity instanceof Pets) {
								$entity->close();
								$n+=1;
							}
						}
					}
					$sender->sendMessage(TF::YELLOW."Total Cleared Pets are ".TF::BLUE.TF::BOLD.$n." Pets");
					break;
				default:
					if($sender->hasPermission('pet.command.help')) {
						$sender->sendMessage(TF::YELLOW."=======".TF::BLUE."Pets".TF::YELLOW."=======");
						$sender->sendMessage(TF::YELLOW."/pets help");
						$sender->sendMessage(TF::YELLOW."/pets cycle");
						$sender->sendMessage(TF::YELLOW."/pets name <Pet Name>");
						$sender->sendMessage(TF::YELLOW."/pets list");
						$sender->sendMessage(TF::YELLOW."/pets clear");
						return true;
					}else{
						$sender->sendMessage(TF::RED . "You do not have permission to use this command");
						return true;
					}
					break;
			}
			return true;
		}
		return false;
	}

	public function onDisable() {
		foreach($this->getServer()->getLevels() as $level) {
			foreach($level->getEntities() as $entity) {
				if($entity instanceof Pets) {
					$entity->close();
				}
			}
		}
		$this->getLogger()->notice(TF::GREEN."Disabled!");
	}

	public function create(Player $player,$type, Position $source, ...$args)
	{
		$chunk = $source->getLevel()->getChunk($source->x >> 4, $source->z >> 4, true);
		$nbt = new Compound("", [
			"Pos" => new Enum("Pos", [
				new Double("", $source->x),
				new Double("", $source->y),
				new Double("", $source->z)
			]),
			"Motion" => new Enum("Motion", [
				new Double("", 0),
				new Double("", 0),
				new Double("", 0)
			]),
			"Rotation" => new Enum("Rotation", [
				new Float("", $source instanceof Location ? $source->yaw : 0),
				new Float("", $source instanceof Location ? $source->pitch : 0)
			]),
		]);
		$pet = Entity::createEntity($type, $chunk, $nbt, ...$args);
		if ($pet instanceof Pets and !is_null($pet)) {
			$pet->setOwner($player);
			$pet->spawnToAll();
		}else{
			$player->sendMessage("");
		}
		return $pet;
	}

	public function createPet(Player $player, $type = null) {
		if (isset($this->pets[$player->getName()]) != true) {
			$pets = array("ChickenPet", "PigPet","WolfPet","BlazePet","RabbitPet","BatPet", "SilverfishPet", "MagmaPet", "OcelotPet");
			$len = rand(8, 12);
			$x = (-sin(deg2rad($player->yaw))) * $len  + $player->getX();
			$z = cos(deg2rad($player->yaw)) * $len  + $player->getZ();
			$y = $player->getLevel()->getHighestBlockAt($x, $z);

			$source = new Position($x , $y + 2, $z, $player->getLevel());
			if (!isset($type)) {
				$this->current[$player->getName()] = rand(0, count($pets)-1);
				$type = $pets[$this->current[$player->getName()]];
			}
			for($n = 0; $n != 9; $n+=1) {
				if($type === $pets[$n]) {
					$this->current[$player->getName()] = $n;
					break;
				}
			}
			$pet = $this->create($player,$type, $source);
			return $pet;
		}
		$player->sendMessage(TF::RED."You can only have one pet! This may be a glitch...");
		return null;
	}

	public function onPlayerQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer();
		$pet = $this->getPet($player);
		if (!is_null($pet)) {
			$this->disablePet($player);
		}
	}

	public function onDeath(EntityDeathEvent $event) {
		$entity = $event->getEntity();
		$attackerEvent = $entity->getLastDamageCause();
		if(!$entity instanceof Player and $entity instanceof Pets) {
			$this->disablePet($entity->getOwner());
			return;
		}
		if($entity instanceof Player) {
			if ($attackerEvent instanceof EntityDamageByEntityEvent) {
				$attacker = $attackerEvent->getDamager();
				if (isset(self::$pet[$entity->getName()])) {
					self::$pet[$entity->getName()]->setLastDamager($attacker->getName());
					self::$pet[$entity->getName()]->setPaused();
					return;
				}
			}
		}
		return;
	}

	public function onPlayerJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		$this->createPet($player);
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event) {
		$player = $event->getPlayer();
		$pos = $event->getRespawnPosition();
		self::$pet[$player->getName()]->setPaused(false);
		$this->getPet($player)->returnToOwner();
	}

	public function togglePet(Player $player) {
		if (isset(self::$pet[$player->getName()])) {
			self::$pet[$player->getName()]->close();
			unset(self::$pet[$player->getName()]);
			return;
		}
		self::$pet[$player->getName()] = $this->createPet($player);
	}

	public function disablePet(Player $player) {
		if (isset(self::$pet[$player->getName()])) {
			self::$pet[$player->getName()]->close();
			self::$pet[$player->getName()] = null;
		}
	}

	public function changePet(Player $player, $newtype) {
		$this->disablePet($player);
		self::$pet[$player->getName()] = $this->createPet($player, $newtype);
	}

	public function getPet(Player $player) {
		if(self::$pet instanceof Pets) {
			return self::$pet[$player->getName()];
		}else{
			return self::$pet[$player->getName()];
		}
	}

	public function sendPetMessage(Player $player, $reason = 1) {
		$availReasons = array(
			1 => "PET_WELCOME",
			2 => "PET_BYE",
			3 => "PET_RANDOM"
		);
		switch ($availReasons[$reason]) {
			case "PET_WELCOME":
				$messages = array(
					"Hey there Best Friend!",
					"Hi!",
					"Welcome Back!",
					"Where ya been?"
				);
				break;
			case "PET_BYE":
				$messages = array(
					"Bye!",
					"Bye Bye!",
					"see ya later!",
					"I'll Miss Ya!"
				);
				break;
			case "PET_RANDOM": //neutral messages that can be said anytime
				$messages = array(
					"I'm Hungry, do yo have any food?",
					"Test2",
					"Test3",
					"Test4"
				);
				break;
			default: //same as random messages
				$messages = array(
					"def1",
					"def2",
					"def3",
					"def4"
				);
				break;
		}
		$message = $messages[rand(0, count($messages) - 1)];
		$player->sendMessage($this->getPet($player)->getNameTag() . TF::WHITE ." > " .TF::GRAY. $message);
	}
}
