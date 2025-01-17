<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\plots;

use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\worlds\WorldSettings;

class MergePlot extends BasePlot {

    protected int $originX;
    protected int $originZ;

    public function __construct(string $worldName, WorldSettings $worldSettings, int $x, int $z, int $originX, int $originZ) {
        parent::__construct($worldName, $worldSettings, $x, $z);
        $this->originX = $originX;
        $this->originZ = $originZ;
    }

    public function getOriginX() : int {
        return $this->originX;
    }

    public function getOriginZ() : int {
        return $this->originZ;
    }

    public function toBasePlot() : BasePlot {
        return new BasePlot($this->worldName, $this->worldSettings, $this->x, $this->z);
    }

    /**
     * @phpstan-return \Generator<int, mixed, Plot|null, Plot|null>
     */
    public function toPlot() : \Generator {
        return yield DataProvider::getInstance()->awaitPlot($this->worldName, $this->originX, $this->originZ);
    }

    public static function fromBasePlot(BasePlot $basePlot, int $originX, int $originZ) : self {
        return new self(
            $basePlot->getWorldName(), $basePlot->getWorldSettings(), $basePlot->getX(), $basePlot->getZ(),
            $originX, $originZ
        );
    }

    /**
     * @phpstan-return array{worldName: string, worldSettings: string, x: int, z: int, originX: int, originZ: int}
     */
    public function __serialize() : array {
        $data = parent::__serialize();
        $data["originX"] = $this->originX;
        $data["originZ"] = $this->originZ;
        return $data;
    }

    /**
     * @phpstan-param array{worldName: string, worldSettings: string, x: int, z: int, originX: int, originZ: int} $data
     */
    public function __unserialize(array $data) : void {
        parent::__unserialize($data);
        $this->originX = $data["originX"];
        $this->originZ = $data["originZ"];
    }
}