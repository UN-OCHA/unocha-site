<?php

namespace Drupal\ocha_mediavalet\Commands;

use Drupal\ocha_mediavalet\Api\MediaValetClient;
use Drupal\ocha_mediavalet\Services\MediaValetService;
use Drush\Commands\DrushCommands;

/**
 * Drush commandfile.
 *
 * @property \Consolidation\Log\Logger $logger
 */
class OchaMediaValetCommands extends DrushCommands {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected MediaValetService $mediavaletService,
  ) {
  }

  /**
   * Generate embed link.
   *
   * @command ocha_mediavalet:generate-embed-link
   *
   * @validate-module-enabled ocha_mediavalet
   *
   * @option timeout
   *   Timeout for HTTP requests.
   *
   * @usage ocha_mediavalet:generate-embed-link uuid
   *   Generate embed link.
   */
  public function generateEmbedLink(
    string $uuid,
    array $options = [
      'timeout' => 10,
    ],
  ) {
    $data = $this->mediavaletService
      ->setTimeout($options['timeout'])
      ->getEmbedLink($uuid)->getData();

    if (empty($data)) {
      $this->output->writeln(strtr('No embed link found/created for @uuid', [
        '@uuid' => $uuid,
      ]));
    }
    else {
      if (!isset($data['cdnLink'])) {
        print_r($data);
      }
      $this->output->writeln(strtr('Embed for @uuid is @url', [
        '@uuid' => $uuid,
        '@url' => $data['cdnLink'],
      ]));
    }
  }

  /**
   * Generate missing embed links.
   *
   * @command ocha_mediavalet:generate-missing-embed-links
   *
   * @validate-module-enabled ocha_mediavalet
   *
   * @option timeout
   *   Timeout for HTTP requests.
   *
   * @usage ocha_mediavalet:generate-missing-embed-links
   *   Generate missing embed links.
   */
  public function generateMissingEmbedLinks(
    array $options = [
      'timeout' => 10,
    ],
  ) {

    $this->mediavaletService
      ->setMediaType(MediaValetClient::MEDIATYPEVIDEO)
      ->setCount(9999)
      ->setOffset(0);
    $videos = $this->mediavaletService->search('', '')->getData();

    $this->output()->writeln(strtr('Checking @num video files.', [
      '@num' => count($videos),
    ]));

    foreach ($videos as $uuid => $video) {
      $this->generateEmbedLink($uuid, $options);
    }
  }

}
