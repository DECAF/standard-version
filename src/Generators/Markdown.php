<?php

namespace Decaf\StandardVersion\Generators;

use Decaf\StandardVersion\Git\HistoryItem;
use Decaf\StandardVersion\Git\Repository;
use Decaf\StandardVersion\Git\Version;

class Markdown
{
    const BREAKING = 'BREAKING';
    const FEATURE = 'FEATURE';
    const FIXES = 'FIXES';
    const REFACTOR = 'REFACTOR';

    protected Repository $repository;

    /**
     * @param  Repository  $repository
     * @param  Version  $currentVersion
     * @param  Version  $nextVersion
     * @param  array  $history
     *
     * @return string
     */
    public function generate(Repository $repository, Version $currentVersion, Version $nextVersion, array $history): string
    {
        $this->repository = $repository;

        $markdown = '';

        $markdown .= '<a name="' . $nextVersion->getVersionString() . '"></a>' . PHP_EOL;
        if ($nextVersion->isMajor) {
            $markdown .= '#';
        } elseif ($nextVersion->isMinor) {
            $markdown .= '#';
        } else {
            $markdown .= '#';
        }
        $markdown .= ' [' . $nextVersion->getVersionString() . '](' . $repository->getCompareUrl($repository->tagPrefix . $currentVersion->getVersionString(), $repository->tagPrefix . $nextVersion->getVersionString()) . ') (' . date('Y-m-d') . ')' . PHP_EOL;

        $markdown .= PHP_EOL;

        $data                 = [];
        $data[self::BREAKING] = [];
        $data[self::FEATURE]  = [];
        $data[self::FIXES]    = [];
        $data[self::REFACTOR] = [];
        $hasItem              = false;
        foreach ($history as $k => $v) {
            switch (true) {
                case $v->isBreakingChange:
                    $data[self::BREAKING][] = $this->line($v);
                    $hasItem                = true;
                    break;
                case $v->type === 'feat':
                    $data[self::FEATURE][] = $this->line($v);
                    $hasItem               = true;
                    break;
                case $v->type === 'fix':
                    $data[self::FIXES][] = $this->line($v);
                    $hasItem             = true;
                    break;
                case $v->type === 'refactor':
                    $data[self::REFACTOR][] = $this->line($v);
                    $hasItem                = true;
                    break;
            }
        }

        if ($hasItem) {
            if (!empty($data[self::BREAKING])) {
                $markdown .= '### Breaking Changes' . PHP_EOL . PHP_EOL;
                foreach ($data[self::BREAKING] as $k => $v) {
                    $markdown .= $v . PHP_EOL;
                }
                $markdown .= PHP_EOL . PHP_EOL;
            }
            if (!empty($data[self::REFACTOR])) {
                $markdown .= '### Refactorings' . PHP_EOL . PHP_EOL;
                foreach ($data[self::REFACTOR] as $k => $v) {
                    $markdown .= $v . PHP_EOL;
                }
                $markdown .= PHP_EOL . PHP_EOL;
            }
            if (!empty($data[self::FIXES])) {
                $markdown .= '### Bug Fixes' . PHP_EOL . PHP_EOL;
                foreach ($data[self::FIXES] as $k => $v) {
                    $markdown .= $v . PHP_EOL;
                }
                $markdown .= PHP_EOL . PHP_EOL;
            }
            if (!empty($data[self::FEATURE])) {
                $markdown .= '### Features' . PHP_EOL . PHP_EOL;
                foreach ($data[self::FEATURE] as $k => $v) {
                    $markdown .= $v . PHP_EOL;
                }
                $markdown .= PHP_EOL . PHP_EOL;
            }
        } else {
            $markdown .= 'Bump version' . PHP_EOL;
        }

        return $markdown;
    }

    /**
     * @param  HistoryItem  $v
     *
     * @return string
     */
    private function line(HistoryItem $v): string
    {
        $markdown = '';
        $markdown .= '* ';

        if (!empty($v->scope)) {
            $markdown .= '**' . $v->scope . ':** ';
        }

        $line = $v->text;

        if (!empty($this->repository->getTicketUrl())) {
            $pattern = '/([A-Z]+-[0-9]+)/';
            $line    = preg_replace($pattern, '[$1](' . $this->repository->getTicketUrl() . '$1)', $line);
        }
        $line = $line . ' ([' . $v->ref . '](' . $this->repository->getCommitUrl($v->ref) . '))';

        $markdown .= $line;

        return $markdown;
    }
}
