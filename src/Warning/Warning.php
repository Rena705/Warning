<?php

namespace Warning;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\utils\Config;

class Warning extends PluginBase implements Listener {

	public function onEnable() {
		$this->getLogger()->info("§aWarning Loaded.");
		$this->getLogger()->warning("二次配布を禁止します。");
		$this->getLogger()->Notice("§aAuthor§f: れな.705 (れな/Rena705)");
		$this->getLogger()->Notice("§bTwitter§f: https://twitter.com/Rena705   §6( @Rena705 )");
		$this->getLogger()->Notice("§cYouTube§f: https://www.youtube.com/channel/UCC258KxM4dAqPnpA8BJ9w3w");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable() {
		$this->getLogger()->info("§cWarning Ended.");
	}

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$folder = $this->getFolder($name);
		$this->data[$name] = new Config($folder, Config::JSON, [
			"warn" => "0",
			"reason" => "",
			"block" => false,
			"command" => false,
			"chat" => false,
		]);
		$this->Warning($player);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		$name = strtolower($sender->getName());
		if ($label === "warn") {
			if ($name === "console") {
				$sender->sendMessage("§cConsoleから実行することはできません。");
			} else {
				$data = [
					"type" => "custom_form",
					"title" => "§cWarning",
					"content" => [
						[
							"type" => "label",
							"text" => "§l警告を付与するプレイヤー名を記入してください。"
						],
						[
							"type" => "input",
							"text" => "§lプレイヤー名",
							"placeholder" => "",
							"default" => ""
						]
					]
				];
				$this->createWindow($sender, $data, 64890);
			}
		} elseif ($label === "checkwarn") {
			if ($name === "console") {
				$sender->sendMessage("§cConsoleから実行することはできません。");
			} else {
				$warn = $this->data[$name]->get("warn");
				$block = $this->Check($this->data[$name]->get("block"));
				$command = $this->Check($this->data[$name]->get("command"));
				$chat = $this->Check($this->data[$name]->get("chat"));
				if ($warn == 0) {
					$sender->sendMessage(
						"§a警告は付与されていません。\n".
						"§eブロックの設置・破壊を禁止§f: {$block}\n".
						"§eコマンドの使用を禁止§f: {$command}\n".
						"§eチャットの使用を禁止§f: {$chat}"
					);
				} else {
					$reason = str_replace("#", "\n§r", $this->data[$name]->get("reason"));
					$sender->sendMessage(
						"§e警告レベル§f: ".$this->data[$name]->get("warn")."\n".
						"§e警告理由§f:\n{$reason}\n".
						"§eブロックの設置・破壊を禁止§f: {$block}\n".
						"§eコマンドの使用を禁止§f: {$command}\n".
						"§eチャットの使用を禁止§f: {$chat}"
					);
				}
			}
		}
		return true;
	}

