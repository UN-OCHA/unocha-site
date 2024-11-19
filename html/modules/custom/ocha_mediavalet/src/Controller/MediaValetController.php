<?php

namespace Drupal\ocha_mediavalet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ocha_mediavalet\Services\MediaValetService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for MediaValet.
 */
class MediaValetController extends ControllerBase {

  /**
   * MediaValet service.
   *
   * @var \Drupal\ocha_mediavalet\Services\MediaValetService
   */
  protected $mediavaletService;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    MediaValetService $mediavalet_service,
    RequestStack $request_stack,
  ) {
    $this->mediavaletService = $mediavalet_service;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ocha_mediavalet.service.client'),
      $container->get('request_stack'),
    );
  }

  /**
   * Get the OEmbed JSON data for a video.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with the oembed data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Not found exception if there was not data for the video.
   */
  public function oembed() {
    $request = $this->requestStack->getCurrentRequest();

    $parameters = $request->query;
    $url = $parameters->get('url');

    if (empty($url) || strpos($url, 'https://www.unocha.org/mediavalet/video/') !== 0) {
      throw new NotFoundHttpException();
    }

    // Extract the video asset ID.
    $id = substr($url, strlen('https://www.unocha.org/mediavalet/video/'));
    if (empty($id)) {
      throw new NotFoundHttpException();
    }

    return $this->video($id);
  }

  /**
   * Get the OEmbed JSON data for a video.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with the oembed data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Not found exception if there was not data for the video.
   */
  public function video($uuid) {
    if (empty($uuid)) {
      return $this->oembed();
    }

    // Time-out of oembed is hard-coded to 5 seconds.
    // @see \Drupal\media\OEmbed\ResourceFetcher:fetchResource().
    $this->mediavaletService->setTimeout(10000);

    $asset = $this->mediavaletService->getAsset($uuid);
    $data = $asset->getData();

    $html = '';
    $oembed_method = $this->config('ocha_mediavalet.settings')->get('oembed_method') ?? 'local';

    if ($oembed_method == 'local') {
      $html = '<video preload="none"
        poster="' . $data['large'] . '"
        controls
        style="width:100%;">
        <source src="' . $data['download'] . '" type="video/mp4">
        </video>';
    }
    else {
      $embed = $this->mediavaletService->getEmbedLink($uuid)->getData();
      if (isset($embed['cdnLink'])) {
        $html = '<iframe
          style="width:100%;" src="' . $embed['cdnLink'] . '"
          frameborder="0"
          allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin"
          allowfullscreen=""
          loading="lazy"
          title="' . htmlspecialchars($data['title']) . '"></iframe>';
      }
    }

    $width = (int) $data['width'] ?? 800;
    $height = (int) $data['height'] ?? 800;

    $oembed = [
      'type' => 'video',
      'version' => '1.0',
      'title' => $data['title'] ?? '',
      'width' => $width,
      'height' => $height,
    ];

    if (!empty($html)) {
      $oembed['html'] = $html;
    }
    else {
      $oembed['type'] = 'photo';
      $oembed['url'] = $data['large'];
    }

    $oembed['thumbnail_url'] = $data['thumb'];
    $oembed['thumbnail_width'] = 800;
    $oembed['thumbnail_height'] = round($height * 800 / $width);

    return new JsonResponse($oembed);
  }

}
