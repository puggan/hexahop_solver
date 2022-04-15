<?php

namespace Puggan\Solver;

/**
 * @implements \ArrayAccess<string, int[]>
 */
abstract class HashStorage implements \ArrayAccess
{
    public function offsetExists($offset): bool
    {
        return $this->get($offset) !== null;
    }

    /**
     * Fetch the hash
     *
     * @param string $hash primary key
     *
     * @return ?int[]
     */
    abstract public function get(string $hash): ?array;

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    //<editor-fold desc="ArrayAccess">

    public function offsetSet($offset, $value): void
    {
        $this->save($offset, $value);
    }

    /**
     * Add/Replace a hash
     *
     * @param int[] $path
     */
    abstract public function save(string $hash, array $path): void;

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * Remove a hash
     */
    abstract public function remove(string $hash): void;
    //</editor-fold>
}
