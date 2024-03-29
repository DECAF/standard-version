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
    protected ?string $reporitoryFolder;

    /**
     * @var string
     */
    protected ?string $tagPrefix;

    /**
     * @return string
     */
    public function getReporitoryFolder(): string
    {
        return $this->reporitoryFolder;
    }

    /**
     * @param string $reporitoryFolder
     */
    public function setReporitoryFolder(string $reporitoryFolder): void
    {
        $this->reporitoryFolder = $reporitoryFolder;
    }

    /**
     * @return string|null
     */
    public function getTagPrefix(): ?string
    {
        return $this->tagPrefix;
    }

    /**
     * @param string|null $tagPrefix
     */
    public function setTagPrefix(?string $tagPrefix): void
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
     * @param InputInterface $input
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
     * @param OutputInterface $output
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
     * @param Repository $repository
     */
    public function setRepository(Repository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * @throws Exception
     *
     * @return Repository
     */
    public function initRepository($extra): Repository
    {
        $this->repository = new Repository($extra);

        $remoteUrl = $this->getRemoteUrl();

        switch (true) {
            case strpos($remoteUrl, 'github.com'):
                $this->repository->provider = Github::class;
                break;
            case strpos($remoteUrl, 'bitbucket.org'):
                $this->repository->provider = Bitbucket::class;
                break;
        }

        $this->repository->init($remoteUrl);

        return $this->repository;
    }

    /**
     * @throws Exception
     */
    public function checkCredentials(): void
    {
        $command = 'git config --global user.email';
        $state1 = $this->exec($command);

        $command = 'git config --global user.name';
        $state2 = $this->exec($command);

        if ($state1->exitCode !== 0 || $state2->exitCode !== 0) {
            throw new Exception('Please set your git credentials:'.PHP_EOL.'   git config --global user.email "foo@bar"'.PHP_EOL.'   git config --global user.name "Foo Bar"');
        }
    }

    /**
     * @throws Exception
     */
    public function changeCurrentFolder($folder): void
    {
        if (!file_exists($folder)) {
            throw new Exception('Folder: '.$folder.' not found');
        }
        if (!chdir($folder)) {
            throw new Exception('Folder: '.$folder.' not accessible');
        }
    }

    /**
     * @throws Exception
     */
    public function checkWorkingCopy(): void
    {
        $command = 'git status';
        $state = $this->exec($command);

        $modified = [];
        foreach ($state->output as $line) {
            if (preg_match('/modified:/', $line)) {
                $modified[] = trim($line);
            }
        }

        if (count($modified) > 0) {
            throw new Exception('working copy contains modified files:'.PHP_EOL.implode(PHP_EOL, $modified));
        }
    }

    /**
     * @param string|null $branch
     *
     * @throws Exception
     *
     * @return string
     */
    public function getRemote(?string $branch = null): string
    {
        if (!$branch) {
            $branch = $this->getCurrentBranch();
        }
        $command = 'git config --get branch.'.$branch.'.remote';
        $state = $this->exec($command, 'unable to get config: branch.'.$branch.'.remote');

        return $state->last;
    }

    /**
     * @param string $branch
     *
     * @throws Exception
     *
     * @return string
     */
    public function getRemoteUrl($branch = 'main'): string
    {
        $remote = $this->getRemote($branch);

        $command = 'git config --get remote.'.$remote.'.url';
        $state = $this->exec($command, 'unable to get config: remote.'.$remote.'.url');

        return $state->last;
    }

    /**
     * @throws Exception
     *
     * @return string
     */
    public function getCurrentBranch(): string
    {
        $command = 'git branch';
        $state = $this->exec($command, 'unable to get current branch');

        return substr($state->last, 2);
    }

    /**
     * @param string $prefix
     *
     * @throws Exception
     *
     * @return string
     */
    public function getLatestTag(): string
    {
        $command = 'git describe --tags';
        $state = $this->exec($command);

        switch (true) {
            case $state->exitCode === 0:
                $tag = $state->last;
                break;
            case $state->exitCode === 127:
                throw new Exception('unable to get tag information');
                break;
            default:
                $tag = $this->getTagPrefix().'0.0.0';
        }

        return $tag;
    }

    /**
     * @param Version $version
     *
     * @throws Exception
     *
     * @return string
     */
    public function getHistory(Version $version): State
    {
        $gitVersion = 'HEAD';

        if ($version->majorVersion > 0 || $version->minorVersion > 0 || $version->patchLevel > 0) {
            $gitVersion = $this->getTagPrefix().$version->majorVersion.'.'.$version->minorVersion.'.'.$version->patchLevel.'..HEAD';
        }

        $command = 'git log '.$gitVersion.' --oneline';
        $state = $this->exec($command, 'unable to get history');

        return $state;
    }

    /**
     * @param string $file
     *
     * @throws Exception
     *
     * @return string
     */
    public function add(string $file): string
    {
        $command = 'git add '.$file;
        $state = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to add file: '.$file);
        }

        return $state->last;
    }

    /**
     * @param string $message
     *
     * @throws Exception
     *
     * @return string
     */
    public function commit(string $message): string
    {
        $command = 'git commit -m "'.$message.'"';
        $state = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to commit: '.$message);
        }

        return $state->last;
    }

    /**
     * @param string $tag
     *
     * @throws Exception
     *
     * @return string
     */
    public function tag(string $tag): string
    {
        $command = 'git tag -a '.$tag.' -m "bump version '.$tag.'"';
        $state = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to create tag: '.$tag);
        }

        return $state->last;
    }

    /**
     * @param string      $tag
     * @param string|null $branch
     *
     * @throws Exception
     *
     * @return string
     */
    public function pushTag(string $tag, ?string $branch = null): string
    {
        $remote = $this->getRemote($branch);

        $command = 'git push '.$remote.' '.$tag;
        $state = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to push tag: '.$tag);
        }

        return $state->last;
    }

    /**
     * @param string|null $branch
     *
     * @throws Exception
     *
     * @return string
     */
    public function push(?string $branch = null): string
    {
        $remote = $this->getRemote($branch);

        $command = 'git push '.$remote;
        $state = $this->exec($command);

        if ($state->exitCode !== 0) {
            throw new Exception('unable to push to '.$remote);
        }

        return $state->last;
    }

    /**
     * @param $command
     * @param null $errorMsg
     *
     * @throws Exception
     *
     * @return State
     */
    protected function exec($command, $errorMsg = null)
    {
        $output = [];

        $last = @exec($command, $output, $exitCode);

        $state = new State();
        $state->output = $output;
        $state->last = $last;
        $state->exitCode = $exitCode;

        if ($errorMsg && empty($state->output)) {
            throw new Exception($errorMsg.' ('.$command.')');
        }

        return $state;
    }

    /**
     * @param string $tag
     *
     * @throws Exception
     *
     * @return Version
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
            throw new Exception('Unable to parse tag: '.$tag);
        }

        $version = new Version();

        $version->tagPrefix = $matches['tagPrefix'] ?? null;
        $version->majorVersion = (int) $matches['majorVersion'];
        $version->minorVersion = (int) $matches['minorVersion'];
        $version->patchLevel = (int) $matches['patchLevel'];
        $version->preRelease = $matches['preRelease'] ?? null;
        $version->build = $matches['build'] ?? null;

        $this->tagPrefix = $version->tagPrefix;

        return $version;
    }

    /**
     * @param array $history
     *
     * @throws Exception
     *
     * @return array
     */
    public function parseHistory(array $history)
    {
        //        $history   = [];
        //        $history[] = '123a789 feat(domain): test2 TEST-456'.PHP_EOL.'This is the description'.PHP_EOL.'BREAKING CHANGE';
        //        $history[] = '9876543 feat(domain): test1 TEST-123';
        //        $history[] = '1234567 feat: test2, closes #TEST-3';

        $historyItems = [];
        foreach ($history as $k => $v) {
            $ref = null;
            $pattern = '/^(?<ref>[^ ]+)/i';
            if (preg_match($pattern, $v, $match)) {
                $ref = $match['ref'];
            }

            if ($ref) {
                $description = null;
                $isBreakingChange = false;

                // check for breaking change
                $command = 'git show -s --format=%B '.$ref;
                $state = $this->exec($command, 'unable to get history entry of '.$ref);

                if ($state->output && count($state->output) > 2) {
                    $entry = $state->output;
                    array_shift($entry);

                    $description = trim(implode(PHP_EOL, $entry));

                    if (preg_match('/BREAKING CHANGE/', $description)) {
                        $isBreakingChange = true;
                    }
                }

                $pattern = '/^(?<ref>[^ ]+) (?<type_scope>(?<type>[a-z]+)(?<scope>\([a-z0-9-_.]+\))?:)?(?<text>.+)/i';

                if (preg_match($pattern, $v, $match)) {
                    if ($isBreakingChange || !empty($match['type'])) {
                        $historyItem = new HistoryItem();
                        $historyItem->ref = $match['ref'];
                        $historyItem->type = $match['type'] ?? null;
                        $historyItem->scope = $match['type'] ? substr(substr($match['scope'], 0, strlen($match['scope']) - 1), 1) : null;
                        $historyItem->text = $match['text'] ?? null;
                        $historyItem->description = $description;
                        $historyItem->isBreakingChange = $isBreakingChange;

                        $historyItems[] = $historyItem;
                    }
                }
            }
        }

        return $historyItems;
    }
}
