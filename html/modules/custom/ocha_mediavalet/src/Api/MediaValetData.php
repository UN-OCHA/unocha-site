<?php

namespace Drupal\ocha_mediavalet\Api;

/**
 * MediaValet data.
 */
class MediaValetData {

  /**
   * Constructor.
   */
  public function __construct(
    protected array $data,
    protected array $resultInfo,
  ) {
    $resultInfo += [
      'total' => 0,
      'start' => 0,
      'records' => 0,
    ];
  }

  /**
   * Get data.
   */
  public function getData() : array {
    return $this->data;
  }

  /**
   * Get result info.
   */
  public function getResultInfo() : array {
    return $this->resultInfo;
  }

}
