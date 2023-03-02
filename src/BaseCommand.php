<?php

namespace DrupalMaintenanceReporter;

use DrupalMaintenanceReporter\Exception\CommitsNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;

/**
 * Base class for commands.
 */
abstract class BaseCommand extends Command {

  /**
   * Cache of composer.lock files that have been read.
   *
   * Used to reduce disk usage.
   *
   * @var array
   */
  protected array $composerLockDataCache = [];

  /**
   * Base path where volatile files are generated.
   *
   * It is needed a folder to place composer files so
   * the audit can be performed.
   *
   * @var string
   */
  protected string $dirBasePath;

  /**
   * Generates a directory where the composer files will be placed.
   */
  protected function generateDirSkeleton() {
    $this->dirBasePath = sys_get_temp_dir() . '/drupal-maintenance-report-' . hash('sha256', random_bytes(20));
    mkdir($this->getDirMainLocation());
  }

  /**
   * Get the path of the directory used to place composer files.
   *
   * @return string
   *   Absolute path with the location.
   */
  protected function getDirMainLocation() {
    return $this->dirBasePath;
  }

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

  protected function showSummary(InputInterface $input, OutputInterface $output) {
    $output->writeln(sprintf('Base reference commit: %s', $this->getBaseCommit($input->getOption('from'), $input->getArgument('branch'), '%h at %ci')));
    $output->writeln(sprintf('Latest reference commit: %s', $this->getLastCommit($input->getOption('to'), $input->getArgument('branch'), '%h at %ci')));
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

  /**
   * Runs a command capturing its expected exception.
   *
   * Used to run commands like composer audit without having to
   * add a try catch in the implementation.
   *
   * @param string $command
   *   Full command.
   *
   * @return Process
   *   Process result.
   */
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

  /**
   * Get the base commit given a specfic date.
   *
   * The base commit is the commit previous
   * to the date from the commits must be looked up.
   *
   * @param string $date
   *   Date.
   * @param string $branch
   *   Branch.
   * @param string $format
   *   Format.
   *
   * @return string
   *   Commit hash.
   */
  protected function getBaseCommit(string $date, string $branch, string $format = '%h') {
    $first_commit = trim($this->runCommand("git log origin/$branch --after=$date --pretty=format:'%h' | tail -n1")->getOutput());

    $base_commit = trim($this->runCommand("git show $first_commit~1 --pretty=format:'$format'")->getOutput());

    if (empty($base_commit)) {
      throw new CommitsNotFoundException('There are no commits in the selected date!');
    }

    return $base_commit;
  }

  /**
   * Get the last commit given a specfic date.
   *
   * @param string $date
   *   Date.
   * @param string $branch
   *   Branch.
   * @param string $format
   *   Format.
   *
   * @return string
   *   Commit hash.
   */
  protected function getLastCommit($date, $branch, string $format = '%h') {
    $last_commit = trim($this->runCommand("git log origin/$branch --until=$date --pretty=format:'$format' | head -n1")->getOutput());

    if (empty($last_commit)) {
      throw new CommitsNotFoundException('There are no commits in the selected date!');
    }

    return $last_commit;
  }

  /**
   * Get the data of a composer lock file.
   *
   * @param string $folder
   *   Folder.
   *
   * @return object
   *   Json with the composer lock data.
   */
  protected function getComposerLockData(string $folder) {
    if (!isset($this->composerLockDataCache[$folder])) {
      $this->composerLockDataCache[$folder] = json_decode(file_get_contents($folder . '/composer.lock'), TRUE);
    }
    return $this->composerLockDataCache[$folder];
  }

  /**
   * Cleanup the directories.
   */
  protected function cleanup() {
    $this->runCommand(sprintf('rm -r %s', $this->getDirMainLocation()));
  }

}
