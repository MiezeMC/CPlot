<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\attributes\BlockListAttribute;
use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\plots\flags\FlagIDs;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\block\Block;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use Ramsey\Uuid\Uuid;

class BlockPlaceListener implements Listener {

    public function onBlockPlace(BlockPlaceEvent $event) : void {
        if ($event->isCancelled()) {
            return;
        }

        $position = $event->getBlock()->getPosition();
        $worldSettings = DataProvider::getInstance()->loadWorldIntoCache($position->getWorld()->getFolderName());
        if ($worldSettings === null) {
            LanguageManager::getInstance()->getProvider()->sendMessage($event->getPlayer(), ["prefix", "player.place.worldNotLoaded"]);
            $event->cancel();
            return;
        }
        if (!$worldSettings instanceof WorldSettings) {
            return;
        }

        $plot = Plot::loadFromPositionIntoCache($position);
        if ($plot instanceof BasePlot && !$plot instanceof Plot) {
            LanguageManager::getInstance()->getProvider()->sendMessage($event->getPlayer(), ["prefix", "player.place.plotNotLoaded"]);
            $event->cancel();
            return;
        }
        if ($plot instanceof Plot) {
            $player = $event->getPlayer();
            if ($player->hasPermission("cplot.place.plot")) {
                return;
            }

            if ($plot->isPlotOwner($player)) {
                return;
            }
            if ($plot->isPlotTrusted($player)) {
                return;
            }
            if ($plot->isPlotHelper($player)) {
                foreach ($plot->getPlotOwners() as $plotOwner) {
                    $owner = $plotOwner->getPlayerData()->getPlayer();
                    if ($owner !== null) {
                        return;
                    }
                }
            }

            $block = $event->getBlock();
            /** @var BlockListAttribute $flag */
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_PLACE);
            /** @var Block $value */
            foreach ($flag->getValue() as $value) {
                if ($block->isSameType($value)) {
                    return;
                }
            }

        } else {
            if ($event->getPlayer()->hasPermission("cplot.place.road")) {
                return;
            }
        }

        $event->cancel();
    }
}