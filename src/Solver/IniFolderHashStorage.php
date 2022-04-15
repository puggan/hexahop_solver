<?php

namespace Puggan\Solver;

class IniFolderHashStorage extends HashStorage
{
    public const MAX_LINE_LENGTH = 1_000_000;
    /** @var string $folder */
    protected mixed $folder;
    /** @var int $prefix_length */
    protected mixed $prefix_length;

    public function __construct(string $folder, int $prefix_length = 3)
    {
        if (!is_dir($folder) && !mkdir($folder) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
        }
        if (substr($folder, -1, 1) !== '/') {
            $folder .= '/';
        }
        $this->folder = $folder;
        $this->prefix_length = $prefix_length;
    }

    /**
     * @param string $hash primary key
     *
     * @return false|int[]
     */
    public function get(string $hash): array|bool
    {
        $filename = $this->filename($hash);
        if (!is_file($filename)) {
            return false;
        }
        $hash_suffix = substr($hash, $this->prefix_length);
        $f = fopen($filename, 'rb');
        while (!feof($f)) {
            $line = fgets($f, self::MAX_LINE_LENGTH);
            if (str_starts_with($line, $hash_suffix)) {
                fclose($f);
                $path_string = substr($line, 1 + strlen($hash_suffix));
                if ($path_string === '') {
                    return [];
                }
                return array_map('intval', explode(',', $path_string));
            }
        }
        fclose($f);
        return false;
    }

    private function filename(string $hash): string
    {
        return $this->folder . substr($hash, 0, $this->prefix_length) . '.ini';
    }

    /**
     * @param int[] $path
     */
    public function save(string $hash, array $path): void
    {
        $this->replace($hash, $path);
    }

    /**
     * @param bool|int[] $path
     */
    public function replace(string $hash, array|bool $path = false): void
    {
        $filename = $this->filename($hash);
        $hash_suffix = substr($hash, $this->prefix_length);
        $new_path = $path === false ? false : $hash_suffix . '=' . implode(',', $path) . PHP_EOL;
        $new_length = $new_path === false ? 0 : strlen($new_path);
        if (!is_file($filename)) {
            if ($new_path !== false) {
                file_put_contents($filename, $new_path);
            }
            return;
        }
        $f = fopen($filename, 'rb+');
        while (!feof($f)) {
            $before = ftell($f);
            $line = fgets($f, self::MAX_LINE_LENGTH);
            if ($new_path !== false && !trim($line)) {
                do {
                    $empty = ftell($f);
                    $line = fgets($f, self::MAX_LINE_LENGTH);
                } while (!trim($line) && !feof($f));
                $length = $empty - $before;
                if ($length >= $new_length) {
                    $after = ftell($f);
                    fseek($f, $before);
                    fwrite($f, $new_path);
                    $new_path = false;
                    $length -= $new_length;
                    $new_length = 0;
                    if ($length) {
                        fwrite($f, str_repeat(' ', $length - 1) . "\n");
                    }
                    fseek($f, $after);
                }
            }
            if (str_starts_with($line, $hash_suffix)) {
                /** @var int $length */
                $length = ftell($f) - $before;
                while (!trim(fgets($f, self::MAX_LINE_LENGTH))) {
                    $length = ftell($f) - $before;
                }
                fseek($f, $before);
                if ($new_path === false || $new_length > $length) {
                    fwrite($f, str_repeat(' ', $length - 1) . "\n");
                } else {
                    fwrite($f, $new_path);
                    $new_path = false;
                    $length -= $new_length;
                    $new_length = 0;
                    if ($length) {
                        fwrite($f, str_repeat(' ', $length - 1) . "\n");
                    }
                }
            }
        }
        if ($new_path !== false) {
            fwrite($f, $new_path);
        }
        fclose($f);
    }

    public function remove(string $hash): void
    {
        $this->replace($hash);
    }
}
