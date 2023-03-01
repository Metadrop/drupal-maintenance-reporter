<?php

namespace DrupalMaintenanceReporting;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Report composer diff for a specific period.
 */
class ComposerDiffPeriodCommand extends BaseCommand {

  protected static $defaultName = 'composer-lock-diff-period';

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    parent::configure();
    $this->setDescription('Shows composer diff report for a repository in a specific period.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output)
  {
    $this->showSummary($input, $output);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) : int {
    $branch = $input->getArgument('branch');
    $from = $input->getOption('from');
    $to = $input->getOption('to');

    $composer_lock_from_filename = 'composer-lock-from.json';

    $first_commit = $this->runCommand("git log origin/$branch --after=$from --pretty=format:'%h' | tail -n1")->getOutput();
    $this->saveFileAtCommit(trim($first_commit), 'composer.lock', $composer_lock_from_filename);

    // @todo: place files into a specific temporary folder!
    $composer_lock_to_filename = 'composer-lock-to.json';
    $last_commit = $this->runCommand("git log origin/$branch --until=$to --pretty=format:'%h' | head -n1")->getOutput();
    $this->saveFileAtCommit(trim($last_commit), 'composer.lock', $composer_lock_to_filename);

    $output->writeln("\n");
    $output->writeln($this->runCommand(sprintf('composer-lock-diff --from %s --to %s', $composer_lock_from_filename, $composer_lock_to_filename)));

    $this->runCommand(sprintf('rm %s %s', $composer_lock_from_filename, $composer_lock_to_filename));

    return 1;
  }

}
