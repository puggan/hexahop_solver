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
    private $f;

    public function __construct($filename)
    {
        $this->f = fopen($filename, 'rb');
    }

    public function __destruct()
    {
        fclose($this->f);
    }

    /**
     * @param int $position
     */
    public function goto(int $position): void
    {
        fseek($this->f, $position, SEEK_SET);
    }

    /**
     * @param int $offset
     */
    public function skip(int $offset): void
    {
        fseek($this->f, $offset, SEEK_CUR);
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function read(int $length): string
    {
        return fread($this->f, $length);
    }

    /**
     * @return int
     */
    public function uint8(): int
    {
        return unpack('C', fread($this->f, 1))[1];
    }

    /**
     * @return int
     */
    public function uint16(): int
    {
        return unpack('v', fread($this->f, 2))[1];
    }

    /**
     * @return int
     */
    public function uint32(): int
    {
        return unpack('V', fread($this->f, 4))[1];
    }
}
