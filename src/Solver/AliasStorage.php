<?php

	namespace Puggan\Solver;

	interface AliasStorage
	{
		/**
		 * @param int[] $alias
		 * @param int[] $better
		 */
		public function add($alias, $better);
	}
