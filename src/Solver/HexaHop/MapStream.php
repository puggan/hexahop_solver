<?php

namespace Puggan\Solver\HexaHop;

/**
 * Class MapStream
 * @package Puggan\Solver\HexaHop
 *
 * HexaHop Map version 4:
 * 2 bytes: string version, "4\n"
 * 4 bytes: uint32 par,
 * 4 bytes: unit32 difficult
 * 1 byte: uint8 boundary min x
 * 1 byte: uint8 boundary max x
 * 1 byte: uint8 boundary min y
 * 1 byte: uint8 boundary max y
 * 4 bytes: uint32 player x position
 * 4 bytes: uint32 player y position
 * * bytes: uint8[][] tiles, foreach(range(x_min, x_max) as x) foreach(range(y_min, y_max) as y)
 */
class MapStream
{
    /** @var resource  */
    private $f;

    public function __construct(string $filename)
    {
        $f = fopen($filename, 'rb');
        if ($f === false) {
            throw new \RuntimeException('failed to open file');
        }
        $this->f = $f;
    }

    public function __destruct()
    {
        fclose($this->f);
    }

    public function goto(int $position): void
    {
        fseek($this->f, $position, SEEK_SET);
    }

    public function skip(int $offset): void
    {
        fseek($this->f, $offset, SEEK_CUR);
    }

    /**
     * @param int<0, max> $length
     */
    public function read(int $length): string
    {
        $content = fread($this->f, $length);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file content');
        }
        return $content;
    }

    public function uint8(): int
    {
        $content = fread($this->f, 1);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file uint32');
        }
        $data = unpack('C', $content);
        if ($data === false) {
            throw new \RuntimeException('Failed to read file uint8');
        }
        return $data[1];
    }

    public function uint16(): int
    {
        $content = fread($this->f, 2);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file uint32');
        }
        $data = unpack('v', $content);
        if ($data === false) {
            throw new \RuntimeException('Failed to read file uint16');
        }
        return $data[1];
    }

    public function uint32(): int
    {
        $content = fread($this->f, 4);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file uint32');
        }
        $data = unpack('V', $content);
        if ($data === false) {
            throw new \RuntimeException('Failed to read file uint32');
        }
        return $data[1];
    }
}
