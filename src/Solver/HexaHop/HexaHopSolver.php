<?php

namespace Puggan\Solver\HexaHop;

//use Puggan\Solver\AliasHashStorage;
use Puggan\Solver\IniFolderHashStorage;
use Puggan\Solver\IniHashStorage;
use Puggan\Solver\Solver;
use Puggan\Solver\TodoFolderStorage;

/**
 * Class HexaHopSolver
 * @package Puggan\Solver\HexaHop
 * @property-read HexaHopMap $startState
 */
class HexaHopSolver extends Solver
{
    public function __construct(int $level_number)
    {
        $path = self::data_dir($level_number);
        $new_map = !is_dir($path . '/todo');
        $startState = new HexaHopMap($level_number);
        $solved = new IniHashStorage($path . 'solved.ini');
        //$alias = new AliasHashStorage(new IniHashStorage($path . 'alias.ini'));
        //$hashes = new IniHashStorage($path . 'hashes.ini');
        $hashes = new IniFolderHashStorage($path . 'hashes/');
        $todos = new TodoFolderStorage($path . 'todo/');
        if ($new_map) {
            $todos->add([]);
        }
        parent::__construct($startState, $solved, /*$alias,*/ $hashes, $todos);
    }

    public static function data_dir(int $level_number): string
    {
        $path = dirname(__DIR__, 3) . '/data/' . $level_number . '/';
        if (!is_dir($path) && !mkdir($path) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
        return $path;
    }

    /**
     * @throws \JsonException
     */
    public function map_info(int $json_option): bool|string
    {
        return $this->startState->map_info($json_option);
    }
}
