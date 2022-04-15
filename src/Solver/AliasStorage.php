<?php

namespace Puggan\Solver;

interface AliasStorage
{
    /**
     * @param int[] $alias
     * @param int[] $better
     */
    public function add(array $alias, array $better): void;
}
