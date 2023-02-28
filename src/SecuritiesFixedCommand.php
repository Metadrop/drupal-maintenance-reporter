<?php

namespace DrupalMaintenanceReporting;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Report composer diff for a specific period.
 */
class SecuritiesFixedCommand extends BaseCommand {

  protected static $defaultName = 'securities-fixed';

  protected string $dirBasePath;

  protected function initialize(InputInterface $input, OutputInterface $output)
  {
    $this->generateDirSkeleton();
  }

  /**
   * Generates a directory where the composer files will be placed.
   */
  protected function generateDirSkeleton() {
    $this->dirBasePath = sys_get_temp_dir() . '/drupal-maintenance-report-' . hash('sha256', random_bytes(20));
    mkdir($this->getDirMainLocation());
    mkdir($this->getComposerJsonFromLocation());
    mkdir($this->getComposerJsonToLocation());
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    parent::configure();
    $this->setDescription('Shows the securities updated in a specific period.');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) : int {
    $branch = $input->getArgument('branch');
    $from = $input->getOption('from');
    $to = $input->getOption('to');

    $this->placeFirstCommitJson($from, $branch);
    $this->placeLatestCommitJson($to, $branch);

    $from_advisories = $this->getFolderSecurityAdvisoriesByDate($this->getComposerJsonFromLocation(), $to);
    $to_advisories = $this->getFolderSecurityAdvisoriesByDate($this->getComposerJsonToLocation(), $to);

    $fixed_advisories = array_values(array_filter($from_advisories, function ($advisory_key) use($to_advisories) {
      return !array_key_exists($advisory_key, $to_advisories);
    }, ARRAY_FILTER_USE_KEY));


    $table = new Table($output);
    $table->setHeaders(['Package', 'CVE', 'Link']);
    $table->setRows($fixed_advisories);
    $table->render();

    $this->cleanup();
    return 1;
  }

  protected function getDirMainLocation() {
    return $this->dirBasePath;
  }

  protected function getComposerJsonFromLocation() {
    return $this->dirBasePath . '/from';
  }

  protected function getComposerJsonToLocation() {
    return $this->dirBasePath . '/to';
  }

  protected function placeFirstCommitJson(string $date, string $branch) {
    $first_commit = trim($this->runCommand("git log origin/$branch --after=$date --pretty=format:'%h' | tail -n1")->getOutput());
    $this->saveComposerCommitStatus($first_commit, $this->getComposerJsonFromLocation());
  }

  protected function placeLatestCommitJson(string $date, string $branch) {
    $last_commit = trim($this->runCommand("git log origin/$branch --until=$date --pretty=format:'%h' | head -n1")->getOutput());
    $this->saveComposerCommitStatus($last_commit, $this->getComposerJsonToLocation());
  }

  protected function saveComposerCommitStatus(string $commit_id, string $folder) {
    $this->saveFileAtCommit($commit_id, 'composer.lock', $folder . '/composer.lock');
    $this->saveFileAtCommit($commit_id, 'composer.json', $folder . '/composer.json');
  }


  protected function getFolderSecurityAdvisoriesByDate(string $folder, string $date) {
    $date = new \DateTime($date);
    $security_advisories = json_decode($this->runCommandWithKnownException(sprintf('composer audit --locked --working-dir=%s --format=json', $folder))->getOutput());
    if (!empty($security_advisories) && isset($security_advisories->advisories)) {
      $security_advisories_list_by_package = (array) $security_advisories->advisories;
      $security_advisories_list = call_user_func_array('array_merge', array_values($security_advisories_list_by_package));
      $security_advisories_list = array_filter($security_advisories_list, function ($advisory) use ($date) {
        if (isset($advisory->reportedAt->date)) {
          $advisory_datetime = new \DateTime($advisory->reportedAt->date);
          return $advisory_datetime->getTimestamp() < $date->getTimestamp();
        }

        return FALSE;
      });
    }
    else {
      $security_advisories_list = [];
    }

    $security_advisories_list_formatted = [];
    foreach ($security_advisories_list as $advisory) {
      $security_advisories_list_formatted[sprintf('%s-%s', $advisory->packageName, $advisory->cve)] = [
        $advisory->packageName,
        $advisory->title,
        $advisory->link,
      ];
    }
    return $security_advisories_list_formatted;
  }

  protected function cleanup() {
    $this->runCommand(sprintf('rm -r %s', $this->getDirMainLocation()));
  }

}
