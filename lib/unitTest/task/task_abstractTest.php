<?php

require_once dirname(__FILE__) . '/../PhraseanetPHPUnitAbstract.class.inc';

/**
 * Test class for task_abstract.
 * Generated by PHPUnit on 2011-06-20 at 12:59:22.
 */
class task_abstractTest extends PhraseanetPHPUnitAbstract
{

  public function testCreate()
  {
    $appbox = appbox::get_instance();

    $task = task_abstract::create($appbox, 'task_period_apibridge');
    $task->delete();
  }

}

