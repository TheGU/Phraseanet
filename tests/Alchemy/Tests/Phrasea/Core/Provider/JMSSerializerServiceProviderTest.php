<?php

namespace Alchemy\Tests\Phrasea\Core\Provider;

/**
 * @covers Alchemy\Phrasea\Core\Provider\JMSServiceProviderServiceProvider
 */
class JMSSerializerServiceProviderTest extends ServiceProviderTestCase
{
    public function provideServiceDescription()
    {
        return [
            ['Alchemy\Phrasea\Core\Provider\JMSSerializerServiceProvider', 'serializer', 'JMS\Serializer\Serializer'],
        ];
    }
}
