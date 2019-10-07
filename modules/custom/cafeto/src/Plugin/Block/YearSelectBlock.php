<?php

namespace Drupal\cafeto\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block to display a calendar with events.
 *
 * @Block(
 *   id = "cafeto_year_select",
 *   admin_label = @Translation("Year select"),
 *   category = @Translation("Cafeto"),
 * )
 */
class YearSelectBlock extends BlockBase {

  /**
   * @inheritdoc
   */
  public function build() {
    $years = [];
    for ($year = 2010; $year <= 2019; $year += 1) {
      $years[$year] = $year;
    }
    return [
      '#theme' => 'cafeto_year_select',
      '#years' => $years,
      '#attached' => [
        'library' => [
          'cafeto/cafeto.select',
        ],
      ],
    ];
  }

}
