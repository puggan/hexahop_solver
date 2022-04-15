<?php

namespace Puggan\Solver;

use Puggan\Solver\Entities\JSON\TodoFolderStorageJson;

class TodoFolderStorage extends TodoStorage
{
    public const MAX_LINE_LENGTH = 1_000_000;
    private string $folder;
    private HashStorage|IniHashStorage $reserved;
    private JsonLockedFile $json;
    private \stdClass|TodoFolderStorageJson $json_cache;
    private int $json_rows_added_in_cache = 0;

    private int $rows_per_file;

    /** @var int[] $removed_position */
    private array $removed_position = [];

    public function __construct(string $folder, int $rows_per_file = 100_000)
    {
        if (!is_dir($folder) && !mkdir($folder) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
        }
        if ($folder[strlen($folder) - 1] !== '/') {
            $folder .= '/';
        }
        $this->folder = $folder;
        $this->rows_per_file = $rows_per_file;
        $this->reserved = new IniHashStorage($folder . 'reserved.ini');
        $this->json = new JsonLockedFile($folder . 'files.json');
        $file_info = $this->json->read();
        if ($file_info === null) {
            $this->json_cache = (object)[
                'row_count' => 0,
                'files' => [
                    $this->add_file(),
                ],
            ];
            $this->json->write($this->json_cache);
        } else {
            $this->json_cache = $file_info;
            $this->json->close();
        }
    }

    /**
     * @throws \Exception
     */
    private function add_file(): string
    {
        $tries = 0;
        do {
            $new_filename = bin2hex(random_bytes(4)) . '.ini';
            $new_path = $this->folder . $new_filename;
        } while ($tries++ < 100 && is_file($new_path));

        if (is_file($new_path)) {
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
    public function add(array $path): void
    {
        if ($this->json_rows_added_in_cache >= 10_000) {
            $file_info = $this->json->read();
            if (!$file_info) {
                throw new \RuntimeException('empty file');
            }
            $this->json_cache = $file_info;
            $this->json_cache->row_count += $this->json_rows_added_in_cache;
            if ($this->json_cache->row_count >= $this->rows_per_file) {
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
     * @return false|int[]
     */
    public function reserve(int $pid): array|bool
    {
        $file_info = $this->json->read();
        if (!$file_info) {
            throw new \RuntimeException('No file');
        }
        foreach ($file_info->files as $file) {
            $filename = $this->folder . $file;
            if (!is_file($filename)) {
                continue;
            }

            $f = fopen($filename, 'rb+');
            if (!is_resource($f)) {
                continue;
            }

            $left = 0;
            if (!empty($this->removed_position[$file])) {
                fseek($f, $this->removed_position[$file]);
            }
            while (!feof($f)) {
                $line = fgets($f, self::MAX_LINE_LENGTH);
                if ($line === '' || $line === PHP_EOL || !str_starts_with($line, '0:')) {
                    if ($left === 0) {
                        $this->removed_position[$file] = ftell($f);
                    }
                    continue;
                }
                $left++;

                $path = trim(substr($line, 2));
                if ($this->reserved->get($path)) {
                    continue;
                }
                fclose($f);

                if ($path === '') {
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
        return false;
    }

    /**
     * @param int[] $path
     */
    public function remove(array $path): void
    {
        $path_string = implode(',', $path);
        $this->reserved->remove($path_string);

        $file_info = $this->json->read();
        if (!$file_info) {
            throw new \RuntimeException('empty file');
        }
        $file_info_updated = false;
        foreach ($file_info->files as $f_index => $file) {
            $filename = $this->folder . $file;
            if (!is_file($filename)) {
                continue;
            }

            $f = fopen($filename, 'rb+');
            if (!is_resource($f)) {
                continue;
            }

            $left = 0;
            if (!empty($this->removed_position[$file])) {
                fseek($f, $this->removed_position[$file]);
            }
            while (!feof($f)) {
                $position_before = ftell($f);
                $line = fgets($f, self::MAX_LINE_LENGTH);
                if ($line === '' || $line === PHP_EOL || !str_starts_with($line, '0:')) {
                    if ($left === 0) {
                        $this->removed_position[$file] = ftell($f);
                    }
                    continue;
                }

                $row_path = trim(substr($line, 2));
                if ($row_path === $path_string) {
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
            if ($left === 0) {
                // last file
                if (count($file_info->files) < 2) {
                    // truncate
                    file_put_contents($filename, '');
                    $file_info->row_count = 0;
                    $this->json_rows_added_in_cache = 0;
                } else {
                    // remove file
                    unset($this->removed_position[$file]);
                    unlink($filename);
                    unset($file_info->files[$f_index]);
                }
                $file_info_updated = true;
            }
        }
        if ($file_info_updated) {
            $file_info->files = array_values($file_info->files);
            $this->json->write($file_info);
        } else {
            $this->json->close();
        }
    }

    /**
     * @param int[] $path
     * @throws \JsonException
     */
    public function remove_all(array $path): void
    {
        $path_string = implode(',', $path);
        $this->reserved->remove($path_string);

        $file_info = $this->json->read();
        if (!$file_info) {
            throw new \RuntimeException('empty file');
        }
        $file_info_updated = false;
        $path_string2 = $path_string . ',';
        foreach ($file_info->files as $f_index => $file) {
            $filename = $this->folder . $file;
            if (!is_file($filename)) {
                continue;
            }

            $f = fopen($filename, 'rb+');
            if (!is_resource($f)) {
                continue;
            }

            $left = 0;
            if (!empty($this->removed_position[$file])) {
                fseek($f, $this->removed_position[$file]);
            }
            while (!feof($f)) {
                $position_before = ftell($f);
                $line = fgets($f, self::MAX_LINE_LENGTH);
                if ($line === '' || $line === PHP_EOL || !str_starts_with($line, '0:')) {
                    if ($left === 0) {
                        $this->removed_position[$file] = ftell($f);
                    }
                    continue;
                }

                $row_path = trim(substr($line, 2));
                if ($row_path === $path_string || str_starts_with($row_path, $path_string2)) {
                    $position_after = ftell($f);
                    fseek($f, $position_before);
                    fwrite($f, 'X');
                    fseek($f, $position_after);
                } else {
                    $left++;
                }
            }
            fclose($f);

            // Empty file? (only removed rows left
            if ($left === 0) {
                // last file
                if (count($file_info->files) < 2) {
                    // truncate
                    file_put_contents($filename, '');
                    $file_info->row_count = 0;
                    $this->json_rows_added_in_cache = 0;
                } else {
                    // remove file
                    unset($this->removed_position[$file]);
                    unlink($filename);
                    unset($file_info->files[$f_index]);
                }
                $file_info_updated = true;
            }
        }
        if ($file_info_updated) {
            $file_info->files = array_values($file_info->files);
            $this->json->write($file_info);
        } else {
            $this->json->close();
        }
    }
}
