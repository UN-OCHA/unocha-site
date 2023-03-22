<?php

namespace Drupal\unocha_reliefweb\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\unocha_reliefweb\Services\ReliefWebApiClient;
use Drupal\unocha_utility\Helpers\DateHelper;
use Drupal\unocha_utility\Helpers\HtmlSanitizer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for page showing a document retrieved from ReliefWeb.
 */
class ReliefWebDocumentController extends ControllerBase {

  /**
   * ReliefWeb API config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The ReliefWeb API Client.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebApiClient
   */
  protected $apiClient;

  /**
   * The parse API data for the document.
   *
   * @var array
   */
  protected $data;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\unocha_reliefweb\Services\ReliefWebApiClient $api_client
   *   The ReliefWeb API Client.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    ReliefWebApiClient $api_client
  ) {
    $this->config = $config_factory->get('unocha_reliefweb.settings');
    $this->requestStack = $request_stack;
    $this->apiClient = $api_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('reliefweb_api.client')
    );
  }

  /**
   * Get the page title.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Page title.
   */
  public function getPageTitle() {
    $this->retrieveApiData();
    return $this->data['title'] ?? '';
  }

  /**
   * Get the page content.
   *
   * @return array
   *   Render array.
   */
  public function getPageContent() {
    $this->retrieveApiData();
    if (empty($this->data)) {
      return [];
    }

    $content = [];
    if (!empty($this->data['attachments'])) {
      $content['attachments'] = $this->renderAttachmentList($this->data['attachments']);
    }
    if (!empty($this->data['body'])) {
      $content['body'] = ['#markup' => $this->data['body']];
    }

    return [
      '#theme' => 'unocha_reliefweb_document',
      '#title' => $this->data['title'],
      '#date' => $this->data['date'],
      '#content' => $content,
    ];
  }

  /**
   * Render a list of attachemnts.
   *
   * @param array $attachments
   *   List of attachments.
   *
   * @return array
   *   Render array.
   */
  protected function renderAttachmentList(array $attachments) {
    if (empty($attachments)) {
      return [];
    }

    $list = [];
    foreach ($attachments as $attachment) {
      $extension = $this->extractFileExtension($attachment['filename']);
      $list[] = [
        'url' => $attachment['url'],
        'name' => $attachment['filename'],
        'preview' => $this->renderAttachmentPreview($attachment),
        'label' => $this->t('Download Attachment'),
        'description' => '(' . implode(' | ', array_filter([
          mb_strtoupper($extension),
          format_size($attachment['filesize']),
          $attachment['description'] ?? '',
        ])) . ')',
      ];
    }

    return [
      '#theme' => 'unocha_reliefweb_attachment_list',
      '#list' => $list,
    ];
  }

  /**
   * Render an attachment preview.
   *
   * @param array $attachment
   *   The attachment data.
   *
   * @return array
   *   Render array.
   */
  public function renderAttachmentPreview(array $attachment) {
    if (empty($attachment['preview'])) {
      return [];
    }

    // @todo review when deciding on how to deal with image styles for
    // images coming from ReliefWeb.
    return [
      '#theme' => 'image',
      '#uri' => $attachment['preview']['url-small'] . '?' . $attachment['preview']['version'],
      '#alt' => $this->t('Preview of @filename', [
        '@filename' => $attachment['filename'],
      ]),
      '#attributes' => [
        'class' => ['unocha-reliefweb-attachment-preview'],
      ],
    ];
  }

  /**
   * Retrieve the data from the ReliefWeb API.
   */
  protected function retrieveApiData() {
    $url_alias = $this->getAliasFromUrl();

    $payload = [
      'filter' => [
        'conditions' => [
          [
            // Filter to limit to OCHA documents.
            'field' => 'ocha_product',
          ],
          [
            'conditions' => [
              [
                'field' => 'url_alias',
                'value' => $url_alias,
              ],
              [
                'field' => 'redirects',
                'value' => $url_alias,
              ],
            ],
            'operator' => 'OR',
          ],
        ],
        'operator' => 'AND',
      ],
      'limit' => 1,
      'fields' => [
        'include' => [
          'title',
          'body-html',
          'date.original',
          // @todo we propably need to provide our own image_style_downloader to
          // get the image from RW and convert it to the appropriate style.
          'image',
          'file',
          // @todo use it as alternate or canonical URL?
          'url_alias',
        ],
      ],
    ];

    $data = $this->apiClient->request('reports', $payload);
    if (!empty($data['data'])) {
      $this->data = $this->parseApiData($data);
    }
    else {
      $this->throwNotFound();
    }
  }

  /**
   * Get the ReliefWeb URL alias from the given UNOCHA URL.
   *
   * @param string $url
   *   UNOCHA URL. If empty use the current URL.
   *   Ex: https://www.unocha.org/publications/report/france/report-title.
   *
   * @return string
   *   A ReliefWeb URL alias based on the given URL.
   *   Ex: https://reliefweb.int/report/france/report-title.
   */
  protected function getAliasFromUrl($url = '') {
    $url = $url ?: $this->requestStack->getCurrentRequest()->getRequestUri();
    $base_url = $this->config->get('reliefweb_website') ?? 'https://reliefweb.int';
    return preg_replace('#^/publications/#', $base_url . '/', $url);
  }

  /**
   * Get a UNOCHA URL from a ReliefWeb URL alias.
   *
   * @param string $alias
   *   ReliefWeb URL alias.
   *   Ex: https://reliefweb.int/report/france/report-title.
   *
   * @return string
   *   A UNOCHA URL based on the given URL alias.
   *   Ex: https://www.unocha.org/publications/report/france/report-title.
   */
  protected function getUrlFromAlias($alias) {
    $base_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    return preg_replace('#^https?://[^/]+/#', $base_url . '/publications/', $alias);
  }

  /**
   * Parse the ReliefWeb API data.
   *
   * @param array $raw
   *   Raw data from the ReliefWeb API.
   *
   * @return array
   *   The parsed API data.
   */
  protected function parseApiData(array $raw) {
    if (empty($raw['data'][0]['fields'])) {
      return [];
    }

    $data = [];
    $fields = $raw['data'][0]['fields'];
    $unocha_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . '/';

    $data['title'] = $fields['title'];
    $data['date'] = DateHelper::getDateTimeStamp($fields['date']['original']);
    $data['body'] = HtmlSanitizer::sanitize($fields['body-html']);

    if (!empty($fields['file'])) {
      $data['attachments'] = $fields['file'];
      // Change the URLs of the attachment to be an unocha.org URL.
      $this->apiClient::updateApiUrls($data['attachments'], $unocha_url);
    }

    if (!empty($fields['image'])) {
      $data['image'] = $fields['image'];
      // Change the URLs of the attachment to be an unocha.org URL.
      $this->apiClient::updateApiUrls($data['image'], $unocha_url);
    }

    // @todo handle image.
    return $data;
  }

  /**
   * Throw a page not found exception.
   */
  protected function throwNotFound() {
    $max_age = $this->config->get('reliefweb_document_not_found_max_age') ?? 0;
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheMaxAge($max_age);
    throw new CacheableNotFoundHttpException($cache_metadata);
  }

  /**
   * Extract the extension of the file.
   *
   * @param string $file_name
   *   File name.
   *
   * @return string
   *   File extension in lower case.
   */
  protected function extractFileExtension($file_name) {
    if (empty($file_name)) {
      return '';
    }
    return mb_strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
  }

}
