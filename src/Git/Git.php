<?php

namespace Decaf\StandardVersion\Git;

use Decaf\StandardVersion\Providers\Bitbucket;
use Decaf\StandardVersion\Providers\Github;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Git
{
    /**
     * @var InputInterface
     */
    protected InputInterface $input;

    /**
     * @var OutputInterface
     */
    protected OutputInterface $output;

    /**
     * @var Repository
     */
    protected Repository $repository;

    /**
     * @var string
     */
    protected string $tagPrefix;

    /**
     * @return string
     */
    public function getTagPrefix(): string
    {
        return $this->tagPrefix;
    }

    /**
     * @param  string  $tagPrefix
     */
    public function setTagPrefix(string $tagPrefix): void
    {
        $this->tagPrefix = $tagPrefix;
    }


    /**
     * @return InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }

    /**
     * @param  InputInterface  $input
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * @param  OutputInterface  $output
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * @return null
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param  Repository  $repository
     */
    public function setRepository(Repository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * @return Repository
     * @throws Exception
     */
    public function initRepository($extra): Repository
    {
        $this->repository = new Repository($extra);

        $remoteOrigin = $this->getRemoteOrigin();

        switch (true) {
            case (strpos($remoteOrigin, 'github.com')):
                $this->repository->provider = Github::class;
                break;
            case (strpos($remoteOrigin, 'bitbucket.org')):
                $this->repository->provider = Bitbucket::class;
                break;
        }

        $this->repository->init($remoteOrigin);

        return $this->repository;
    }

    /**
     * @throws Exception
     */
    public function checkCredentials(): void
    {
        $command = 'git config --global user.email';
        $state1  = $this->exec($command);

        $command = 'git config --global user.name';
        $state2  = $this->exec($command);

        if ($state1->exitCode !== 0 || $state2->exitCode !== 0) {
            throw new Exception('Please set your git credentials:' . PHP_EOL . '   git config --global user.email "foo@bar"' . PHP_EOL . '   git config --global user.name "Foo Bar"');
        }
    }

    /**
     * @throws Exception
     */
    public function checkWorkingCopy(): void
    {
        $command = 'git status';
        $state   = $this->exec($command);

        $modified = [];
        foreach ($state->output as $line) {
            if (preg_match('/modified:/', $line)) {
                $modified[] = trim($line);
            }
        }


        if (sizeof($modified) > 0) {
            throw new Exception('working copy contains modified files:' . PHP_EOL . implode(PHP_EOL, $modified));
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getRemoteOrigin(): string
    {
        $command = 'git config --get remote.origin.url';
        $state   = $this->exec($command, 'unable to get current branch');

        return $state->last;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getCurrentBranch(): string
    {
        $command = 'git branch';
        $state   = $this->exec($command, 'unable to get current branch');

        return substr($state->last, 2);
    }

    /**
     * @param  string  $prefix
     *
     * @return string
     * @throws Exception
     */
    public function getLatestTag(): string
    {

        $command = 'git describe --tags';
        $state   = $this->exec($command);

        switch (true) {
            case ($state->exitCode === 0):
                $tag = $state->last;
                break;
            case ($state->exitCode === 127):
                throw new Exception('unable to get tag information');
                break;
            default:
                $tag = $this->getTagPrefix() . '0.0.0';
        }

        return $tag;
    }

    /**
     * @param  Version  $version
     *
     * @return string
     * @throws Exception
     */
    public function getHistory(Version $version): State
    {
        $gitVersion = 'HEAD';

        if ($version->majorVersion > 0 || $version->minorVersion > 0 || $version->patchLevel > 0) {
            $gitVersion = $this->getTagPrefix() . $version->majorVersion . '.' . $version->minorVersion . '.' . $version->patchLevel . '..HEAD';
        }

        $command = 'git log ' . $gitVersion . ' --oneline';
        $state   = $this->exec($command, 'unable to get history');

        return $state;
    }

    /**
     * @param  string  $file
     *
     * @return string
     * @throws Exception
     */
    public function add(string $file): string
    {
        $command = 'git add ' . $file;
        $state   = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to add file: ' . $file);
        }

        return $state->last;
    }

    /**
     * @param  string  $message
     *
     * @return string
     * @throws Exception
     */
    public function commit(string $message): string
    {
        $command = 'git commit -m "' . $message . '"';
        $state   = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to commit: ' . $message);
        }

        return $state->last;
    }

    /**
     * @param  string  $tag
     *
     * @return string
     * @throws Exception
     */
    public function tag(string $tag): string
    {
        $command = 'git tag -a ' . $tag . ' -m "bump version ' . $tag . '"';
        $state   = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to create tag: ' . $tag);
        }

        return $state->last;
    }

    /**
     * @param  string  $tag
     *
     * @return string
     * @throws Exception
     */
    public function pushTag(string $tag): string
    {
        $command = 'git push origin ' . $tag;
        $state   = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to push tag: ' . $tag);
        }

        return $state->last;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function push(): string
    {
        $command = 'git push origin';
        $state   = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to push to origin');
        }

        return $state->last;
    }

    /**
     * @param $command
     * @param  null  $errorMsg
     *
     * @return State
     * @throws Exception
     */
    protected function exec($command, $errorMsg = null)
    {
        $output = [];

        $last = @exec($command, $output, $exitCode);

        $state           = new State();
        $state->output   = $output;
        $state->last     = $last;
        $state->exitCode = $exitCode;

        if ($errorMsg && empty($state->output)) {
            throw new Exception($errorMsg . ' (' . $command . ')');
        }

        return $state;
    }

    /**
     * @param  string  $tag
     *
     * @return Version
     * @throws Exception
     */
    public function parseTag(string $tag): Version
    {
        $pattern = '/^';
        $pattern .= '(?<tagPrefix>v?)';
        $pattern .= '(?<majorVersion>\d+)';
        $pattern .= '\.';
        $pattern .= '(?<minorVersion>\d+)';
        $pattern .= '\.';
        $pattern .= '(?<patchLevel>\d+)';
        $pattern .= '(?:-(?<preRelease>[0-9A-Za-z.]+))?';
        $pattern .= '(?:-(?<build>[0-9A-Za-z-.]+)?)?';
        $pattern .= '$/';

        if (!preg_match($pattern, $tag, $matches)) {
            throw new Exception('Unable to parse tag: ' . $tag);
        }

        $version = new Version();

        $version->tagPrefix    = $matches['tagPrefix'] ?? null;
        $version->majorVersion = (int) $matches['majorVersion'];
        $version->minorVersion = (int) $matches['minorVersion'];
        $version->patchLevel   = (int) $matches['patchLevel'];
        $version->preRelease   = $matches['preRelease'] ?? null;
        $version->build        = $matches['build'] ?? null;

        $this->tagPrefix = $version->tagPrefix;

        return $version;
    }

    /**
     * @param  array  $history
     *
     * @return array
     * @throws Exception
     */
    public function parseHistory(array $history)
    {
        //        $history   = [];
        //        $history[] = '123a789 feat(domain): test2 TEST-456'.PHP_EOL.'This is the description'.PHP_EOL.'BREAKING CHANGE';
        //        $history[] = '9876543 feat(domain): test1 TEST-123';
        //        $history[] = '1234567 feat: test2, closes #TEST-3';

        $historyItems = [];
        foreach ($history as $k => $v) {
            $pattern = '/^(?<ref>[^ ]+) (?<type>[a-z]+)(?<scope>\([a-z0-9-_.]+\))?:(?<text>.+)/i';

            if (preg_match($pattern, $v, $match)) {
                $historyItem        = new HistoryItem();
                $historyItem->ref   = $match['ref'];
                $historyItem->type  = $match['type'];
                $historyItem->scope = substr(substr($match['scope'], 0, strlen($match['scope']) - 1), 1);
                $historyItem->text  = $match['text'];

                $command = 'git show -s --format=%B ' . $historyItem->ref;
                $state   = $this->exec($command, 'unable to get history entry of ' . $historyItem->ref);

                if ($state->output && sizeof($state->output) > 2) {
                    $entry = $state->output;
                    array_shift($entry);

                    $description              = trim(implode(PHP_EOL, $entry));
                    $historyItem->description = $description;

                    if (preg_match('/BREAKING CHANGE/', $historyItem->description)) {
                        $historyItem->isBreakingChange = true;
                    }
                }

                $historyItems[] = $historyItem;
            }
        }

        return $historyItems;
    }
}
