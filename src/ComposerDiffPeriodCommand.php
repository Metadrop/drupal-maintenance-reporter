<?php

namespace DrupalMaintenanceReporting;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Report composer diff for a specific period.
 */
class ComposerDiffPeriodCommand extends Command {

  protected static $defaultName = 'composer-lock-diff-period';

  /**
   * {@inheritdoc}
   */
  protected function configure() {

    $this->setDescription('Shows composer diff report for a repository in a specific period.');

    $this->addArgument(
      'branch'
    );

    $this->addOption(
      'from',
      'f',
      InputOption::VALUE_REQUIRED,
      'Y-m-d date to check the composer.lock from'
    );

    $this->addOption(
      'to',
      't',
      InputOption::VALUE_REQUIRED,
      'Y-m-d date to check the composer.lock to'
    );

  }

  public function execute(InputInterface $input, OutputInterface $output) : int {
    $branch = $input->getArgument('branch');
    $from = $input->getOption('from');
    $to = $input->getOption('to');

    $this->runCommand(sprintf('git fetch origin %s', $branch));

    $composer_lock_from_filename = 'composer-lock-from.json';

    $first_commit = $this->runCommand("git log origin/$branch --after=$from --pretty=format:'%h' | tail -n1")->getOutput();
    $this->saveGitCommit(trim($first_commit), $composer_lock_from_filename);

    $composer_lock_to_filename = 'composer-lock-to.json';
    $last_commit = $this->runCommand("git log origin/$branch --until=$to --pretty=format:'%h' | head -n1")->getOutput();
    $this->saveGitCommit(trim($last_commit), $composer_lock_to_filename);

    $output->writeln($this->runCommand(sprintf('composer-lock-diff --from %s --to %s', $composer_lock_from_filename, $composer_lock_to_filename)));

    $this->runCommand(sprintf('rm %s %s'), $composer_lock_from_filename, $composer_lock_to_filename);

    return 1;
  }

  protected function saveGitCommit(string $commit_id, string $filepath) {
    $first_commit_data = $this->runCommand(sprintf('git show %s:composer.lock', $commit_id))->getOutput();
    file_put_contents($filepath, $first_commit_data);
  }

  /**
   * Runs a shell command.
   *
   * @param string $command
   *   Command.
   *
   * @return Process
   *   It can be used to obtain the command output if needed.
   *
   * @throws ProcessFailedException
   *   When the command fails.
   */
  protected function runCommand(string $command) {
    $process = Process::fromShellCommandline($command);
    $process->run();
    if (!$process->isSuccessful()) {
      throw new ProcessFailedException($process);
    }
    return $process;
  }

}
