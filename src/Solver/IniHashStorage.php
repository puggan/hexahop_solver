<?php

	namespace Puggan\Solver;

	class IniHashStorage extends HashStorage
	{
		/** @var string[] $ini */
		private array|false $ini;
		/** @var string $filename */
		private string $filename;

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
		private function _save() : void
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
		public function get(string $hash): array|bool
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
		public function save(string $hash, array $path) : void
		{
			$this->ini[$hash] = implode(',', $path);
			$this->_save();
		}

		public function remove(string $hash) : void
		{
			unset($this->ini[$hash]);
			$this->_save();
		}
	}
