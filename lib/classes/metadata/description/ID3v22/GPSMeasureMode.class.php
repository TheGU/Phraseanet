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
class metadata_description_ID3v22_GPSMeasureMode extends metadata_Abstract implements metadata_Interface
{
  const SOURCE = '/rdf:RDF/rdf:Description/ID3v2_2:GPSMeasureMode';
  const NAME_SPACE = 'ID3v2_2';
  const TAGNAME = 'GPSMeasureMode';
  const TYPE = self::TYPE_STRING;

  public static function available_values()
  {
    return array(
        '2' => '2-Dimensional Measurement'
        , '3' => '3-Dimensional Measurement'
    );
  }

}
