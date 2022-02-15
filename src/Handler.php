<?php

namespace Decaf\StandardVersion;

use Composer\Composer;
use Composer\Question\StrictConfirmationQuestion;
use Decaf\StandardVersion\Generators\Markdown;
use Decaf\StandardVersion\Git\Git;
use Exception;
use Symfony\Component\Console\Helper\QuestionHelper;
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
     * @var string
     */
    protected string $repositoryFolder = './';

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
     * @return string
     */
    public function getRepositoryFolder(): string
    {
        return $this->repositoryFolder;
    }

    /**
     * @param bool $repositoryFolder
     */
    public function setRepositoryFolder($repositoryFolder): void
    {
        $this->repositoryFolder = $repositoryFolder;
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
        $input = $this->getInput();
        $output = $this->getOutput();
        $composer = $this->getComposer();

        $tagPrefix = '';

        $extra = $composer->getPackage()->getExtra();
        if (isset($extra['decaf/standard-version'])) {
            $extra = $extra['decaf/standard-version'];
        }

        $git = new Git();

        $git->checkCredentials();

        $git->changeCurrentFolder($this->getRepositoryFolder());

        $git->checkWorkingCopy();

        $git->setTagPrefix($extra['tag-prefix'] ?? null);

        $currentBranch = $git->getCurrentBranch();

        if ($currentBranch !== 'main' || $currentBranch !== 'master') {
            throw new Exception('Please checkout "main/master" branch first! You are currently on "'.$currentBranch.'"');
        }

        $repository = $git->initRepository($extra);

        $latestTag = $git->getLatestTag();

        $currentVersion = $git->parseTag($latestTag);

        $output->writeln('current version: '.$currentVersion->getVersionString($git->getTagPrefix()));

        $gitHistory = $git->getHistory($currentVersion);

        $history = $git->parseHistory($gitHistory->output);

        $nextVersion = $currentVersion->getNextVersion($history);

        $output->writeln('next version...: '.$nextVersion->getVersionString($git->getTagPrefix()));

        $markdown = new Markdown();
        $markdown = $markdown->generate($repository, $currentVersion, $nextVersion, $history);

        $output->writeln('****************************************************************************');
        $output->writeln($markdown);
        $output->writeln('****************************************************************************');

        $dialog = new QuestionHelper();
        $question = new StrictConfirmationQuestion('Do you want to continue? [YES|no]');
        $question->setMaxAttempts(1);

        if (!$dialog->ask($input, $output, $question)) {
            return;
        }

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
            $remoteUrl = $git->getRemoteUrl();
            $tag = $git->getTagPrefix().$nextVersion->getVersionString();

            $git->pushTag($tag);

            $output->writeln('pushed tag: '.$tag);

            $git->push();

            $output->writeln('pushed to origin: '.$remoteUrl);
        }
    }
}
