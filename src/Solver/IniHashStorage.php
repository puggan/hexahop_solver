<?php

namespace Puggan\Solver;

class IniHashStorage extends HashStorage
{
    /** @var string[] $ini */
    private array $ini;
    private string $filename;

    /**
     * Load list from file
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
        if (file_exists($filename)) {
            $this->ini = parse_ini_file($filename, false) ?: [];
        } else {
            $this->ini = [];
        }
    }

    /**
     * @param string $hash primary key
     *
     * @return ?int[]
     */
    public function get(string $hash): ?array
    {
        if (!isset($this->ini[$hash])) {
            return null;
        }
        return array_map('intval', explode(',', $this->ini[$hash]));
    }

    /**
     * @param int[] $path
     */
    public function save(string $hash, array $path): void
    {
        $this->ini[$hash] = implode(',', $path);
        $this->_save();
    }

    /**
     * Save list back to file
     */
    private function _save(): void
    {
        $f = fopen($this->filename, 'wb');
        if ($f === false) {
            throw new \RuntimeException('failed to open file');
        }
        foreach ($this->ini as $key => $value) {
            fwrite($f, $key . '="' . str_replace('"', '\\"', $value) . '"' . PHP_EOL);
        }
        fclose($f);
    }

    public function remove(string $hash): void
    {
        unset($this->ini[$hash]);
        $this->_save();
    }
}
