<?php

namespace Drupal\unocha_reliefweb\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\unocha_reliefweb\Helpers\UrlHelper;
use Drupal\unocha_reliefweb\Services\ReliefWebDocuments;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The ReliefWeb API Client.
   *
   * @var \Drupal\unocha_reliefweb\Services\ReliefWebDocuments
   */
  protected $reliefwebDocuments;

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
   * @param \Drupal\unocha_reliefweb\Services\ReliefWebDocuments $reliefweb_documents
   *   The ReliefWeb Documents service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ReliefWebDocuments $reliefweb_documents
  ) {
    $this->config = $config_factory->get('unocha_reliefweb.settings');
    $this->reliefwebDocuments = $reliefweb_documents;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('reliefweb.documents')
    );
  }

  /**
   * Get the page title.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Page title.
   */
  public function getPageTitle() {
    $data = $this->getDocumentData();
    return $data['title'] ?? '';
  }

  /**
   * Get the page content.
   *
   * @return array
   *   Render array.
   */
  public function getPageContent() {
    $data = $this->getDocumentData();
    if (empty($data)) {
      return [];
    }

    $content = [];
    if (!empty($data['attachments'])) {
      $content['attachments'] = $this->renderAttachmentList($data['attachments']);
    }
    if (!empty($data['body-html'])) {
      $content['body'] = ['#markup' => $data['body-html']];
    }

    return [
      '#theme' => 'unocha_reliefweb_document',
      '#title' => $data['title'],
      '#date' => $data['published'],
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
   * Retrieve the ReliefWeb document data.
   */
  protected function getDocumentData() {
    if (!isset($this->data)) {
      // Get the ReliefWeb URL matching the current URL.
      $url = UrlHelper::getReliefWebUrlFromUnochaUrl();

      // Get the data from the API for the document matching this URL.
      $data = $this->getReliefWebDocuments()->getDocumentDataFromUrl('updates', $url);
      $this->data = $data['entity'];
    }

    if (!empty($this->data)) {
      return $this->data;
    }
    else {
      $this->throwNotFound();
    }
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

  /**
   * Get the ReliefWeb Documents service.
   *
   * @return \Drupal\unocha_reliefweb\Services\ReliefWebDocuments
   *   The ReliefWeb docuements service.
   */
  protected function getReliefWebDocuments() {
    return $this->reliefwebDocuments;
  }

}
