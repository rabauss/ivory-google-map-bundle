<?php

declare(strict_types=1);

/*
 * This file is part of the Ivory Google Map package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\GoogleMapBundle\Tests\Helper;

use Ivory\GoogleMap\Helper\ApiHelper;
use Ivory\GoogleMap\Helper\MapHelper;
use Ivory\Tests\GoogleMap\Helper\Functional\MapFunctionalTest as BaseMapFunctionalTest;

/**
 * @group functional
 */
class MapFunctionalTest extends BaseMapFunctionalTest
{
    /** {@inheritdoc} */
    protected function createApiHelper(): ApiHelper
    {
        return HelperFactory::createApiHelper();
    }

    /** {@inheritdoc} */
    protected function createMapHelper(): MapHelper
    {
        return HelperFactory::createMapHelper();
    }
}