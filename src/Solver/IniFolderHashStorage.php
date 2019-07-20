<?php

	namespace Puggan\Solver;

	class IniFolderHashStorage extends HashStorage
	{
		/** @var string $folder */
		protected $folder;
		/** @var int $prefix_length */
		protected $prefix_length;

		public function __construct($folder, $prefix_length = 3)
		{
			if(!is_dir($folder) && !mkdir($folder) && !is_dir($folder))
			{
				throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
			}
			if(substr($folder, -1, 1) !== '/')
			{
				$folder .= '/';
			}
			$this->folder = $folder;
			$this->prefix_length = $prefix_length;
		}

		private function filename($hash)
		{
			return $this->folder . substr($hash, 0, $this->prefix_length) . '.ini';
		}

		/**
		 * @param string $hash primary key
		 *
		 * @return false|int[]
		 */
		function get($hash)
		{
			$filename = $this->filename($hash);
			if(!is_file($filename))
			{
				return FALSE;
			}
			$hash_suffix = substr($hash, $this->prefix_length);
			$f = fopen($filename, 'rb');
			while(!feof($f))
			{
				$line = fgets($f, 1e6);
				if(strpos($line, $hash_suffix) === 0)
				{
					fclose($f);
					$path_string = substr($line, 1 + strlen($hash_suffix));
					if($path_string === '')
					{
						return [];
					}
					return array_map('intval', explode(',', $path_string));
				}

			}
			fclose($f);
			return FALSE;
		}

		/**
		 * @param string $hash
		 * @param int[] $path
		 */
		public function save($hash, $path) : void
		{
			$this->replace($hash, $path);
		}

		/**
		 * @param string $hash
		 */
		public function remove($hash) : void
		{
			$this->replace($hash, false);
		}

		/**
		 * @param string $hash
		 * @param false|int[] $path
		 */
		public function replace($hash, $path = false) : void
		{
			$filename = $this->filename($hash);
			$hash_suffix = substr($hash, $this->prefix_length);
			$new_path = $path === false ? false : $hash_suffix . '=' . implode(',', $path) . PHP_EOL;
			$new_length = $new_path === false ? 0 : strlen($new_path);
			if(!is_file($filename))
			{
				if($new_path !== FALSE)
				{
					file_put_contents($filename, $new_path);
				}
				return;
			}
			$f = fopen($filename, 'rb+');
			while(!feof($f))
			{
				$before = ftell($f);
				$line = fgets($f, 1e6);
				if($new_path !== FALSE && !trim($line))
				{
					do
					{
						$empty = ftell($f);
						$line = fgets($f, 1e6);
					}
					while(!trim($line) && !feof($f));
					$length = $empty - $before;
					if($length >= $new_length)
					{
						$after = ftell($f);
						fseek($f, $before);
						fwrite($f, $new_path);
						$new_path = false;
						$length -= $new_length;
						$new_length = 0;
						if($length)
						{
							fwrite($f, str_repeat(' ', $length - 1) . "\n");
						}
						fseek($f, $after);
					}
				}
				if(strpos($line, $hash_suffix) === 0)
				{
					$length = ftell($f) - $before;
					while(!trim(fgets($f, 1e6))) {
						$length = ftell($f) - $before;
					}
					fseek($f, $before);
					if($new_path === false || $new_length > $length) {
						fwrite($f, str_repeat(' ', $length - 1) . "\n");
					} else {
						fwrite($f, $new_path);
						$new_path = false;
						$length -= $new_length;
						$new_length = 0;
						if($length)
						{
							fwrite($f, str_repeat(' ', $length - 1) . "\n");
						}
					}
				}
			}
			if($new_path !== false) {
				fwrite($f, $new_path);
			}
			fclose($f);
		}
	}
