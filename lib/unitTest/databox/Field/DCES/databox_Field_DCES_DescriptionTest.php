<?php

require_once dirname(__FILE__) . '/../../../PhraseanetPHPUnitAbstract.class.inc';

/**
 * Test class for databox_Field_DCES_Description.
 * Generated by PHPUnit on 2011-07-11 at 15:33:40.
 */
class databox_Field_DCES_DescriptionTest extends PhraseanetPHPUnitAbstract
{

  /**
   * @var databox_Field_DCES_Description
   */
  protected $object;

  public function setUp()
  {
    $this->object = new databox_Field_DCES_Description;
  }

  public function testGet_label()
  {
    $name = str_replace('Test', '', array_pop(explode('_', __CLASS__)));
    $this->assertEquals($name, $this->object->get_label());
  }

  public function testGet_definition()
  {
    $this->assertTrue(is_string($this->object->get_definition()));
    $this->assertTrue(strlen($this->object->get_definition()) > 20);
  }

  public function testGet_documentation_link()
  {
    $this->assertRegExp('/^http:\/\/dublincore\.org\/documents\/dces\/#[a-z]+$/', $this->object->get_documentation_link());
  }

}

