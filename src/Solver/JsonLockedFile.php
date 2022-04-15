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

    public function __construct(string $filename)
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
            $f = fopen($this->filename, 'wb');
            if ($f === false) {
                throw new \RuntimeException('Failed to open file: ' . $this->filename);
            }
            $this->f = $f;
            return null;
        }
        $f = fopen($this->filename, 'rb+');
        if (!$f) {
            throw new \RuntimeException('Failed to open file: ' . $this->filename);
        }
        $this->f = $f;
        flock($this->f, LOCK_EX);
        $raw = stream_get_contents($this->f);
        if ($raw === false) {
            throw new \RuntimeException('Failed to read file content');
        }
        try {
            $json = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('json parse failed: ' . $e->getMessage() . ' on ' . $raw, previous: $e);
        }
        if (!is_object($json) || ! $json instanceof \stdClass) {
            throw new \RuntimeException('json not parsed into object');
        }
        return $json;
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
