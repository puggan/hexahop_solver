<?php

	namespace Puggan\Solver;

	class TodoFileStorage extends TodoStorage
	{
		/** @var string $filename */
		private $filename;
		/** @var HashStorage $reserved */
		private $reserved;
		/** @var int $remove_count */
		private $remove_count;

		/**
		 * TodoFileStorage constructor.
		 *
		 * @param string $filename
		 */
		public function __construct($filename)
		{
			$this->filename = $filename;
			if($filename[strlen($filename) - 4] === '.')
			{
				$reserved_filename = substr($filename, 0, -4) . '.reserved.' . substr($filename, -3);
			}
			else
			{
				$reserved_filename = $filename . '.reserved';
			}
			$this->reserved = new IniHashStorage($reserved_filename);
		}

		/**
		 * Adds a path to the todo
		 *
		 * @param int[] $path
		 */
		public function add($path) : void
		{
			$row = '0:' . implode(',', $path) . PHP_EOL;
			$f = fopen($this->filename, 'ab');
			fwrite($f, $row);
			fclose($f);
		}

		/**
		 * Reserve a todo, thats not already reserved
		 *
		 * @param int $pid
		 *
		 * @return false|int[]
		 */
		public function reserve($pid)
		{
			$f = fopen($this->filename, 'rb');
			while(!feof($f))
			{
				$line = fgetss($f, 1e6);
				if(strpos($line, '0:') !== 0)
				{
					continue;
				}
				$path = trim(substr($line, 2));
				if($this->reserved->get($path))
				{
					continue;
				}
				$this->reserved->save($path, [$pid]);
				return array_map('intval', explode(',', $path));
			}
			fclose($f);
			return FALSE;
		}

		/**
		 * @param int[] $path
		 */
		public function remove($path) : void
		{
			$path_string = implode(',', $path);
			$this->reserved->remove($path_string);
			$f = fopen($this->filename, 'rb+');
			while(!feof($f))
			{
				$position_before = ftell($f);
				$line = fgetss($f, 1e6);
				if(strpos($line, '0:') !== 0)
				{
					continue;
				}
				$row_path = trim(substr($line, 2));
				if($row_path === $path_string)
				{
					fseek($f, $position_before);
					fwrite($f, 'X');
					fclose($f);
					$this->auto_clean();
					return;
				}
			}
			fclose($f);
		}

		/**
		 * @param int[] $path
		 */
		public function remove_all($path) : void
		{
			$path_string = implode(',', $path);
			$this->reserved->remove($path_string);
			$f = fopen($this->filename, 'rb+');
			while(!feof($f))
			{
				$position_before = ftell($f);
				$line = fgetss($f, 1e6);
				if(strpos($line, '0:') !== 0)
				{
					continue;
				}
				$row_path = trim(substr($line, 2));
				if($row_path === $path_string || strpos($row_path, $path_string . ',') === 0)
				{
					$position_after = ftell($f);
					fseek($f, $position_before);
					fwrite($f, 'X');
					fseek($f, $position_after);
					$this->remove_count++;
				}
			}
			fclose($f);
			$this->auto_clean();
		}

		public function auto_clean($removed = 0, $force = FALSE)
		{
			$this->remove_count += $removed;
			if(!$force && $this->remove_count <= 100)
			{
				return;
			}

			$filenmae_copy = $this->filename . '.copy';
			rename($this->filename, $filenmae_copy);
			$f_copy = fopen($filenmae_copy, 'rb');
			$f_new = fopen($this->filename, 'wb');
			while(!feof($f_copy))
			{
				$line = fgetss($f_copy, 1e6);
				if(strpos($line, '0:') !== 0)
				{
					continue;
				}
				fwrite($f_new, $line);
			}
			fclose($f_copy);
			fclose($f_new);
			unlink($filenmae_copy);
			$this->remove_count = 0;
		}
	}
