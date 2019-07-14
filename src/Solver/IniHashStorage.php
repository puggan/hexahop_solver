<?php

	namespace Puggan\Solver;

	class IniHashStorage extends HashStorage
	{
		/** @var string[] $ini */
		private $ini;
		/** @var string $filename */
		private $filename;

		/**
		 * Load list from file
		 *
		 * @param $filename
		 */
		public function __construct($filename)
		{
			$this->filename = $filename;
			if(file_exists($filename))
			{
				$this->ini = parse_ini_file($filename, FALSE);
			}
			else
			{
				$this->ini = [];
			}
		}

		/**
		 * Save list back to file
		 */
		private function _save()
		{
			$f = fopen($this->filename, 'wb');
			foreach($this->ini as $key => $value)
			{
				fwrite($f, $key . '="' . str_replace('"', '\\"', $value) . '"' . PHP_EOL);
			}
			fclose($f);
		}

		/**
		 * @param string $hash primary key
		 *
		 * @return false|int[]
		 */
		public function get($hash)
		{
			if(!isset($this->ini[$hash]))
			{
				return FALSE;
			}
			return array_map('intval', explode(',', $this->ini[$hash]));
		}

		/**
		 * @param string $hash
		 * @param int[] $path
		 */
		public function save($hash, $path) : void
		{
			$this->ini[$hash] = implode(',', $path);
			$this->_save();
		}

		public function remove($hash) : void
		{
			unset($this->ini[$hash]);
			$this->_save();
		}
	}
