<?php

declare(strict_types=1);

/*
 * This file is part of the Ivory Google Map bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\GoogleMapBundle\Tests\Templating;

use Ivory\GoogleMap\Helper\ApiHelper as BaseApiHelper;
use Ivory\GoogleMapBundle\Templating\ApiHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiHelperTest extends TestCase
{
    /** @var ApiHelper */
    private $apiHelper;

    /** @var BaseApiHelper|MockObject */
    private $innerApiHelper;

    /** {@inheritdoc} */
    protected function setUp(): void
    {
        $this->innerApiHelper = $this->createApiHelperMock();
        $this->apiHelper = new ApiHelper($this->innerApiHelper);
    }

    public function testRender(): void
    {
        $this->innerApiHelper
            ->expects($this->once())
            ->method('render')
            ->with($this->identicalTo($objects = [new \stdClass()]))
            ->will($this->returnValue($result = 'result'));

        $this->assertSame($result, $this->apiHelper->render($objects));
    }

    public function testName(): void
    {
        $this->assertSame('ivory_google_api', $this->apiHelper->getName());
    }

    /** @return MockObject|BaseApiHelper */
    private function createApiHelperMock()
    {
        return $this->createMock(BaseApiHelper::class);
    }
}