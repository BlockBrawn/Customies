<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use InvalidArgumentException;

final class UseModifiersComponent implements ItemComponent {

	private float $useDuration;
	private float $movementModifier;

	/**
	 * Determines how long an item takes to use in combination with components such as Shooter, Throwable, or Food.
	 * @param float $useDuration How long the item takes to use in seconds
	 * @param float $movementModifier Modifier value to scale the players movement speed when item is in use
	 * @throws InvalidArgumentException if `$movementModifier` is out of bounds (0.0 to 1.0).
	 */
	public function __construct(float $movementModifier, float $useDuration = 0) {
		if($movementModifier < 0.0 || $movementModifier > 1.0){
			throw new InvalidArgumentException("movementModifier must be between 0.0 or 1.0");
		}
		$this->useDuration = $useDuration;
		$this->movementModifier = $movementModifier;
	}

	public function getName(): string {
		return "minecraft:use_modifiers";
	}

	public function getValue(): array {
		return [
			"movement_modifier" => $this->movementModifier,
			"use_duration" => $this->useDuration
		];
	}

	public function isProperty(): bool {
		return false;
	}
}