<?php

	namespace Puggan\Solver;

	class JsonLockedFile
	{
		/** @var string $filename */
		private $filename;
		/** @var boolean $locked */
		private $locked = FALSE;
		/** @var resource $f */
		private $f;

		public function __construct($filename)
		{
			$this->filename = $filename;
		}

		public function read()
		{
			if($this->locked)
			{
				throw new \RuntimeException('double read without save() or close()');
			}
			$this->locked = TRUE;
			if(!is_file($this->filename))
			{
				$this->f = fopen($this->filename, 'wb');
				if(!$this->f)
				{
					throw new \RuntimeException('Failed to open file: ', $this->filename);
				}
				return [];
			}
			$this->f = fopen($this->filename, 'rb+');
			flock($this->f, LOCK_EX);
			if(!$this->f)
			{
				throw new \RuntimeException('Failed to open file: ' . $this->filename);
			}
			return json_decode(stream_get_contents($this->f), false);

		}

		public function write($data) : void
		{
			$json = json_encode($data);
			rewind($this->f);
			fwrite($this->f, $json);
			ftruncate($this->f, strlen($json));
			flock($this->f, LOCK_UN);
			$this->locked = FALSE;
			fclose($this->f);
		}

		public function close() : void
		{
			flock($this->f, LOCK_UN);
			$this->locked = FALSE;
			fclose($this->f);
		}
	}
