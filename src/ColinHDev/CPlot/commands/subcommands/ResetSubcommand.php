<?php

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\provider\EconomyProvider;
use ColinHDev\CPlot\tasks\async\PlotResetAsyncTask;
use ColinHDev\CPlotAPI\BasePlot;
use ColinHDev\CPlotAPI\flags\FlagIDs;
use ColinHDev\CPlotAPI\flags\FlagManager;
use ColinHDev\CPlotAPI\Plot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;

class ResetSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : void {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.senderNotOnline"));
            return;
        }

        $worldSettings = $this->getPlugin()->getProvider()->getWorld($sender->getWorld()->getFolderName());
        if ($worldSettings === null) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.noPlotWorld"));
            return;
        }

        $plot = Plot::fromPosition($sender->getPosition());
        if ($plot === null) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.noPlot"));
            return;
        }
        if (!$sender->hasPermission("cplot.admin.reset")) {
            if ($plot->getOwnerUUID() === null) {
                $sender->sendMessage($this->getPrefix() . $this->translateString("reset.noPlotOwner"));
                return;
            } else if ($plot->getOwnerUUID() !== $sender->getUniqueId()->toString()) {
                $sender->sendMessage($this->getPrefix() . $this->translateString("reset.notPlotOwner", [$this->getPlugin()->getProvider()->getPlayerNameByUUID($plot->getOwnerUUID()) ?? "ERROR"]));
                return;
            }
        }

        if (!$plot->loadFlags()) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.loadFlagsError"));
            return;
        }
        $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_SERVER_PLOT);
        if ($flag->getValue() === true) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.serverPlotFlag", [$flag->getID() ?? FlagIDs::FLAG_SERVER_PLOT]));
            return;
        }

        if (!$plot->loadMergedPlots()) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.loadMergedPlotsError"));
            return;
        }

        $economyProvider = $this->getPlugin()->getEconomyProvider();
        if ($economyProvider !== null) {
            $price = $economyProvider->getPrice(EconomyProvider::PRICE_RESET) ?? 0.0;
            if ($price > 0.0) {
                $money = $economyProvider->getMoney($sender);
                if ($money === null) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("reset.loadMoneyError"));
                    return;
                }
                if ($money < $price) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("reset.notEnoughMoney", [$economyProvider->getCurrency(), $economyProvider->parseMoneyToString($price), $economyProvider->parseMoneyToString($price - $money)]));
                    return;
                }
                if (!$economyProvider->removeMoney($sender, $price, "Paid " . $price . $economyProvider->getCurrency() . " to reset the plot " . $plot->toString() . ".")) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("reset.saveMoneyError"));
                    return;
                }
                $sender->sendMessage($this->getPrefix() . $this->translateString("reset.chargedMoney", [$economyProvider->getCurrency(), $economyProvider->parseMoneyToString($price)]));
            }
        }

        $sender->sendMessage($this->getPrefix() . $this->translateString("reset.start"));
        $task = new PlotResetAsyncTask($worldSettings, $plot);
        $world = $sender->getWorld();
        $task->setWorld($world);
        $task->setClosure(
            function (int $elapsedTime, string $elapsedTimeString, array $result) use ($world, $sender) {
                [$plotCount, $plots] = $result;
                $plots = array_map(
                    function (BasePlot $plot) : string {
                        return $plot->toSmallString();
                    },
                    $plots
                );
                Server::getInstance()->getLogger()->debug(
                    "Resetting plot" . ($plotCount > 1 ? "s" : "") . " in world " . $world->getDisplayName() . " (folder: " . $world->getFolderName() . ") took " . $elapsedTimeString . " (" . $elapsedTime . "ms) for player " . $sender->getUniqueId()->toString() . " (" . $sender->getName() . ") for " . $plotCount . " plot" . ($plotCount > 1 ? "s" : "") . ": [" . implode(", ", $plots) . "]."
                );
                if (!$sender->isConnected()) return;
                $sender->sendMessage($this->getPrefix() . $this->translateString("reset.finish", [$elapsedTimeString]));
            }
        );
        if (!$this->getPlugin()->getProvider()->deletePlot($plot)) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("reset.deletePlotError"));
            return;
        }
        $this->getPlugin()->getServer()->getAsyncPool()->submitTask($task);
    }
}