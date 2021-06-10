<?php

	namespace Puggan\Solver;

	class JsonLockedFile
	{
		/** @var string $filename */
		private string $filename;
		/** @var boolean $locked */
		private bool $locked = FALSE;
		/** @var resource $f */
		private $f;

		public function __construct($filename)
		{
			$this->filename = $filename;
		}

        /**
         * @return \stdClass|null
         * @throws \JsonException
         */
		public function read(): ?\stdClass
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
				return null;
			}
			$this->f = fopen($this->filename, 'rb+');
			flock($this->f, LOCK_EX);
			if(!$this->f)
			{
				throw new \RuntimeException('Failed to open file: ' . $this->filename);
			}
			return json_decode(stream_get_contents($this->f), false, 512, JSON_THROW_ON_ERROR);

		}

        /**
         * @param \stdClass $data
         * @throws \JsonException
         */
		public function write(\stdClass $data) : void
		{
			$json = json_encode($data, JSON_THROW_ON_ERROR);
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
