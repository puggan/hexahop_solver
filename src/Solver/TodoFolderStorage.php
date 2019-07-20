<?php

	namespace Puggan\Solver;

	use Puggan\Solver\Entities\JSON\TodoFolderStorageJson;

	class TodoFolderStorage extends TodoStorage
	{
		/** @var string $folder */
		private $folder;
		/** @var HashStorage $reserved */
		private $reserved;
		/** @var JsonLockedFile $json */
		private $json;
		/** @var TodoFolderStorageJson */
		private $json_cache;
		private $json_rows_added_in_cache = 0;

		private $rows_per_file;

		/** @var int[] $removed_position */
		private $removed_position = [];

		/**
		 * TodoFileStorage constructor.
		 *
		 * @param string $folder
		 * @param int $rows_per_file
		 *
		 * @throws \Exception
		 */
		public function __construct($folder, $rows_per_file = 100000)
		{
			if(!is_dir($folder) && !mkdir($folder) && !is_dir($folder))
			{
				throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
			}
			if($folder[strlen($folder) - 1] !== '/')
			{
				$folder .= '/';
			}
			$this->folder = $folder;
			$this->rows_per_file = $rows_per_file;
			$this->reserved = new IniHashStorage($folder . 'reserved.ini');
			$this->json = new JsonLockedFile($folder . 'files.json');
			$this->json_cache = $this->json->read();
			if($this->json_cache === [])
			{
				$this->json_cache = (object) [
					'row_count' => 0,
					'files' => [
						$this->add_file(),
					],
				];
				$this->json->write($this->json_cache);
			}
			else
			{
				$this->json->close();
			}
		}

		/**
		 * @throws \Exception
		 */
		private function add_file() : string
		{
			$tries = 0;
			do
			{
				$new_filename = bin2hex(random_bytes(4)) . '.ini';
				$new_path = $this->folder . $new_filename;
			}
			while($tries++ < 100 && is_file($new_path));

			if(is_file($new_path))
			{
				throw new \RuntimeException('File already exists: ' . $new_filename);
			}

			file_put_contents($new_path, '');
			return $new_filename;
		}

		/**
		 * Adds a path to the todo
		 *
		 * @param int[] $path
		 *
		 * @throws \Exception
		 */
		public function add($path) : void
		{
			if($this->json_rows_added_in_cache >= 10000)
			{
				$this->json_cache = $this->json->read();
				$this->json_cache->row_count += $this->json_rows_added_in_cache;
				if($this->json_cache->row_count >= $this->rows_per_file)
				{
					$this->json_cache->files[] = $this->add_file();
					$this->json_cache->row_count = 0;
				}
				$this->json->write($this->json_cache);
				$this->json_rows_added_in_cache = 0;
			}
			$file = $this->json_cache->files[count($this->json_cache->files) - 1];

			$row = '0:' . implode(',', $path) . PHP_EOL;
			$f = fopen($this->folder . $file, 'ab');
			fwrite($f, $row);
			fclose($f);
			$this->json_cache->row_count++;
			$this->json_rows_added_in_cache++;
		}

		/**
		 * Reserve a todo, that's not already reserved
		 *
		 * @param int $pid
		 *
		 * @return false|int[]
		 */
		public function reserve($pid)
		{
			foreach($this->json->read()->files as $f_index => $file)
			{
				$filename = $this->folder . $file;
				if(!is_file($filename))
				{
					continue;
				}

				$f = fopen($filename, 'rb+');
				if(!is_resource($f))
				{
					continue;
				}

				$left = 0;
				if(!empty($this->removed_position[$file]))
				{
					fseek($f, $this->removed_position[$file]);
				}
				while(!feof($f))
				{
					$line = fgets($f, 1e6);
					if($line === '' || $line === PHP_EOL || strpos($line, '0:') !== 0)
					{
						if($left === 0)
						{
							$this->removed_position[$file] = ftell($f);
						}
						continue;
					}
					$left++;

					$path = trim(substr($line, 2));
					if($this->reserved->get($path))
					{
						continue;
					}
					fclose($f);

					if($path === '')
					{
						$this->json->close();
						return [];
					}
					$this->reserved->save($path, [$pid]);
					$this->json->close();
					return array_map('intval', explode(',', $path));
				}
				fclose($f);
			}
			$this->json->close();
			return FALSE;
		}

		/**
		 * @param int[] $path
		 */
		public function remove($path) : void
		{
			$path_string = implode(',', $path);
			$this->reserved->remove($path_string);

			/** @var TodoFolderStorageJson $file_info */
			$file_info = $this->json->read();
			$file_info_updated = FALSE;
			foreach($file_info->files as $f_index => $file)
			{
				$filename = $this->folder . $file;
				if(!is_file($filename))
				{
					continue;
				}

				$f = fopen($filename, 'rb+');
				if(!is_resource($f))
				{
					continue;
				}

				$left = 0;
				if(!empty($this->removed_position[$file]))
				{
					fseek($f, $this->removed_position[$file]);
				}
				while(!feof($f))
				{
					$position_before = ftell($f);
					$line = fgets($f, 1e6);
					if($line === '' || $line === PHP_EOL || strpos($line, '0:') !== 0)
					{
						if($left === 0)
						{
							$this->removed_position[$file] = ftell($f);
						}
						continue;
					}

					$row_path = trim(substr($line, 2));
					if($row_path === $path_string)
					{
						$position_after = ftell($f);
						fseek($f, $position_before);
						fwrite($f, 'X');
						fseek($f, $position_after);
						fclose($f);
						break 2;
					}

					$left++;
				}
				fclose($f);

				// Empty file? (only removed rows left
				if($left === 0)
				{
					// last file
					if(count($file_info->files) < 2)
					{
						// truncate
						file_put_contents($filename, '');
						$file_info->row_count = 0;
						$this->json_rows_added_in_cache = 0;
					}
					else
					{
						// remove file
						unset($this->removed_position[$file]);
						unlink($filename);
						unset($file_info->files[$f_index]);
					}
					$file_info_updated = TRUE;
				}
			}
			if($file_info_updated)
			{
				$file_info->files = array_values($file_info->files);
				$this->json->write($file_info);
			}
			else
			{
				$this->json->close();
			}
		}

		/**
		 * @param int[] $path
		 */
		public function remove_all($path) : void
		{
			$path_string = implode(',', $path);
			$this->reserved->remove($path_string);

			/** @var TodoFolderStorageJson $file_info */
			$file_info = $this->json->read();
			$file_info_updated = FALSE;
			$path_string2 = $path_string . ',';
			foreach($file_info->files as $f_index => $file)
			{
				$filename = $this->folder . $file;
				if(!is_file($filename))
				{
					continue;
				}

				$f = fopen($filename, 'rb+');
				if(!is_resource($f))
				{
					continue;
				}

				$left = 0;
				if(!empty($this->removed_position[$file]))
				{
					fseek($f, $this->removed_position[$file]);
				}
				while(!feof($f))
				{
					$position_before = ftell($f);
					$line = fgets($f, 1e6);
					if($line === '' || $line === PHP_EOL || strpos($line, '0:') !== 0)
					{
						if($left === 0)
						{
							$this->removed_position[$file] = ftell($f);
						}
						continue;
					}

					$row_path = trim(substr($line, 2));
					if($row_path === $path_string || strpos($row_path, $path_string2) === 0)
					{
						$position_after = ftell($f);
						fseek($f, $position_before);
						fwrite($f, 'X');
						fseek($f, $position_after);
					}
					else
					{
						$left++;
					}
				}
				fclose($f);

				// Empty file? (only removed rows left
				if($left === 0)
				{
					// last file
					if(count($file_info->files) < 2)
					{
						// truncate
						file_put_contents($filename, '');
						$file_info->row_count = 0;
						$this->json_rows_added_in_cache = 0;
					}
					else
					{
						// remove file
						unset($this->removed_position[$file]);
						unlink($filename);
						unset($file_info->files[$f_index]);
					}
					$file_info_updated = TRUE;
				}
			}
			if($file_info_updated)
			{
				$file_info->files = array_values($file_info->files);
				$this->json->write($file_info);
			}
			else
			{
				$this->json->close();
			}
		}
	}
