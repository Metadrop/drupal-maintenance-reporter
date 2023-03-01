<?php

namespace DrupalMaintenanceReporting;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Semver\Semver;
use GuzzleHttp\Client;

/**
 * Report security advisories fixed in a specific date.
 */
class SecuritiesFixedCommand extends BaseCommand {

  protected static $defaultName = 'securities-fixed';

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->showSummary($input, $output);
    $this->generateDirSkeleton();
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

    $this->saveComposerCommitStatus($this->getFirstCommit($from, $branch), $this->getComposerJsonFromLocation());
    $this->saveComposerCommitStatus($this->getLastCommit($to, $branch), $this->getComposerJsonToLocation());

    $output->writeln("\n");
    $fixed_advisories_table = $this->getComposerFixedSecurityAdvisories($to, $output);
    $output->writeln('Fixed security advisories (Composer):');
    $fixed_advisories_table->render();

    $output->writeln("\n");
    $fixed_drupal_advisories_table = $this->getFixedDrupalSecurities($output);
    $output->writeln('Fixed security advisories (Drupal):');
    $fixed_drupal_advisories_table->render();

    $this->cleanup();
    return 1;
  }

  /**
   * Get the fixed advisories from composer.
   *
   * @param string $to
   *   Date limit to check fixed securities. If there is a security reported later, if won't be checked.
   * @param OutputInterface $output
   *   Used to build the table with fixed advisories.
   *
   * @return Table
   *   Output table prepared to render.
   */
  protected function getComposerFixedSecurityAdvisories(string $to, OutputInterface $output) {
    $from_advisories = $this->getFolderSecurityAdvisoriesByDate($this->getComposerJsonFromLocation(), $to);
    $to_advisories = $this->getFolderSecurityAdvisoriesByDate($this->getComposerJsonToLocation(), $to);

    $fixed_advisories = array_values(array_filter($from_advisories, function ($advisory_key) use($to_advisories) {
      return !array_key_exists($advisory_key, $to_advisories);
    }, ARRAY_FILTER_USE_KEY));

    $table = new Table($output);
    $table->setHeaders(['Package', 'CVE', 'Link']);
    $table->setRows($fixed_advisories);
    return $table;
  }

  /**
   * Get the fixed drupal securities.
   *
   * The process is done in the following steps:
   *   1. Detect the drupal securities from the starting date.
   *   2. Detect the drupal securities from the end date.
   *   3. From the first list, get the securities that are not present in the second list, that means they are fixed.
   *
   * @param OutputInterface $output
   *   Output to create the table with results.
   *
   * @return Table
   *   Table ready to render the securities.
   */
  protected function getFixedDrupalSecurities(OutputInterface $output) {
    $from_advisories = $this->getFolderDrupalAdvisories($this->getComposerJsonFromLocation());
    $to_advisories = $this->getFolderDrupalAdvisories($this->getComposerJsonToLocation());

    $fixed_securities =  array_values(array_filter($from_advisories, function ($advisory_key) use ($to_advisories) {
      return !array_key_exists($advisory_key, $to_advisories);
    }, ARRAY_FILTER_USE_KEY));

    $composer_lock_to_data = $this->getComposerLockData($this->getComposerJsonToLocation());
    $fixed_securities = array_filter(array_map(function ($advisory) use ($composer_lock_to_data) {
      $data = [
        'name' => $advisory['name'],
        'from' => $advisory['version'],
        'to' => '-',
      ];
      foreach ($composer_lock_to_data['packages'] as $package) {
        if ($package['name'] == $advisory['name']) {
          $data['to'] =  $package['version'];
          break;
        }
      }
      return $data;
    }, $fixed_securities));

    $table = new Table($output);
    $table->setHeaders(['Package', 'From', 'To']);
    $table->setRows($fixed_securities);

    return $table;
  }

  /**
   * Get security advisories from a specific folder.
   *
   * @param string $folder
   *   Folder.
   *
   * @return array
   *   List of security updates.
   *
   * @throws \Exception
   */
  protected function getFolderDrupalAdvisories(string $folder) {
    $composer_lock_data = $this->getComposerLockData($folder);
    $security_advisories = $this->fetchAdvisoryComposerJson();
    return $this->calculateSecurityUpdates($composer_lock_data, $security_advisories);
  }

  /**
   * Fetches the generated composer.json from drupal-security-advisories.
   *
   * @return mixed
   *
   * @throws \Exception
   */
  protected function fetchAdvisoryComposerJson() {
    $client = new Client();
    $response = $client->get('https://raw.githubusercontent.com/drupal-composer/drupal-security-advisories/9.x/composer.json');
    return json_decode($response->getBody(), true);
  }

  /**
   * Return available security updates.
   *
   * @param array $composer_lock_data
   *   The contents of the local Drupal application's composer.lock file.
   * @param array $security_advisories_composer_json
   *   The composer.json array from drupal-security-advisories.
   *
   * @return array
   *   Security updates list.
   */
  protected function calculateSecurityUpdates($composer_lock_data, $security_advisories_composer_json)
  {
    $updates = [];
    $packages = $composer_lock_data['packages'];
    $conflict = $security_advisories_composer_json['conflict'];
    foreach ($packages as $package) {
      $name = $package['name'];
      if (!empty($conflict[$name]) && Semver::satisfies($package['version'], $security_advisories_composer_json['conflict'][$name])) {
        $updates[$name] = [
          'name' => $name,
          'version' => $package['version'],
        ];
      }
    }
    return $updates;
  }

  /**
   * Generates a directory where the composer files will be placed.
   */
  protected function generateDirSkeleton() {
    parent::generateDirSkeleton();
    mkdir($this->getComposerJsonFromLocation());
    mkdir($this->getComposerJsonToLocation());
  }

  /**
   * Get the location of the composer json start date files.
   *
   * @return string
   *   Absolute path.
   */
  protected function getComposerJsonFromLocation() {
    return $this->dirBasePath . '/from';
  }

  /**
   * Get the location of the composer json end date files.
   *
   * @return string
   *   Absolute path.
   */
  protected function getComposerJsonToLocation() {
    return $this->dirBasePath . '/to';
  }

  protected function saveComposerCommitStatus(string $commit_id, string $folder) {
    $this->saveFileAtCommit($commit_id, 'composer.lock', $folder . '/composer.lock');
    $this->saveFileAtCommit($commit_id, 'composer.json', $folder . '/composer.json');
  }

  /**
   * Get the security advisories of composer audit from a specific date.
   *
   * @param string $folder
   *   Folder where it is wanted to get the security update.
   * @param string $date
   *   This date is used to get CVE reports created before this date.
   *
   * @return array
   *   List of security advisories.
   */
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

}
