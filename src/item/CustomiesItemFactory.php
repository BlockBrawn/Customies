<?php
declare(strict_types=1);

namespace customiesdevs\customies\item;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\inventory\CreativeCategory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionClass;
use function array_values;
use function defined;

final class CustomiesItemFactory {
	use SingletonTrait;

	/**
	 * @var ItemTypeEntry[]
	 */
	private array $itemTableEntries = [];

	/**
	 * Get a custom item from its identifier. An exception will be thrown if the item is not registered.
	 */
	public function get(string $identifier, int $amount = 1): Item {
		$item = StringToItemParser::getInstance()->parse($identifier);
		if($item === null) {
			throw new InvalidArgumentException("Custom item " . $identifier . " is not registered");
		}
		return $item->setCount($amount);
	}

	/**
	 * Returns custom item entries
	 * @return ItemTypeEntry[]
	 */
	public function getItemTableEntries(): array {
		return array_values($this->itemTableEntries);
	}

	/**
	 * Registers the item to the item factory and assigns it an ID. It also updates the required mappings and stores the
	 * item components if present.
	 * @phpstan-param class-string $className
	 */
	public function registerItem(string $className, string $identifier, string $name, ?CreativeCategory $category = null): void {
		if($className !== Item::class) {
			Utils::testValidInstance($className, Item::class);
		}

		$itemId = ItemTypeIds::newId();
		$item = new $className(new ItemIdentifier($itemId), $name);

		GlobalItemDataHandlers::getDeserializer()->map($identifier, fn() => clone $item);
		GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData($identifier));

		StringToItemParser::getInstance()->register($identifier, fn() => clone $item);

		$nbt = ($componentBased = $item instanceof ItemComponents) ? $item->getComponents()
			->setInt("id", $itemId)
			->setString("name", $identifier) : CompoundTag::create();

		$this->itemTableEntries[$identifier] = $entry = new ItemTypeEntry($identifier, $itemId, $componentBased, $componentBased ? 1 : 0, new CacheableNbt($nbt));
		$this->registerCustomItemMapping($identifier, $itemId, $entry);
		CreativeItemManager::getInstance()->addItem($item, $category);
	}

	/**
	 * Registers a custom item ID to the required mappings in the global ItemTypeDictionary instance.
	 */
	private function registerCustomItemMapping(string $stringId, int $id, ItemTypeEntry $entry): void {
		if(defined(ProtocolInfo::class . "::ACCEPTED_PROTOCOL")){
			foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocol){
				$this->registerCustomItemMappingToDictionary(TypeConverter::getInstance($protocol)->getItemTypeDictionary(), $stringId, $id, $entry);
			}
		}else{
			$this->registerCustomItemMappingToDictionary(TypeConverter::getInstance()->getItemTypeDictionary(), $stringId, $id, $entry);
		}
	}

	private function registerCustomItemMappingToDictionary(ItemTypeDictionary $dictionary, string $identifier, int $itemId, ItemTypeEntry $entry): void {
		$reflection = new ReflectionClass($dictionary);

		$intToString = $reflection->getProperty("intToStringIdMap");
		/** @var string[] $value */
		$value = $intToString->getValue($dictionary);
		$value[$itemId] = $identifier;
		$intToString->setValue($dictionary, $value);

		$stringToInt = $reflection->getProperty("stringToIntMap");
		/** @var int[] $value */
		$value = $stringToInt->getValue($dictionary);
		$value[$identifier] = $itemId;
		$stringToInt->setValue($dictionary, $value);

		$itemTypes = $reflection->getProperty("itemTypes");
		$value = $itemTypes->getValue($dictionary);
		$value[] = $entry;
		$itemTypes->setValue($dictionary, $value);
	}

	/**
	 * Registers the required mappings for the block to become an item that can be placed etc. It is assigned an ID that
	 * correlates to its block ID.
	 */
	public function registerBlockItem(string $identifier, Block $block): void {
		$itemId = $block->getIdInfo()->getBlockTypeId();
		StringToItemParser::getInstance()->registerBlock($identifier, fn() => clone $block);
		$this->itemTableEntries[] = $entry = new ItemTypeEntry($identifier, $itemId, false, 2, new CacheableNbt(CompoundTag::create()));
		$this->registerCustomItemMapping($identifier, $itemId, $entry);

		$blockItemIdMap = BlockItemIdMap::getInstance();
		$reflection = new ReflectionClass($blockItemIdMap);

		$itemToBlockId = $reflection->getProperty("itemToBlockId");
		/** @var string[] $value */
		$value = $itemToBlockId->getValue($blockItemIdMap);
		$itemToBlockId->setValue($blockItemIdMap, $value + [$identifier => $identifier]);
	}
}
