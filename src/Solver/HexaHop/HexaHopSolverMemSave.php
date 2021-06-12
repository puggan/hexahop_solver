<?php

namespace Puggan\Solver\HexaHop;

use Puggan\Solver\MapState;
use Puggan\Solver\SolverNoSave;

class HexaHopSolverMemSave extends SolverNoSave
{
    /** @var int[][][][][] */
    private array $position_hashes = [];

    /*
    public function __construct($startState, $solved)
    {
        parent::__construct($startState, $solved);
    }
    */

    public function hash_test(string $hash, MapState $state): bool
    {
        if (parent::hash_test($hash, $state)) {
            return true;
        }

        $path = array_slice($this->path, 0, $this->depth);
        $player = $state->player();
        $position_hashes = &$this->position_hashes[$player->z][$player->y][$player->x];
        if (isset($position_hashes[$hash]) && count($position_hashes[$hash]) <= $this->depth) {
            return true;
        }
        $position_hashes[$hash] = $path;
        if (count($position_hashes) > 3) {
            array_shift($position_hashes);
        }
        return false;
    }
}
