<?php

namespace Drupal\Tests\unocha_demo_content\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * A model test case using traits from Drupal Test Traits.
 */
class HomePageTest extends ExistingSiteBase {

  /**
   * Test home page blocks.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testHomePageBlocks() {
    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(404);
  }

}
