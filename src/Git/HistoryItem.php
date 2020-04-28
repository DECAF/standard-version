<?php

namespace Decaf\StandardVersion\Git;

class HistoryItem
{
    public string $ref;
    public ?string $type;
    public ?string $scope;
    public ?string $text;
    public ?string $description = null;
    public bool $isBreakingChange = false;
}
