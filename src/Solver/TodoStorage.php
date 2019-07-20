<?php

	namespace Puggan\Solver;

	abstract class TodoStorage
	{
		/**
		 * Adds a path to the todo
		 * @param int[] $path
		 */
		abstract public function add($path): void;

		/**
		 * Reserve a todo, that's not already reserved
		 * @param int $pid
		 *
		 * @return false|int[]
		 */
		abstract public function reserve($pid);

		/**
		 * @param int[] $path
		 */
		abstract public function remove($path): void;

		/**
		 * @param int[] $path
		 */
		abstract public function remove_all($path): void;
	}
