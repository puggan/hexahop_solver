<?php

	namespace Puggan\Solver;

	abstract class HashStorage implements \ArrayAccess
	{
		/**
		 * Fetch the hash
		 *
		 * @param string $hash primary key
		 *
		 * @return false|int[]
		 */
		abstract public function get($hash);

		/**
		 * Add/Replace a hash
		 *
		 * @param string $hash
		 * @param int[] $path
		 */
		abstract public function save($hash, $path) : void;

		/**
		 * Remove an hash
		 *
		 * @param string $hash
		 */
		abstract public function remove($hash) : void;

		//<editor-fold desc="ArrayAccess">
		public function offsetExists($offset) : bool
		{
			return $this->get($offset) !== FALSE;
		}

		public function offsetGet($offset)
		{
			return $this->get($offset);
		}

		public function offsetSet($offset, $value) : void
		{
			$this->save($offset, $value);
		}

		public function offsetUnset($offset) : void
		{
			$this->remove($offset);
		}
		//</editor-fold>
	}
