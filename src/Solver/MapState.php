<?php

namespace Puggan\Solver;

abstract class MapState
{
    /**
     * @param mixed $data
     * @param ?array<int, string|int> $path
     */
    abstract public function __construct($data, ?array $path = null);

    /**
     * Player have won?
     */
    abstract public function won(): bool;

    /**
     * Execute all possible moves, and return a list
     * @return MapState[]
     */
    public function move_all(): array
    {
        /** @var MapState[] $states */
        $states = [];
        foreach ($this->possible_moves() as $move) {
            $states[$move] = $this->move($move);
        }
        return $states;
    }

    /**
     * The game allows thus moves to be executed
     * May include moves that make the player lose
     * @return int[]
     */
    abstract public function possible_moves(): array;

    /**
     * Clones the state, make a move, and return that new state
     *
     * @param int $move move/direction to travel
     *
     * @return static Pure function, no side-effect allowed, so not $this
     */
    public function move(int $move): self
    {
        $state = clone $this;
        $state->non_pure_move($move);
        return $state;
    }

    /**
     * Make a move in the current state
     *
     * @param int $direction move/direction to travel
     */
    abstract protected function non_pure_move(int $direction): void;

    /**
     * @return string uniq state hash, used to detect duplicates
     */
    abstract public function hash(): string;

    /**
     * @param int[] $path
     */
    public function path(array $path): self
    {
        $state = clone $this;
        foreach ($path as $move) {
            if ($state->lost()) {
                throw new \RuntimeException(
                    'Invalid path, already lost: ' . $state->print_path($path) . ' (' . implode(', ', $path) . ')'
                );
            }
            $state->non_pure_move($move);
        }
        return $state;
    }

    /**
     * Player have lost
     */
    abstract public function lost(): bool;

    /**
     * @param int[] $path
     */
    abstract public function print_path(array $path): string;

    /**
     * is the current state better that this other state?
     */
    abstract public function better(MapState $other): bool;

    public function impossible(): bool
    {
        return false;
    }
}
