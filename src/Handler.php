<?php

namespace Decaf\StandardVersion;

use Composer\Composer;
use Decaf\StandardVersion\Generators\Markdown;
use Decaf\StandardVersion\Git\Git;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Handler
{
    /**
     * @var Composer
     */
    protected Composer $composer;

    /**
     * @var bool
     */
    protected bool $modifyChangelog = true;

    /**
     * @var bool
     */
    protected bool $tagVersion = true;

    /**
     * @var bool
     */
    protected bool $gitPush = true;

    /**
     * @var bool
     */
    protected bool $dryRun = false;

    /**
     * @var InputInterface|null
     */
    protected ?InputInterface $input = null;

    /**
     * @var OutputInterface|null
     */
    protected ?OutputInterface $output = null;

    /**
     * @return Composer
     */
    public function getComposer(): Composer
    {
        return $this->composer;
    }

    /**
     * @param Composer $composer
     */
    public function setComposer(Composer $composer): void
    {
        $this->composer = $composer;
    }

    /**
     * @return bool
     */
    public function getModifyChangelog(): bool
    {
        return $this->modifyChangelog;
    }

    /**
     * @param bool $modifyChangelog
     */
    public function setModifyChangelog(bool $modifyChangelog): void
    {
        $this->modifyChangelog = $modifyChangelog;
    }

    /**
     * @return bool
     */
    public function getTagVersion(): bool
    {
        return $this->tagVersion;
    }

    /**
     * @param bool $tagVersion
     */
    public function setTagVersion(bool $tagVersion): void
    {
        $this->tagVersion = $tagVersion;
    }

    /**
     * @return bool
     */
    public function getGitPush(): bool
    {
        return $this->gitPush;
    }

    /**
     * @param bool $gitPush
     */
    public function setGitPush(bool $gitPush): void
    {
        $this->gitPush = $gitPush;
    }

    /**
     * @return bool
     */
    public function getDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * @param bool $dryRun
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @param InputInterface $input
     */
    public function setInput($input): void
    {
        $this->input = $input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput($output): void
    {
        $this->output = $output;
    }

    /**
     * @throws Exception
     */
    public function process(): void
    {
        $output = $this->getOutput();
        $composer = $this->getComposer();

        $tagPrefix = '';

        $extra = $composer->getPackage()->getExtra();
        if (isset($extra['decaf/standard-version'])) {
            $extra = $extra['decaf/standard-version'];
        }

        $git = new Git();

        $git->checkCredentials();

        $git->checkWorkingCopy();

        $git->setTagPrefix($extra['tag-prefix'] ?? null);

        $repository = $git->initRepository($extra);

        $currentBranch = $git->getCurrentBranch();

        if ($currentBranch !== 'master') {
            throw new Exception('Please checkout "master" branch first! You are currently on "'.$currentBranch.'"');
        }

        $latestTag = $git->getLatestTag();

        $currentVersion = $git->parseTag($latestTag);

        $output->writeln('current version: '.$currentVersion->getVersionString($git->getTagPrefix()));

        $gitHistory = $git->getHistory($currentVersion);

        $history = $git->parseHistory($gitHistory->output);

        $nextVersion = $currentVersion->getNextVersion($history);

        $output->writeln('next version...: '.$nextVersion->getVersionString($git->getTagPrefix()));

        $markdown = new Markdown();
        $markdown = $markdown->generate($repository, $currentVersion, $nextVersion, $history);

        if ($this->getDryRun()) {
            $output->writeln('****************************************************************************');
            $output->writeln($markdown);
            $output->writeln('****************************************************************************');
        } else {
            if ($this->getModifyChangelog()) {
                if (!file_exists('CHANGELOG.md')) {
                    $output->writeln('no CHANGELOG.md found. Creating default template');
                    copy(__DIR__.'/../template/CHANGELOG.md', 'CHANGELOG.md');

                    $changelog = file_get_contents('CHANGELOG.md');

                    $content = $changelog.$markdown;
                } else {
                    $changelog = file_get_contents('CHANGELOG.md');

                    // check if version already in Changelog
                    if (preg_match('#<a name="'.$nextVersion->getVersionString().'"></a>#', $changelog)) {
                        throw new Exception('version "'.$nextVersion->getVersionString().'" already exists in CHANGELOG.md!');
                    }

                    $content = preg_replace('/<a name=/', $markdown.'<a name=', $changelog, 1);
                }

                file_put_contents('CHANGELOG.md', $content);

                $output->writeln('CHANGELOG.md written');

                $git->add('CHANGELOG.md');

                $output->writeln('CHANGELOG.md added');

                $git->commit('chore: CHANGELOG.md added');

                $output->writeln('CHANGELOG.md committed');
            }

            if ($this->getTagVersion()) {
                $tag = $git->getTagPrefix().$nextVersion->getVersionString();
                $git->tag($tag);

                $output->writeln('tagged as version: '.$tag);
            }

            if ($this->getGitPush()) {
                $remoteOrigin = $git->getRemoteOrigin();
                $tag = $git->getTagPrefix().$nextVersion->getVersionString();

                $git->pushTag($tag);

                $output->writeln('pushed tag: '.$tag);

                $git->push();

                $output->writeln('pushed to origin: '.$remoteOrigin);
            }
        }
    }
}
