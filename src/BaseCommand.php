<?php

namespace DrupalMaintenanceReporting;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;

/**
 * Base class for commands.
 */
abstract class BaseCommand extends Command {

  protected string $firstCommit;
  protected string $lastCommit;

  protected array $composerLockDataCache = [];

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

  protected function runCommandWithKnownException(string $command) {
    try {
      return $this->runCommand($command);
    }
    catch (ProcessFailedException $e) {
      return $e->getProcess();
    }
  }

  /**
   * Saves a commit.
   *
   * @param string $commit_id
   *   Commit id.
   * @param string $filepath
   *   Filepath.
   */
  protected function saveFileAtCommit(string $commit_id, string $filename, string $filepath) {
    $first_commit_data = $this->runCommand(sprintf('git show %s:%s', $commit_id, $filename))->getOutput();
    file_put_contents($filepath, $first_commit_data);
  }

  protected function getFirstCommit(string $date, string $branch) {
    if (!isset($this->firstCommit)) {
      $this->firstCommit = trim($this->runCommand("git log origin/$branch --after=$date --pretty=format:'%h' | tail -n1")->getOutput());
    }
    return $this->firstCommit;
  }

  protected function getLastCommit($date, $branch) {
    if (!isset($this->lastCommit)) {
      $this->lastCommit = trim($this->runCommand("git log origin/$branch --until=$date --pretty=format:'%h' | head -n1")->getOutput());
    }
    return $this->lastCommit;
  }

  protected function getComposerLockData(string $folder) {
    if (!isset($this->composerLockDataCache[$folder])) {
      $this->composerLockDataCache[$folder] = json_decode(file_get_contents($folder . '/composer.lock'), TRUE);
    }
    return $this->composerLockDataCache[$folder];
  }

}
