<?php

namespace Decaf\StandardVersion\Composer\Commands;

use Composer\Command\BaseCommand;
use Decaf\StandardVersion\Handler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StandardVersionCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('standard-version');
        $this->setDescription('Process standard-version command to current project.');

        $this->setDefinition(
            new InputDefinition([
                new InputArgument('folder', InputOption::VALUE_OPTIONAL),
                new InputOption('composer', 'c', InputOption::VALUE_OPTIONAL),
                new InputOption('tag', 't', InputOption::VALUE_OPTIONAL),
                new InputOption('push', 'p', InputOption::VALUE_OPTIONAL),
                new InputOption('force', 'f', InputOption::VALUE_OPTIONAL),
            ])
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new Handler();
        $handler->setInput($input);
        $handler->setOutput($output);
        $handler->setComposer($this->getComposer());

        if ($input->hasArgument('folder')) {
            $folder = $input->getArgument('folder');

            $output->writeln('* folder: '.$folder[0]);

            $handler->setRepositoryFolder($folder[0]);
        }

        if ($input->hasParameterOption('--changelog') || $input->hasParameterOption('-c') ||
            $input->hasParameterOption('--tag') || $input->hasParameterOption('-t') ||
            $input->hasParameterOption('--push') || $input->hasParameterOption('-p')) {
            $handler->setModifyChangelog(false);
            $handler->setTagVersion(false);
            $handler->setGitPush(false);
        }

        if ($input->hasParameterOption('--changelog') || $input->hasParameterOption('-c')) {
            $output->writeln(' * modify CHANGELOG.MD enabled');

            $handler->setModifyChangelog(true);
        }

        if ($input->hasParameterOption('--tag') || $input->hasParameterOption('-t')) {
            $output->writeln(' * create tag enabled');

            $handler->setTagVersion(true);
        }

        if ($input->hasParameterOption('--push') || $input->hasParameterOption('-p')) {
            $output->writeln(' * push to origin enabled');

            $handler->setGitPush(true);
        }

        if ($input->hasParameterOption('--force') || $input->hasParameterOption('-f')) {
            $output->writeln(' * force enabled');

            $handler->setForce(true);
        }

        $output->writeln('starting ...', OutputInterface::VERBOSITY_VERBOSE);

        $handler->process();
        
        return 0;
    }
}
