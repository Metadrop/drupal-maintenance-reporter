<?php

namespace DrupalMaintenanceReporter\Exception;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Thrown when there aren't commits found.
 */
class CommitsNotFoundException extends \RuntimeException {

  /**
   * Helper to handle exceptions on not found commits.
   *
   * @param OutputInterface $output
   *   Output.
   *
   * @return int
   *   Exit code.
   */
  public function handle(OutputInterface $output) {
    $output->writeln('No code changes have been found.');
    return 0;
  }

}
