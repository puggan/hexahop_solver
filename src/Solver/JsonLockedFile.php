<?php

namespace Puggan\Solver;

class JsonLockedFile
{
    /** @var string $filename */
    private string $filename;
    /** @var bool $locked */
    private bool $locked = false;
    /** @var resource $f */
    private $f;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function read(): ?\stdClass
    {
        if ($this->locked) {
            throw new \RuntimeException('double read without save() or close()');
        }
        $this->locked = true;
        if (!is_file($this->filename)) {
            $this->f = fopen($this->filename, 'wb');
            if (!$this->f) {
                throw new \RuntimeException('Failed to open file: ', $this->filename);
            }
            return null;
        }
        $this->f = fopen($this->filename, 'rb+');
        flock($this->f, LOCK_EX);
        if (!$this->f) {
            throw new \RuntimeException('Failed to open file: ' . $this->filename);
        }
        $raw = stream_get_contents($this->f);
        try {
            return json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('json parse failed: ' . $e->getMessage() . ' on ' . $raw, previous: $e);
        }
    }

    public function write(\stdClass $data): void
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('json failed: ' . $e->getMessage(), previous: $e);
        }
        rewind($this->f);
        fwrite($this->f, $json);
        ftruncate($this->f, strlen($json));
        flock($this->f, LOCK_UN);
        $this->locked = false;
        fclose($this->f);
    }

    public function close(): void
    {
        flock($this->f, LOCK_UN);
        $this->locked = false;
        fclose($this->f);
    }
}
