<?php

namespace Decaf\StandardVersion\Composer\Commands;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [new StandardVersionCommand()];
    }
}

