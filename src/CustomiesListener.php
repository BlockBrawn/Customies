<?php
declare(strict_types=1);

namespace customiesdevs\customies;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use function method_exists;

final class CustomiesListener implements Listener {

	private ?ItemRegistryPacket $cachedItemComponentPacket = null;
	/** @var ItemTypeEntry[] */
	private array $cachedItemTable = [];
	/** @var BlockPaletteEntry[] */
	private array $cachedBlockPalette = [];
	private Experiments $experiments;

	public function __construct() {
		$this->experiments = new Experiments([
			// "data_driven_items" is required for custom blocks to render in-game. With this disabled, they will be
			// shown as the UPDATE texture block.
			"data_driven_items" => true,
		], true);
	}

	public function onDataPacketSend(DataPacketSendEvent $event): void {
		foreach($event->getPackets() as $packet){
			if($packet instanceof BiomeDefinitionListPacket) {
				foreach($event->getTargets() as $session){
					if(method_exists($session, "getProtocolId")) {
						// https://github.com/NetherGamesMC/PocketMine-MP/blob/9bb498806586517473012b2e7fab12402dcd3cd5/src/network/mcpe/handler/PreSpawnPacketHandler.php#L117-L120
						$protocolId = $session->getProtocolId();
						if($protocolId >= ProtocolInfo::PROTOCOL_1_21_60) {
							return;
						}
						// ItemComponentPacket needs to be sent after the BiomeDefinitionListPacket.
						if($this->cachedItemComponentPacket === null) {
							// Wait for the data to be needed before it is actually cached. Allows for all blocks and items to be
							// registered before they are cached for the rest of the runtime.
							$this->cachedItemComponentPacket = ItemRegistryPacket::create(CustomiesItemFactory::getInstance()->getItemTableEntries());
						}
						$session->sendDataPacket($this->cachedItemComponentPacket);
					}
					break;//expect there is only one recipient
				}
			} elseif($packet instanceof StartGamePacket) {
				$protocolId = ProtocolInfo::CURRENT_PROTOCOL;
				foreach($event->getTargets() as $session){
					if(method_exists($session, "getProtocolId")) {
						$protocolId = $session->getProtocolId();
					}
					break;//expect there is only one recipient
				}
				$packet->levelSettings->experiments = $this->experiments;
				$packet->blockPalette = $this->cachedBlockPalette[$protocolId] ??= CustomiesBlockFactory::getInstance()->getBlockPaletteEntries($protocolId);
			} elseif($packet instanceof ResourcePackStackPacket) {
				$packet->experiments = $this->experiments;
			}
		}
	}
}
