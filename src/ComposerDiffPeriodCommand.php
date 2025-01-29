<?php

namespace DrupalMaintenanceReporter;

use DrupalMaintenanceReporter\Exception\CommitsNotFoundException;
use Symfony\Component\Console\Helper\Table;
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

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->generateDirSkeleton();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) : int {
    try {
      $this->showSummary($input, $output);
    }
    catch (CommitsNotFoundException $exception) {
      return $exception->handle($output);
    }

    $branch = $input->getArgument('branch');
    $from = $input->getOption('from');
    $to = $input->getOption('to');

    $composer_lock_from_filepath = $this->dirBasePath . '/composer-lock-from.json';

    $base_commit = $this->getBaseCommit($from, $branch);

    $this->saveFileAtCommit($base_commit, 'composer.lock', $composer_lock_from_filepath);

    // @todo: place files into a specific temporary folder!
    $composer_lock_to_filename = $this->dirBasePath . '/composer-lock-to.json';
    $last_commit = $this->getLastCommit($to, $branch);
    $this->saveFileAtCommit($last_commit, 'composer.lock', $composer_lock_to_filename);

    $output->writeln("\n");
    $composer_lock_diff = json_decode($this->runCommand(sprintf('composer-lock-diff --from %s --to %s --json', $composer_lock_from_filepath, $composer_lock_to_filename))->getOutput(), true);

    $output->writeln("Production changes:\n");
    if (!empty($composer_lock_diff['changes'])) {
      $this->printComposerChanges($composer_lock_diff['changes'], $output);
    }
    else {
      $output->writeln('No changes has been found in the selected period.');
    }

    $output->writeln("Development changes:\n");
    if (!empty($composer_lock_diff['changes-dev'])) {
      $this->printComposerChanges($composer_lock_diff['changes-dev'], $output);
    }
    else {
      $output->writeln('No changes has been found in the selected period.');
    }

    $this->cleanup();

    return 0;
  }

  /**
   * Print the composer changes shown by composer lock diff.
   *
   * @param array $changes
   *   Changes from changes-dev property or changes.
   * @param string $label
   *   Label that will appear in the output.
   * @param OutputInterface $output
   *   Output to print the changes.
   */
  protected function printComposerChanges(array $changes, OutputInterface $output) {
    $production_changes_table = new Table($output);
    $production_changes_table->setHeaders(['Package', 'From', 'To']);

    foreach ($changes as $package => $package_changes) {
      [$from, $to] = $package_changes;
      $production_changes_table->addRow([$package, $from, $to]);
    }

    $production_changes_table->render();
  }

}
