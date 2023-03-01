<?php

namespace DrupalMaintenanceReporting;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalMaintenanceReporter extends BaseCommand {
  protected static $defaultName = 'report';

  protected function configure() {
    parent::configure();
    $this->setDescription('Reports maintenance actions done in a specific period.');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $output->writeln('Composer lock diff:');
    $this->getApplication()->find('composer-lock-diff-period')
      ->run($input, $output);

    $output->writeln('Securities fixed:');
    $this->getApplication()->find('securities-fixed')
      ->run($input, $output);
    return 1;
  }


}
