<?php

namespace Puggan\Solver;

class TodoFileStorage extends TodoStorage
{
    public const MAX_LINE_LENGTH = 1_000_000;
    private string $filename;
    private HashStorage|IniHashStorage $reserved;
    private int $remove_count;
    private int $removed_position = 0;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
        if ($filename[strlen($filename) - 4] === '.') {
            $reserved_filename = substr($filename, 0, -4) . '.reserved.' . substr($filename, -3);
        } else {
            $reserved_filename = $filename . '.reserved';
        }
        $this->reserved = new IniHashStorage($reserved_filename);
    }

    /**
     * Adds a path to the todo
     *
     * @param int[] $path
     */
    public function add(array $path): void
    {
        $row = '0:' . implode(',', $path) . PHP_EOL;
        $f = fopen($this->filename, 'ab');
        if ($f === false) {
            throw new \RuntimeException('failed to open file');
        }
        fwrite($f, $row);
        fclose($f);
    }

    /**
     * Reserve a todo, that's not already reserved
     *
     * @return false|int[]
     */
    public function reserve(int $pid): array|bool
    {
        if (!is_file($this->filename)) {
            return false;
        }
        $f = fopen($this->filename, 'rb');
        if (!is_resource($f)) {
            return false;
        }
        fseek($f, $this->removed_position);
        $first_found = false;
        while (!feof($f)) {
            $line = fgets($f, self::MAX_LINE_LENGTH);
            if ($line === false) {
                throw new \RuntimeException('failed to read line');
            }
            if (!str_starts_with($line, '0:')) {
                if (!$first_found) {
                    $position = ftell($f);
                    if ($position === false) {
                        throw new \RuntimeException('failed to find current position in file');
                    }
                    $this->removed_position = $position;
                }
                continue;
            }
            $first_found = true;
            $path = trim(substr($line, 2));
            if ($this->reserved->get($path)) {
                continue;
            }
            fclose($f);
            if ($path === '') {
                return [];
            }
            $this->reserved->save($path, [$pid]);
            return array_map('intval', explode(',', $path));
        }
        fclose($f);
        return false;
    }

    /**
     * @param int[] $path
     */
    public function remove(array $path): void
    {
        $path_string = implode(',', $path);
        $this->reserved->remove($path_string);
        if (!is_file($this->filename)) {
            return;
        }
        $f = fopen($this->filename, 'rb+');
        if (!is_resource($f)) {
            return;
        }
        fseek($f, $this->removed_position);
        $first_found = false;
        while (!feof($f)) {
            $position_before = ftell($f);
            if ($position_before === false) {
                throw new \RuntimeException('failed to find current position in file');
            }
            $line = fgets($f, self::MAX_LINE_LENGTH);
            if ($line === false) {
                throw new \RuntimeException('failed to read line');
            }
            if (!str_starts_with($line, '0:')) {
                if (!$first_found) {
                    $position = ftell($f);
                    if ($position === false) {
                        throw new \RuntimeException('failed to find current position in file');
                    }
                    $this->removed_position = $position;
                }
                continue;
            }
            $first_found = true;
            $row_path = trim(substr($line, 2));
            if ($row_path === $path_string) {
                fseek($f, $position_before);
                fwrite($f, 'X');
                fclose($f);
                $this->auto_clean(1);
                return;
            }
        }
        fclose($f);
    }

    public function auto_clean(int $removed = 0, bool $force = false): void
    {
        if (!is_file($this->filename)) {
            return;
        }
        $this->remove_count += $removed;
        if (!$force && $this->remove_count <= 10_000) {
            return;
        }

        $filename_copy = $this->filename . '.copy';
        rename($this->filename, $filename_copy);
        $f_copy = fopen($filename_copy, 'rb');
        if ($f_copy === false) {
            throw new \RuntimeException('failed to open file (read copy)');
        }
        $f_new = fopen($this->filename, 'wb');
        if ($f_new === false) {
            throw new \RuntimeException('failed to open file (write new)');
        }
        while (!feof($f_copy)) {
            $line = fgets($f_copy, self::MAX_LINE_LENGTH);
            if ($line === false) {
                throw new \RuntimeException('failed to read line');
            }
            if (!str_starts_with($line, '0:')) {
                continue;
            }
            fwrite($f_new, $line);
        }
        fclose($f_copy);
        fclose($f_new);
        unlink($filename_copy);
        $this->remove_count = 0;
        $this->removed_position = 0;
    }

    /**
     * @param int[] $path
     */
    public function remove_all(array $path): void
    {
        $path_string = implode(',', $path);
        $this->reserved->remove($path_string);
        if (!is_file($this->filename)) {
            return;
        }
        $f = fopen($this->filename, 'rb+');
        if (!is_resource($f)) {
            return;
        }
        while (!feof($f)) {
            $position_before = ftell($f);
            if ($position_before === false) {
                throw new \RuntimeException('failed to find current position in file');
            }
            $line = fgets($f, self::MAX_LINE_LENGTH);
            if ($line === false) {
                throw new \RuntimeException('failed to read line');
            }
            if (!str_starts_with($line, '0:')) {
                continue;
            }
            $row_path = trim(substr($line, 2));
            if ($row_path === $path_string || str_starts_with($row_path, $path_string . ',')) {
                $position_after = ftell($f);
                if ($position_after === false) {
                    throw new \RuntimeException('failed to find current position in file');
                }
                fseek($f, $position_before);
                fwrite($f, 'X');
                fseek($f, $position_after);
                $this->remove_count++;
            }
        }
        fclose($f);
        $this->auto_clean();
    }
}
