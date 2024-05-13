<?php

namespace Drupal\unocha_canto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unocha_canto\Services\CantoApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to proxy queries to the Canto API or retrieve static assets.
 */
class CantoController extends ControllerBase {

  /**
   * The Canto API client.
   *
   * @var \Drupal\unocha_canto\Services\CantoApiClient
   */
  protected $cantoApiClient;

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
    CantoApiClient $canto_api_client,
    RequestStack $request_stack,
  ) {
    $this->cantoApiClient = $canto_api_client;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('canto_api.client'),
      $container->get('request_stack')
    );
  }

  /**
   * Perform a request against the Canto API.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with the Canto API response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Not found exception if there is no endpoint.
   */
  public function api() {
    $request = $this->requestStack->getCurrentRequest();

    $parameters = $request->query;
    $endpoint = $parameters->get('endpoint');

    if (empty($endpoint)) {
      throw new NotFoundHttpException();
    }

    $parameters->remove('endpoint');

    $method = $request->getMethod();
    if ($method === 'GET') {
      $payload = $parameters->all();
    }
    else {
      $payload = $request->request->all();
    }

    $data = $this->cantoApiClient->request($endpoint, $payload, $method);
    if (empty($data)) {
      throw new NotFoundHttpException();
    }

    return new Response($data['content'], $data['code'], $data['headers']);
  }

  /**
   * Get the OEmbed JSON data for a Canto video.
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

    if (empty($url) || strpos($url, 'https://www.unocha.org/canto/video/') !== 0) {
      throw new NotFoundHttpException();
    }

    // Extract the Canto video asset ID.
    $id = substr($url, strlen('https://www.unocha.org/canto/video/'));
    if (!ctype_alnum($id)) {
      throw new NotFoundHttpException();
    }

    // The data from Canto is cached by the API client.
    $data = $this->cantoApiClient->request('/video/' . $id, [], 'GET');
    if (empty($data['content']) || !is_string($data['content'])) {
      throw new NotFoundHttpException();
    }

    try {
      $data = json_decode($data['content'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Exception $exception) {
      throw new NotFoundHttpException();
    }

    if (empty($data['width']) || empty($data['height']) || empty($data['url']['directUrlPreviewPlay'])) {
      throw new NotFoundHttpException();
    }

    $video_url = $data['url']['directUrlPreviewPlay'];
    $width = (int) $data['width'];
    $height = (int) $data['height'];

    $oembed = [
      'type' => 'video',
      'version' => '1.0',
      'title' => $data['name'] ?? $id,
      'html' => '<video controls style="width:100%;"><source src="' . $video_url . '" type="video/mp4"></video>',
      'width' => $width,
      'height' => $height,
    ];

    if (!empty($data['url']['directUrlPreview'])) {
      $oembed['thumbnail_url'] = $data['url']['directUrlPreview'];
      $oembed['thumbnail_width'] = 800;
      $oembed['thumbnail_height'] = round($height * 800 / $width);
    }

    return new JsonResponse($oembed);
  }

}
