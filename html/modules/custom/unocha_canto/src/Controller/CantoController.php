<?php

namespace Drupal\unocha_canto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\unocha_canto\Services\CantoApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
    RequestStack $request_stack
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
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Bad request exception if there is no endpoint.
   */
  public function api() {
    $request = $this->requestStack->getCurrentRequest();

    $parameters = $request->query;
    $endpoint = $parameters->get('endpoint');

    if (empty($endpoint)) {
      throw new BadRequestHttpException();
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
      throw new BadRequestHttpException();
    }

    return new Response($data['content'], $data['code'], $data['headers']);
  }

}
