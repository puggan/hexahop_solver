<?php

namespace Puggan\Solver;

abstract class TodoStorage
{
    /**
     * Adds a path to the todo
     * @param int[] $path
     */
    abstract public function add(array $path): void;

    /**
     * Reserve a todo, that's not already reserved
     * @param int $pid
     *
     * @return false|int[]
     */
    abstract public function reserve(int $pid): array|bool;

    /**
     * @param int[] $path
     */
    abstract public function remove(array $path): void;

    /**
     * @param int[] $path
     */
    abstract public function remove_all(array $path): void;
}
