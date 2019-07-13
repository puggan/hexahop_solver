<?php

	namespace Puggan\Solver;

	class AliasHashStorage implements AliasStorage
	{
		private $storage;

		public function __construct(HashStorage $storage)
		{
			$this->storage = $storage;
		}

		/**
		 * @param int[] $alias
		 * @param int[] $better
		 */
		public function add($alias, $better)
		{
			$this->storage->save(implode(',', $alias), $better);
		}
	}
