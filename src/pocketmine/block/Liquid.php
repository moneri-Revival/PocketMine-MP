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

namespace pocketmine\block;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

abstract class Liquid extends Transparent{

	/** @var Vector3 */
	private $temporalVector = null;

	public $adjacentSources = 0;
	public $isOptimalFlowDirection = [0, 0, 0, 0];
	public $flowCost = [0, 0, 0, 0];

	/** @var Vector3|null */
	protected $flowVector = null;

	public function hasEntityCollision() : bool{
		return true;
	}

	public function isBreakable(Item $item) : bool{
		return false;
	}

	public function canBeReplaced() : bool{
		return true;
	}

	public function canBeFlowedInto() : bool{
		return true;
	}

	public function isSolid() : bool{
		return false;
	}

	public function getHardness() : float{
		return 100;
	}

	protected function recalculateBoundingBox() : ?AxisAlignedBB{
		return null;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function getFluidHeightPercent(){
		$d = $this->meta;
		if($d >= 8){
			$d = 0;
		}

		return ($d + 1) / 9;
	}

	protected function getFlowDecay(Vector3 $pos) : int{
		if(!($pos instanceof Block)){
			$pos = $this->level->getBlock($pos);
		}

		if($pos->getId() !== $this->getId()){
			return -1;
		}else{
			return $pos->getDamage();
		}
	}

	protected function getEffectiveFlowDecay(Vector3 $pos) : int{
		if(!($pos instanceof Block)){
			$pos = $this->level->getBlock($pos);
		}

		if($pos->getId() !== $this->getId()){
			return -1;
		}

		$decay = $pos->getDamage();

		if($decay >= 8){
			$decay = 0;
		}

		return $decay;
	}

	public function clearCaches() : void{
		parent::clearCaches();
		$this->flowVector = null;
	}

	public function getFlowVector() : Vector3{
		if($this->flowVector !== null){
			return $this->flowVector;
		}

		$vector = new Vector3(0, 0, 0);

		if($this->temporalVector === null){
			$this->temporalVector = new Vector3(0, 0, 0);
		}

		$decay = $this->getEffectiveFlowDecay($this);

		for($j = 0; $j < 4; ++$j){

			$x = $this->x;
			$y = $this->y;
			$z = $this->z;

			if($j === 0){
				--$x;
			}elseif($j === 1){
				++$x;
			}elseif($j === 2){
				--$z;
			}elseif($j === 3){
				++$z;
			}
			$sideBlock = $this->level->getBlockAt($x, $y, $z);
			$blockDecay = $this->getEffectiveFlowDecay($sideBlock);

			if($blockDecay < 0){
				if(!$sideBlock->canBeFlowedInto()){
					continue;
				}

				$blockDecay = $this->getEffectiveFlowDecay($this->level->getBlockAt($x, $y - 1, $z));

				if($blockDecay >= 0){
					$realDecay = $blockDecay - ($decay - 8);
					$vector->x += ($sideBlock->x - $this->x) * $realDecay;
					$vector->y += ($sideBlock->y - $this->y) * $realDecay;
					$vector->z += ($sideBlock->z - $this->z) * $realDecay;
				}

				continue;
			}else{
				$realDecay = $blockDecay - $decay;
				$vector->x += ($sideBlock->x - $this->x) * $realDecay;
				$vector->y += ($sideBlock->y - $this->y) * $realDecay;
				$vector->z += ($sideBlock->z - $this->z) * $realDecay;
			}
		}

		if($this->getDamage() >= 8){
			$falling = false;

			if(!$this->level->getBlockAt($this->x, $this->y, $this->z - 1)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x, $this->y, $this->z + 1)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x - 1, $this->y, $this->z)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x + 1, $this->y, $this->z)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x, $this->y + 1, $this->z - 1)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x, $this->y + 1, $this->z + 1)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x - 1, $this->y + 1, $this->z)->canBeFlowedInto()){
				$falling = true;
			}elseif(!$this->level->getBlockAt($this->x + 1, $this->y + 1, $this->z)->canBeFlowedInto()){
				$falling = true;
			}

			if($falling){
				$vector = $vector->normalize()->add(0, -6, 0);
			}
		}

		return $this->flowVector = $vector->normalize();
	}

	public function addVelocityToEntity(Entity $entity, Vector3 $vector) : void{
		$flow = $this->getFlowVector();
		$vector->x += $flow->x;
		$vector->y += $flow->y;
		$vector->z += $flow->z;
	}

	abstract public function tickRate() : int;

	/**
	 * Returns how many liquid levels are lost per block flowed horizontally. Affects how far the liquid can flow.
	 *
	 * @return int
	 */
	public function getLiquidLevelDecreasePerBlock() : int{
		return 1;
	}

	public function onUpdate(int $type){
		if($type === Level::BLOCK_UPDATE_NORMAL){
			$this->checkForHarden();
			$this->level->scheduleDelayedBlockUpdate($this, $this->tickRate());
		}elseif($type === Level::BLOCK_UPDATE_SCHEDULED){
			if($this->temporalVector === null){
				$this->temporalVector = new Vector3(0, 0, 0);
			}

			$decay = $this->getFlowDecay($this);
			$multiplier = $this->getLiquidLevelDecreasePerBlock();

			if($decay > 0){
				$smallestFlowDecay = -100;
				$this->adjacentSources = 0;
				$smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlockAt($this->x, $this->y, $this->z - 1), $smallestFlowDecay);
				$smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlockAt($this->x, $this->y, $this->z + 1), $smallestFlowDecay);
				$smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlockAt($this->x - 1, $this->y, $this->z), $smallestFlowDecay);
				$smallestFlowDecay = $this->getSmallestFlowDecay($this->level->getBlockAt($this->x + 1, $this->y, $this->z), $smallestFlowDecay);

				$newDecay = $smallestFlowDecay + $multiplier;

				if($newDecay >= 8 or $smallestFlowDecay < 0){
					$newDecay = -1;
				}

				if(($topFlowDecay = $this->getFlowDecay($this->level->getBlockAt($this->x, $this->y + 1, $this->z))) >= 0){
					if($topFlowDecay >= 8){
						$newDecay = $topFlowDecay;
					}else{
						$newDecay = $topFlowDecay | 0x08;
					}
				}

				if($this->adjacentSources >= 2 and $this instanceof Water){
					$bottomBlock = $this->level->getBlockAt($this->x, $this->y - 1, $this->z);
					if($bottomBlock->isSolid()){
						$newDecay = 0;
					}elseif($bottomBlock instanceof Water and $bottomBlock->getDamage() === 0){
						$newDecay = 0;
					}
				}

				if($newDecay !== $decay){
					$decay = $newDecay;
					if($decay < 0){
						$this->level->setBlock($this, BlockFactory::get(Block::AIR), true, true);
					}else{
						$this->level->setBlock($this, BlockFactory::get($this->id, $decay), true, true);
						$this->level->scheduleDelayedBlockUpdate($this, $this->tickRate());
					}
				}
			}

			if($decay >= 0){
				$bottomBlock = $this->level->getBlockAt($this->x, $this->y - 1, $this->z);

				if($this instanceof Lava and $bottomBlock instanceof Water){
					$this->level->setBlock($bottomBlock, BlockFactory::get(Block::STONE), true, true);
				}else{
					$this->flowIntoBlock($bottomBlock, $decay | 0x08);
				}

				if($decay === 0 or !$bottomBlock->canBeFlowedInto()){
					$flags = $this->getOptimalFlowDirections();

					$l = $decay + $multiplier;

					if($decay >= 8){
						$l = 1;
					}

					if($l >= 8){
						$this->checkForHarden();

						return;
					}

					if($flags[0]){
						$this->flowIntoBlock($this->level->getBlockAt($this->x - 1, $this->y, $this->z), $l);
					}

					if($flags[1]){
						$this->flowIntoBlock($this->level->getBlockAt($this->x + 1, $this->y, $this->z), $l);
					}

					if($flags[2]){
						$this->flowIntoBlock($this->level->getBlockAt($this->x, $this->y, $this->z - 1), $l);
					}

					if($flags[3]){
						$this->flowIntoBlock($this->level->getBlockAt($this->x, $this->y, $this->z + 1), $l);
					}
				}

				$this->checkForHarden();
			}
		}
	}

	private function flowIntoBlock(Block $block, int $newFlowDecay) : void{
		if($block->canBeFlowedInto() and !($block instanceof Liquid)){
			if($block->getId() > 0){
				$this->level->useBreakOn($block);
			}

			$this->level->setBlock($block, BlockFactory::get($this->getId(), $newFlowDecay), true, true);
			$this->level->scheduleDelayedBlockUpdate($block, $this->tickRate());
		}
	}

	private function calculateFlowCost(Block $block, int $accumulatedCost, int $prevX, int $prevZ, int $maxCost) : int{
		$cost = 1000;

		for($j = 0; $j < 4; ++$j){
			$x = $block->x;
			$y = $block->y;
			$z = $block->z;

			if($j === 0){
				--$x;
			}elseif($j === 1){
				++$x;
			}elseif($j === 2){
				--$z;
			}elseif($j === 3){
				++$z;
			}

			if($prevX === $x and $prevZ === $z){
				continue;
			}

			$blockSide = $this->level->getBlockAt($x, $y, $z);

			if(!$blockSide->canBeFlowedInto()){
				continue;
			}elseif($this->level->getBlockAt($x, $y - 1, $z)->canBeFlowedInto()){
				return $accumulatedCost;
			}

			if($accumulatedCost >= $maxCost){
				continue;
			}

			$realCost = $this->calculateFlowCost($blockSide, $accumulatedCost + 1, $x, $z, $maxCost);

			if($realCost < $cost){
				$cost = $realCost;
			}
		}

		return $cost;
	}

	/**
	 * @return bool[]
	 */
	private function getOptimalFlowDirections() : array{
		if($this->temporalVector === null){
			$this->temporalVector = new Vector3(0, 0, 0);
		}

		$maxCost = 4;
		for($j = 0; $j < 4; ++$j){
			$this->flowCost[$j] = 1000;

			$x = $this->x;
			$y = $this->y;
			$z = $this->z;

			if($j === 0){
				--$x;
			}elseif($j === 1){
				++$x;
			}elseif($j === 2){
				--$z;
			}elseif($j === 3){
				++$z;
			}
			$block = $this->level->getBlockAt($x, $y, $z);

			if(!$block->canBeFlowedInto()){
				continue;
			}elseif($this->level->getBlockAt($x, $y - 1, $z)->canBeFlowedInto()){
				$this->flowCost[$j] = $maxCost = 0;
			}elseif($maxCost > 0){
				$this->flowCost[$j] = $this->calculateFlowCost($block, 1, $this->x, $this->z, $maxCost);
				$maxCost = min($maxCost, $this->flowCost[$j]);
			}
		}

		$minCost = min($this->flowCost);

		for($i = 0; $i < 4; ++$i){
			$this->isOptimalFlowDirection[$i] = ($this->flowCost[$i] === $minCost);
		}

		return $this->isOptimalFlowDirection;
	}

	private function getSmallestFlowDecay(Vector3 $pos, int $decay) : int{
		$blockDecay = $this->getFlowDecay($pos);

		if($blockDecay < 0){
			return $decay;
		}elseif($blockDecay === 0){
			++$this->adjacentSources;
		}elseif($blockDecay >= 8){
			$blockDecay = 0;
		}

		return ($decay >= 0 && $blockDecay >= $decay) ? $decay : $blockDecay;
	}

	protected function checkForHarden(){

	}
}