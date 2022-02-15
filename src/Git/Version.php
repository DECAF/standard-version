<?php

namespace Decaf\StandardVersion\Git;

class Version
{
    public ?string $tagPrefix = null;
    public ?int $majorVersion = null;
    public ?int $minorVersion = null;
    public ?int $patchLevel = null;
    public ?string $preRelease = null;
    public ?string $build = null;

    public bool $isMajor = false;
    public bool $isMinor = false;
    public bool $isPatch = false;

    /**
     * @param $history
     *
     * @return $this
     */
    public function getNextVersion($history): self
    {
        foreach ($history as $k => $v) {
            if (is_string($v->description) && preg_match('/BREAKING CHANGE/', $v->description)) {
                $this->isMajor = true;
            } elseif (isset($v->type) && in_array($v->type, ['feat'])) {
                $this->isMinor = true;
            } else {
                $this->isPatch = true;
            }
        }

        $version = clone $this;

        if ($this->isMajor) {
            $version->majorVersion++;
            $version->minorVersion = 0;
            $version->patchLevel = 0;
        } elseif ($this->isMinor) {
            $version->minorVersion++;
            $version->patchLevel = 0;
        } else {
            $version->patchLevel++;
        }

        return $version;
    }

    /**
     * @return string
     */
    public function getVersionString(): string
    {
        return $this->majorVersion.'.'.$this->minorVersion.'.'.$this->patchLevel;
    }
}
