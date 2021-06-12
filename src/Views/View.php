<?php

declare(strict_types=1);

namespace Puggan\Views;

use Puggan\Solver\HexaHop\HexaHopMap;

abstract class View
{
    abstract public function header(HexaHopMap $map): string;

    abstract public function map(HexaHopMap $map): string;
}
