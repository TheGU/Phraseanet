<?php

namespace Alchemy\Tests\Phrasea\Border\Checker;

use Alchemy\Phrasea\Border\File;
use Alchemy\Phrasea\Border\Checker\Colorspace;

class ColorspaceTest extends \PhraseanetTestCase
{
    /**
     * @var Colorspace
     */
    protected $object;

    /**
     * @covers Alchemy\Phrasea\Border\Checker\CheckerInterface
     * @covers Alchemy\Phrasea\Border\Checker\Colorspace::__construct
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = new Colorspace(self::$DI['app'], ['colorspaces' => ['RGB', 'cmyk']]);
    }

    /**
     * @covers Alchemy\Phrasea\Border\Checker\Colorspace::check
     */
    public function testCheck()
    {
        $media = $this
            ->getMockBuilder('\\MediaVorus\\Media\\Image')
            ->disableOriginalConstructor()
            ->getMock();
        $media->expects($this->once())
            ->method('getColorSpace')
            ->will($this->returnValue('RGB'));
        $media->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue(new \SplFileInfo(__FILE__)));

        $File = new File(self::$DI['app'], $media, self::$DI['collection']);

        $response = $this->object->check(self::$DI['app']['orm.em'], $File);

        $this->assertInstanceOf('\\Alchemy\\Phrasea\\Border\\Checker\\Response', $response);
        $this->assertTrue($response->isOk());
    }

    /**
     * @covers Alchemy\Phrasea\Border\Checker\Colorspace::getMessage
     */
    public function testGetMessage()
    {
        $this->assertInternalType('string', $this->object->getMessage($this->createTranslatorMock()));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testContructorInvalidArgumentException()
    {
        new Colorspace(self::$DI['app'], [['RGB', 'cmyk']]);
    }
}