	public function onDataPacketReceiveEvent(DataPacketReceiveEvent $event) {
		$pk = $event->getPacket();
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		if ($pk instanceof ModalFormResponsePacket) {
			$id = $pk->formId;
			$data = $pk->formData;
			$result = json_decode($data);
			if ($data == "null\n") {
			} else {
				if ($id === 64890) {
					$target_name = strtolower($result[1]);
					if ($target_name === "") {
						$player->sendMessage("§c未記入です。");
					} else {
						$result = array_map(function($file){return basename ($file, ".json");}, glob($this->getDataFolder() . "/*/*{$target_name}*.json"));
						$data = [
							"type" => "custom_form",
							"title" => "§cWarning",
							"content" => [
								[
									"type" => "dropdown",
									"text" => "§l警告を付与するプレイヤーを選択してください。",
									"options" => $result
								]
							]
						];
						$this->createWindow($player, $data, 64891);
						$this->WarnData[$name] = $result;
					}
				} elseif ($id === 64891) {
					if (isset($this->WarnData[$name])) {
						if (empty($this->WarnData[$name])) {
							$player->sendMessage("§c存在しない値を選択していた為、実行できませんでした。");
						} else {
							$player_data = $this->WarnData[$name];
							$target_name = $player_data[$result[0]];
							$this->WarnPlayerData[$name] = $target_name;
							if (!isset($this->data[$target_name])) {
								if (file_exists($this->getFolder($target_name))) {
									$this->data[$target_name] = new Config($this->getFolder($target_name), Config::JSON, [
										"warn" => "0",
										"reason" => "",
										"block" => false,
										"command" => false,
										"chat" => false,
									]);
									$this->data[$target_name]->save();
								} else {
									$player->sendMessage("§cそのプレイヤーは存在しません");
									return true;
								}
							}
							$data = [
								"type" => "custom_form",
								"title" => "§cWarning",
								"content" => [
									[
										"type" => "label",
										"text" => "§l{$target_name} に警告を付与します。"
									],
									[
										"type" => "step_slider",
										"text" => "§l警告レベル",
										"steps" => array("§l§a0   ", "§l§e1   ⚠", "§l§62   ⚠", "§l§c3   ⚠"),
										"default" => (int) $this->data[$target_name]->get("warn")
									],
									[
										"type" => "input",
										"text" => "§l警告理由\n#を入力すると改行できます。",
										"placeholder" => "",
										"default" => $this->data[$target_name]->get("reason")
									],
									[
										"type" => "toggle",
										"text" => "§lブロックの設置・破壊を禁止",
										"default" => $this->data[$target_name]->get("block"),
									],
									[
										"type" => "toggle",
										"text" => "§lコマンドの使用を禁止",
										"default" => $this->data[$target_name]->get("command"),
									],
									[
										"type" => "toggle",
										"text" => "§lチャットの使用を禁止",
										"default" => $this->data[$target_name]->get("chat"),
									],
								]
							];
							$this->createWindow($player, $data, 64892);
							unset($this->WarnData[$name]);
						}
					} else {
						$player->sendMessage("§cデータに不具合が発生しました。");
					}
				} elseif ($id === 64892) {
					if (isset($this->WarnPlayerData[$name])) {
						$target_name = $this->WarnPlayerData[$name];
						$warn = (string) $result[1];
						$reason = (string) $result[2];
						$block = $result[3];
						$command = $result[4];
						$chat = $result[5];
						$this->data[$target_name]->set("warn", $warn);
						$this->data[$target_name]->set("reason", $reason);
						$this->data[$target_name]->set("block", $block);
						$this->data[$target_name]->set("command", $command);
						$this->data[$target_name]->set("chat", $chat);
						$this->data[$target_name]->save();
						$block = $this->Check($result[3]);
						$command = $this->Check($result[4]);
						$chat = $this->Check($result[5]);
						$reason = str_replace("#", "\n§r§l", $reason);
						$data = [
							"type" => "custom_form",
							"title" => "§cWarning",
							"content" => [
								[
									"type" => "label",
									"text" => "§l".$target_name." への警告の付与が完了しました。"
								],
								[
									"type" => "label",
									"text" => "§l§c警告レベル§f: {$warn}\n§e警告理由§f:\n{$reason}"
								],
								[
									"type" => "label",
									"text" => "§l§eブロックの設置・破壊を禁止§f: {$block}\n"
								],
								[
									"type" => "label",
									"text" => "§l§eコマンドの使用を禁止§f: {$command}\n"
								],
								[
									"type" => "label",
									"text" => "§l§eチャットの使用を禁止§f: {$chat}\n"
								],
							]
						];
						$this->createWindow($player, $data, 3003);
						$target = $this->getServer()->getPlayer($target_name);
						if (isset($target)) {
							$this->Warning($target);
						}
					}
				}
			}
		}
	}

	public function Check($bool) {
		if ($bool == false) {
			return "§c無効";
		} elseif ($bool == true) {
			return "§b有効";
		}
	}

	public function Warning(Player $player) {
		$name = strtolower($player->getName());
		$int = (int) $this->data[$name]->get("warn");
		if ($int == 0) {
			$player->setNameTag($player->getName());
			$player->setDisplayName($player->getName());
		} else {
			$Mark = $this->Mark($int);
			$player->setNameTag($Mark.$player->getName());
			$player->setDisplayName($Mark.$player->getName());
		}
	}

	public function Mark(int $int) {
		if ($int == 1) {
			$color = "§e";
		} elseif ($int == 2) {
			$color = "§6";
		} elseif ($int == 3) {
			$color = "§c";
		}
		$Mark = $color."⚠§r ";
		return $Mark;
	}

	public function getFolder($name) {
		$sub = substr($name, 0, 1);
		$upper = strtoupper($sub);
		$folder = $this->getDataFolder().$upper.'/';
		if (!file_exists($folder)) mkdir($folder);
		$lower = strtolower($name);
		return $folder .= $lower.'.json';
	}

	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		if ($this->data[$name]->get("block") == true) {
			$event->setCancelled();
			$player->addActionBarMessage("§cブロックの設置・破壊を禁止されています。");
		}
	}

	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		if ($this->data[$name]->get("block") == true) {
			$event->setCancelled();
			$player->addActionBarMessage("§cブロックの設置・破壊を禁止されています。");
		}
	}

	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$msg = $event->getMessage();
		if (substr($msg, 0, 1) === "/") {
			if ($this->data[$name]->get("command") == true) {
				if (substr($msg, 0, 5) !== "/warn") {
					$event->setCancelled();
					$player->addActionBarMessage("§cコマンドの使用を禁止されています。");
				}
			}
			if ($this->data[$name]->get("chat") == true) {
				if (substr($msg, 0, 3) === "/me" || substr($msg, 0, 4) === "/say") {
					$event->setCancelled();
					$player->addActionBarMessage("§cチャットの使用を禁止されています。");
				}
			}
		}
	}

	public function onChat(PlayerChatEvent $event) {
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$msg = $event->getMessage();
		if ($this->data[$name]->get("chat") == true) {
			$event->setCancelled();
			$player->addActionBarMessage("§cチャットの使用を禁止されています。");
		}
	}

	public function createWindow(Player $player, $data, int $id) {
		$pk = new ModalFormRequestPacket();
		$pk->formId = $id;
		$pk->formData = json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE);
		$player->dataPacket($pk);
	}
}