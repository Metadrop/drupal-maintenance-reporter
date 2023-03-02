<?php

namespace DrupalMaintenanceReporter;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates a full maintenance report.
 */
class DrupalMaintenanceReporter extends BaseCommand {

  protected static $defaultName = 'report';

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    parent::configure();
    $this->setDescription('Reports maintenance actions done in a specific period. It includes: packages updated, fixed securities.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $output->writeln('Composer lock diff:');
    $this->getApplication()->find('composer-lock-diff-period')
      ->run($input, $output);

    $output->writeln('Securities fixed:');
    $this->getApplication()->find('securities-fixed')
      ->run($input, $output);
    return 0;
  }


}
