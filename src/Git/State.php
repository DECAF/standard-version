<?php

namespace Decaf\StandardVersion\Git;

class State
{
    /**
     * The last line from the result of the command.
     *
     * @var string
     */
    public string $last;

    /**
     * All lines of the output of the command.
     *
     * @var array
     */
    public array $output;

    /**
     * The return exit code for the command.
     *
     * @var int
     */
    public int $exitCode;
}
