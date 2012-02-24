<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2010 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @package
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class patch_3602 implements patchInterface
{

  /**
   *
   * @var string
   */
  private $release = '3.6.0.0.a2';
  /**
   *
   * @var Array
   */
  private $concern = array(base::DATA_BOX);

  /**
   *
   * @return string
   */
  function get_release()
  {
    return $this->release;
  }

  public function require_all_upgrades()
  {
    return true;
  }

  /**
   *
   * @return Array
   */
  function concern()
  {
    return $this->concern;
  }

  function apply(base &$databox)
  {

    $sql = 'ALTER TABLE `metadatas` DROP INDEX `unique`';

    $stmt = $databox->get_connection()->prepare($sql);
    $stmt->execute();
    $stmt->closeCursor();

    return true;
  }

}