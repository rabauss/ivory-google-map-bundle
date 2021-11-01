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

namespace Ivory\GoogleMapBundle\Tests\DependencyInjection;

use Exception;
use Ivory\GoogleMap\Helper\ApiHelper;
use Ivory\GoogleMap\Helper\MapHelper;
use Ivory\GoogleMap\Helper\PlaceAutocompleteHelper;
use Ivory\GoogleMap\Helper\StaticMapHelper;
use Ivory\GoogleMap\Service\Direction\DirectionService;
use Ivory\GoogleMap\Service\DistanceMatrix\DistanceMatrixService;
use Ivory\GoogleMap\Service\Elevation\ElevationService;
use Ivory\GoogleMap\Service\Geocoder\GeocoderService;
use Ivory\GoogleMap\Service\Place\Autocomplete\PlaceAutocompleteService;
use Ivory\GoogleMap\Service\Place\Detail\PlaceDetailService;
use Ivory\GoogleMap\Service\Place\Photo\PlacePhotoService;
use Ivory\GoogleMap\Service\Place\Search\PlaceSearchService;
use Ivory\GoogleMap\Service\TimeZone\TimeZoneService;
use Ivory\GoogleMapBundle\DependencyInjection\IvoryGoogleMapExtension;
use Ivory\GoogleMapBundle\IvoryGoogleMapBundle;
use Ivory\Serializer\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

abstract class AbstractIvoryGoogleMapExtensionTest extends TestCase
{
    /** @var ContainerBuilder */
    private $container;

    /** @var bool */
    private $debug;

    /** @var string */
    private $locale;

    /** @var ClientInterface|Stub */
    private $client;

    /** @var RequestFactoryInterface|MockObject */
    private $messageFactory;

    /** @var SerializerInterface|MockObject */
    private $serializer;

    /** {@inheritdoc} */
    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.root_dir', __DIR__.'/../..');
        $this->container->setParameter('kernel.debug', $this->debug = false);
        $this->container->setParameter('kernel.default_locale', $this->locale = 'en');
        $this->container->set('httplug.client', $this->client = $this->createClientMock());
        $this->container->set('httplug.message_factory', $this->messageFactory = $this->createMessageFactoryMock());
        $this->container->set('ivory.serializer', $this->serializer = $this->createSerializerMock());
        $this->container->registerExtension($extension = new IvoryGoogleMapExtension());
        $this->container->loadFromExtension($extension->getAlias());
        (new IvoryGoogleMapBundle())->build($this->container);
    }

    abstract protected function loadConfiguration(ContainerBuilder $container, string $configuration);

    /** @throws Exception */
    public function testDefaultState(): void
    {
        $this->container->compile();

        $apiHelper = $this->container->get('ivory.google_map.helper.api');
        $mapHelper = $this->container->get('ivory.google_map.helper.map');
        $staticMapHelper = $this->container->get('ivory.google_map.helper.map.static');
        $placeAutocompleteHelper = $this->container->get('ivory.google_map.helper.place_autocomplete');

        $this->assertInstanceOf(ApiHelper::class, $apiHelper);
        $this->assertInstanceOf(MapHelper::class, $mapHelper);
        $this->assertInstanceOf(StaticMapHelper::class, $staticMapHelper);
        $this->assertInstanceOf(PlaceAutocompleteHelper::class, $placeAutocompleteHelper);

        $formatter = $this->container->get('ivory.google_map.helper.formatter');
        $loaderRenderer = $this->container->get('ivory.google_map.helper.renderer.loader');

        $this->assertSame($this->debug, $formatter->isDebug());
        $this->assertSame($this->locale, $loaderRenderer->getLanguage());
        $this->assertFalse($loaderRenderer->hasKey());

        $this->assertTrue($this->container->get('ivory.google_map.helper.renderer.control.manager')->hasRenderers());
        $this->assertTrue($this->container->get('ivory.google_map.helper.renderer.overlay.extendable')->hasRenderers());

        $this->assertTrue($this->container->get('ivory.google_map.helper.api.event_dispatcher')->hasListeners());
        $this->assertTrue($this->container->get('ivory.google_map.helper.map.event_dispatcher')->hasListeners());
        $this->assertTrue($this->container->get('ivory.google_map.helper.map.static.event_dispatcher')->hasListeners());
        $this->assertTrue(
            $this->container->get('ivory.google_map.helper.place_autocomplete.event_dispatcher')->hasListeners()
        );

        $this->assertFalse($this->container->has('ivory.google_map.direction'));
        $this->assertFalse($this->container->has('ivory.google_map.distance_matrix'));
        $this->assertFalse($this->container->has('ivory.google_map.elevation'));
        $this->assertFalse($this->container->has('ivory.google_map.geocoder'));
        $this->assertFalse($this->container->has('ivory.google_map.time_zone'));

        $this->assertFalse($this->container->has('ivory.google_map.templating.api'));
        $this->assertFalse($this->container->has('ivory.google_map.templating.map'));
        $this->assertFalse($this->container->has('ivory.google_map.templating.place_autocomplete'));

        $this->assertFalse($this->container->has('ivory.google_map.twig.extension.api'));
        $this->assertFalse($this->container->has('ivory.google_map.twig.extension.map'));
        $this->assertFalse($this->container->has('ivory.google_map.twig.extension.place_autocomplete'));
    }

    public function testTemplatingHelpers(): void
    {
        $this->container->setDefinition('templating.engine.php', new Definition(stdClass::class));
        $this->container->compile();

        $this->assertTrue($this->container->has('ivory.google_map.templating.api'));
        $this->assertTrue($this->container->has('ivory.google_map.templating.map'));
        $this->assertTrue($this->container->has('ivory.google_map.templating.map.static'));
        $this->assertTrue($this->container->has('ivory.google_map.templating.place_autocomplete'));
    }

    public function testTwigExtensions(): void
    {
        $this->container->setDefinition('twig', new Definition(stdClass::class));
        $this->container->compile();

        $this->assertTrue($this->container->has('ivory.google_map.twig.extension.api'));
        $this->assertTrue($this->container->has('ivory.google_map.twig.extension.map'));
        $this->assertTrue($this->container->has('ivory.google_map.twig.extension.map.static'));
        $this->assertTrue($this->container->has('ivory.google_map.twig.extension.place_autocomplete'));
    }

    public function testTemplatingFormResources(): void
    {
        $this->container->setParameter($parameter = 'templating.helper.form.resources', $resources = ['resource']);
        $this->container->compile();

        $this->assertSame(
            array_merge(['@IvoryGoogleMapBundle/Form'], $resources),
            $this->container->getParameter($parameter)
        );
    }

    public function testTwigFormResources(): void
    {
        $this->container->setParameter($parameter = 'twig.form.resources', $resources = ['resource']);
        $this->container->compile();

        $this->assertSame(
            array_merge(['@IvoryGoogleMap/Form/place_autocomplete_widget.html.twig'], $resources),
            $this->container->getParameter($parameter)
        );
    }

    /** @throws Exception */
    public function testMapDebug(): void
    {
        $this->loadConfiguration($this->container, 'map_debug');
        $this->container->compile();

        $this->assertTrue($this->container->get('ivory.google_map.helper.formatter')->isDebug());
    }

    /** @throws Exception */
    public function testMapLanguage(): void
    {
        $this->loadConfiguration($this->container, 'map_language');
        $this->container->compile();

        $this->assertSame('fr', $this->container->get('ivory.google_map.helper.renderer.loader')->getLanguage());
    }

    /** @throws Exception */
    public function testMapApiKey(): void
    {
        $this->loadConfiguration($this->container, 'map_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.helper.renderer.loader')->getKey());
    }

    public function testStaticMapApiKey(): void
    {
        $this->loadConfiguration($this->container, 'static_map_api_key');
        $this->container->compile();

        $this->assertSame(
            'key',
            $this->container->getDefinition('ivory.google_map.helper.subscriber.static.key')->getArgument(0)
        );
    }

    /** @throws Exception */
    public function testStaticMapApiSecret(): void
    {
        $this->loadConfiguration($this->container, 'static_map_api_secret');
        $this->container->compile();

        $staticMapHelper = $this->container->get('ivory.google_map.helper.map.static');

        $this->assertSame('my-secret', $staticMapHelper->getSecret());
        $this->assertFalse($staticMapHelper->hasClientId());
        $this->assertFalse($staticMapHelper->hasChannel());
    }

    /** @throws Exception */
    public function testStaticMapBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'static_map_business_account');
        $this->container->compile();

        $staticMapHelper = $this->container->get('ivory.google_map.helper.map.static');

        $this->assertSame('my-client', $staticMapHelper->getClientId());
        $this->assertSame('my-secret', $staticMapHelper->getSecret());
        $this->assertFalse($staticMapHelper->hasChannel());
    }

    /** @throws Exception */
    public function testStaticMapBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'static_map_business_account_channel');
        $this->container->compile();

        $staticMapHelper = $this->container->get('ivory.google_map.helper.map.static');

        $this->assertSame('my-client', $staticMapHelper->getClientId());
        $this->assertSame('my-secret', $staticMapHelper->getSecret());
        $this->assertSame('my-channel', $staticMapHelper->getChannel());
    }

    public function testStaticMapBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'static_map_business_account_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testDirection(): void
    {
        $this->loadConfiguration($this->container, 'direction');
        $this->container->compile();

        $direction = $this->container->get('ivory.google_map.direction');

        $this->assertInstanceOf(DirectionService::class, $direction);
        $this->assertSame($this->client, $direction->getClient());
        $this->assertSame($this->messageFactory, $direction->getMessageFactory());
        $this->assertSame($this->serializer, $direction->getSerializer());
        $this->assertSame(DirectionService::FORMAT_JSON, $direction->getFormat());
        $this->assertFalse($direction->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testDirectionFormat(): void
    {
        $this->loadConfiguration($this->container, 'direction_format');
        $this->container->compile();

        $this->assertSame(DirectionService::FORMAT_XML, $this->container->get('ivory.google_map.direction')->getFormat());
    }

    /** @throws Exception */
    public function testDirectionApiKey(): void
    {
        $this->loadConfiguration($this->container, 'direction_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.direction')->getKey());
    }

    /** @throws Exception */
    public function testDirectionBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'direction_business_account');
        $this->container->compile();

        $direction = $this->container->get('ivory.google_map.direction');

        $this->assertTrue($direction->hasBusinessAccount());
        $this->assertSame('my-client', $direction->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $direction->getBusinessAccount()->getSecret());
        $this->assertFalse($direction->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testDirectionBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'direction_business_account_channel');
        $this->container->compile();

        $direction = $this->container->get('ivory.google_map.direction');

        $this->assertTrue($direction->hasBusinessAccount());
        $this->assertSame('my-client', $direction->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $direction->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $direction->getBusinessAccount()->getChannel());
    }

    public function testDirectionBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'direction_business_account_invalid');
        $this->container->compile();
    }

    public function testDirectionInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'direction_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testDistanceMatrix(): void
    {
        $this->loadConfiguration($this->container, 'distance_matrix');
        $this->container->compile();

        $distanceMatrix = $this->container->get('ivory.google_map.distance_matrix');

        $this->assertInstanceOf(DistanceMatrixService::class, $distanceMatrix);
        $this->assertSame($this->client, $distanceMatrix->getClient());
        $this->assertSame($this->messageFactory, $distanceMatrix->getMessageFactory());
        $this->assertSame($this->serializer, $distanceMatrix->getSerializer());
        $this->assertSame(DistanceMatrixService::FORMAT_JSON, $distanceMatrix->getFormat());
        $this->assertFalse($distanceMatrix->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testDistanceMatrixFormat(): void
    {
        $this->loadConfiguration($this->container, 'distance_matrix_format');
        $this->container->compile();

        $this->assertSame(
            DistanceMatrixService::FORMAT_XML,
            $this->container->get('ivory.google_map.distance_matrix')->getFormat()
        );
    }

    /** @throws Exception */
    public function testDistanceMatrixApiKey(): void
    {
        $this->loadConfiguration($this->container, 'distance_matrix_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.distance_matrix')->getKey());
    }

    /** @throws Exception */
    public function testDistanceMatrixBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'distance_matrix_business_account');
        $this->container->compile();

        $distanceMatrix = $this->container->get('ivory.google_map.distance_matrix');

        $this->assertTrue($distanceMatrix->hasBusinessAccount());
        $this->assertSame('my-client', $distanceMatrix->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $distanceMatrix->getBusinessAccount()->getSecret());
        $this->assertFalse($distanceMatrix->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testDistanceMatrixBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'distance_matrix_business_account_channel');
        $this->container->compile();

        $distanceMatrix = $this->container->get('ivory.google_map.distance_matrix');

        $this->assertTrue($distanceMatrix->hasBusinessAccount());
        $this->assertSame('my-client', $distanceMatrix->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $distanceMatrix->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $distanceMatrix->getBusinessAccount()->getChannel());
    }

    public function testDistanceMatrixBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'distance_matrix_business_account_invalid');
        $this->container->compile();
    }

    public function testDistanceMatrixInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'distance_matrix_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testElevation(): void
    {
        $this->loadConfiguration($this->container, 'elevation');
        $this->container->compile();

        $elevation = $this->container->get('ivory.google_map.elevation');

        $this->assertInstanceOf(ElevationService::class, $elevation);
        $this->assertSame($this->client, $elevation->getClient());
        $this->assertSame($this->messageFactory, $elevation->getMessageFactory());
        $this->assertSame($this->serializer, $elevation->getSerializer());
        $this->assertSame(ElevationService::FORMAT_JSON, $elevation->getFormat());
        $this->assertFalse($elevation->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testElevationFormat(): void
    {
        $this->loadConfiguration($this->container, 'elevation_format');
        $this->container->compile();

        $this->assertSame(ElevationService::FORMAT_XML, $this->container->get('ivory.google_map.elevation')->getFormat());
    }

    /** @throws Exception */
    public function testElevationApiKey(): void
    {
        $this->loadConfiguration($this->container, 'elevation_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.elevation')->getKey());
    }

    /** @throws Exception */
    public function testElevationBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'elevation_business_account');
        $this->container->compile();

        $elevation = $this->container->get('ivory.google_map.elevation');

        $this->assertTrue($elevation->hasBusinessAccount());
        $this->assertSame('my-client', $elevation->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $elevation->getBusinessAccount()->getSecret());
        $this->assertFalse($elevation->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testElevationBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'elevation_business_account_channel');
        $this->container->compile();

        $elevation = $this->container->get('ivory.google_map.elevation');

        $this->assertTrue($elevation->hasBusinessAccount());
        $this->assertSame('my-client', $elevation->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $elevation->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $elevation->getBusinessAccount()->getChannel());
    }

    public function testElevationBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'elevation_business_account_invalid');
        $this->container->compile();
    }

    public function testElevationInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'elevation_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testGeocoder(): void
    {
        $this->loadConfiguration($this->container, 'geocoder');
        $this->container->compile();

        $geocoder = $this->container->get('ivory.google_map.geocoder');

        $this->assertInstanceOf(GeocoderService::class, $geocoder);
        $this->assertSame($this->client, $geocoder->getClient());
        $this->assertSame($this->messageFactory, $geocoder->getMessageFactory());
        $this->assertSame($this->serializer, $geocoder->getSerializer());
        $this->assertSame(GeocoderService::FORMAT_JSON, $geocoder->getFormat());
        $this->assertFalse($geocoder->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testGeocoderFormat(): void
    {
        $this->loadConfiguration($this->container, 'geocoder_format');
        $this->container->compile();

        $this->assertSame(
            GeocoderService::FORMAT_XML,
            $this->container->get('ivory.google_map.geocoder')->getFormat()
        );
    }

    /** @throws Exception */
    public function testGeocoderApiKey(): void
    {
        $this->loadConfiguration($this->container, 'geocoder_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.geocoder')->getKey());
    }

    /** @throws Exception */
    public function testGeocoderBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'geocoder_business_account');
        $this->container->compile();

        $geocoder = $this->container->get('ivory.google_map.geocoder');

        $this->assertTrue($geocoder->hasBusinessAccount());
        $this->assertSame('my-client', $geocoder->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $geocoder->getBusinessAccount()->getSecret());
        $this->assertFalse($geocoder->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testGeocoderBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'geocoder_business_account_channel');
        $this->container->compile();

        $geocoder = $this->container->get('ivory.google_map.geocoder');

        $this->assertTrue($geocoder->hasBusinessAccount());
        $this->assertSame('my-client', $geocoder->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $geocoder->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $geocoder->getBusinessAccount()->getChannel());
    }

    public function testGeocoderBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'geocoder_business_account_invalid');
        $this->container->compile();
    }

    public function testGeocoderInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'geocoder_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testPlaceAutocomplete(): void
    {
        $this->loadConfiguration($this->container, 'place_autocomplete');
        $this->container->compile();

        $placeAutocomplete = $this->container->get('ivory.google_map.place_autocomplete');

        $this->assertInstanceOf(PlaceAutocompleteService::class, $placeAutocomplete);
        $this->assertSame($this->client, $placeAutocomplete->getClient());
        $this->assertSame($this->messageFactory, $placeAutocomplete->getMessageFactory());
        $this->assertSame($this->serializer, $placeAutocomplete->getSerializer());
        $this->assertSame(PlaceAutocompleteService::FORMAT_JSON, $placeAutocomplete->getFormat());
        $this->assertFalse($placeAutocomplete->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testPlaceAutocompleteFormat(): void
    {
        $this->loadConfiguration($this->container, 'place_autocomplete_format');
        $this->container->compile();

        $this->assertSame(
            PlaceAutocompleteService::FORMAT_XML,
            $this->container->get('ivory.google_map.place_autocomplete')->getFormat()
        );
    }

    /** @throws Exception */
    public function testPlaceAutocompleteApiKey(): void
    {
        $this->loadConfiguration($this->container, 'place_autocomplete_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.place_autocomplete')->getKey());
    }

    /** @throws Exception */
    public function testPlaceAutocompleteBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'place_autocomplete_business_account');
        $this->container->compile();

        $placeAutocomplete = $this->container->get('ivory.google_map.place_autocomplete');

        $this->assertTrue($placeAutocomplete->hasBusinessAccount());
        $this->assertSame('my-client', $placeAutocomplete->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placeAutocomplete->getBusinessAccount()->getSecret());
        $this->assertFalse($placeAutocomplete->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testPlaceAutocompleteBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'place_autocomplete_business_account_channel');
        $this->container->compile();

        $placeAutocomplete = $this->container->get('ivory.google_map.place_autocomplete');

        $this->assertTrue($placeAutocomplete->hasBusinessAccount());
        $this->assertSame('my-client', $placeAutocomplete->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placeAutocomplete->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $placeAutocomplete->getBusinessAccount()->getChannel());
    }

    public function testPlaceAutocompleteBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_autocomplete_business_account_invalid');
        $this->container->compile();
    }

    public function testPlaceAutocompleteInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_autocomplete_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testPlaceDetail(): void
    {
        $this->loadConfiguration($this->container, 'place_detail');
        $this->container->compile();

        $placeDetail = $this->container->get('ivory.google_map.place_detail');

        $this->assertInstanceOf(PlaceDetailService::class, $placeDetail);
        $this->assertSame($this->client, $placeDetail->getClient());
        $this->assertSame($this->messageFactory, $placeDetail->getMessageFactory());
        $this->assertSame($this->serializer, $placeDetail->getSerializer());
        $this->assertSame(PlaceDetailService::FORMAT_JSON, $placeDetail->getFormat());
        $this->assertFalse($placeDetail->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testPlaceDetailFormat(): void
    {
        $this->loadConfiguration($this->container, 'place_detail_format');
        $this->container->compile();

        $this->assertSame(
            PlaceDetailService::FORMAT_XML,
            $this->container->get('ivory.google_map.place_detail')->getFormat()
        );
    }

    /** @throws Exception */
    public function testPlaceDetailApiKey(): void
    {
        $this->loadConfiguration($this->container, 'place_detail_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.place_detail')->getKey());
    }

    /** @throws Exception */
    public function testPlaceDetailBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'place_detail_business_account');
        $this->container->compile();

        $placeDetail = $this->container->get('ivory.google_map.place_detail');

        $this->assertTrue($placeDetail->hasBusinessAccount());
        $this->assertSame('my-client', $placeDetail->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placeDetail->getBusinessAccount()->getSecret());
        $this->assertFalse($placeDetail->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testPlaceDetailBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'place_detail_business_account_channel');
        $this->container->compile();

        $placeDetail = $this->container->get('ivory.google_map.place_detail');

        $this->assertTrue($placeDetail->hasBusinessAccount());
        $this->assertSame('my-client', $placeDetail->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placeDetail->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $placeDetail->getBusinessAccount()->getChannel());
    }

    public function testPlaceDetailBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_detail_business_account_invalid');
        $this->container->compile();
    }

    public function testPlaceDetailInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_detail_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testPlacePhoto(): void
    {
        $this->loadConfiguration($this->container, 'place_photo');
        $this->container->compile();

        $placePhoto = $this->container->get('ivory.google_map.place_photo');

        $this->assertInstanceOf(PlacePhotoService::class, $placePhoto);
        $this->assertFalse($placePhoto->hasKey());
        $this->assertFalse($placePhoto->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testPlacePhotoApiKey(): void
    {
        $this->loadConfiguration($this->container, 'place_photo_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.place_photo')->getKey());
    }

    /** @throws Exception */
    public function testPlacePhotoBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'place_photo_business_account');
        $this->container->compile();

        $placePhoto = $this->container->get('ivory.google_map.place_photo');

        $this->assertTrue($placePhoto->hasBusinessAccount());
        $this->assertSame('my-client', $placePhoto->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placePhoto->getBusinessAccount()->getSecret());
        $this->assertFalse($placePhoto->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testPlacePhotoBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'place_photo_business_account_channel');
        $this->container->compile();

        $placePhoto = $this->container->get('ivory.google_map.place_photo');

        $this->assertTrue($placePhoto->hasBusinessAccount());
        $this->assertSame('my-client', $placePhoto->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placePhoto->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $placePhoto->getBusinessAccount()->getChannel());
    }

    public function testPlacePhotoBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_photo_business_account_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testPlaceSearch(): void
    {
        $this->loadConfiguration($this->container, 'place_search');
        $this->container->compile();

        $placeSearch = $this->container->get('ivory.google_map.place_search');

        $this->assertInstanceOf(PlaceSearchService::class, $placeSearch);
        $this->assertSame($this->client, $placeSearch->getClient());
        $this->assertSame($this->messageFactory, $placeSearch->getMessageFactory());
        $this->assertSame($this->serializer, $placeSearch->getSerializer());
        $this->assertSame(PlaceSearchService::FORMAT_JSON, $placeSearch->getFormat());
        $this->assertFalse($placeSearch->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testPlaceSearchFormat(): void
    {
        $this->loadConfiguration($this->container, 'place_search_format');
        $this->container->compile();

        $this->assertSame(
            PlaceSearchService::FORMAT_XML,
            $this->container->get('ivory.google_map.place_search')->getFormat()
        );
    }

    /** @throws Exception */
    public function testPlaceSearchApiKey(): void
    {
        $this->loadConfiguration($this->container, 'place_search_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.place_search')->getKey());
    }

    /** @throws Exception */
    public function testPlaceSearchBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'place_search_business_account');
        $this->container->compile();

        $placeSearch = $this->container->get('ivory.google_map.place_search');

        $this->assertTrue($placeSearch->hasBusinessAccount());
        $this->assertSame('my-client', $placeSearch->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placeSearch->getBusinessAccount()->getSecret());
        $this->assertFalse($placeSearch->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testPlaceSearchBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'place_search_business_account_channel');
        $this->container->compile();

        $placeSearch = $this->container->get('ivory.google_map.place_search');

        $this->assertTrue($placeSearch->hasBusinessAccount());
        $this->assertSame('my-client', $placeSearch->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $placeSearch->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $placeSearch->getBusinessAccount()->getChannel());
    }

    public function testPlaceSearchBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_search_business_account_invalid');
        $this->container->compile();
    }

    public function testPlaceSearchInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'place_search_invalid');
        $this->container->compile();
    }

    /** @throws Exception */
    public function testTimeZone(): void
    {
        $this->loadConfiguration($this->container, 'time_zone');
        $this->container->compile();

        $timeZone = $this->container->get('ivory.google_map.time_zone');

        $this->assertInstanceOf(TimeZoneService::class, $timeZone);
        $this->assertSame($this->client, $timeZone->getClient());
        $this->assertSame($this->messageFactory, $timeZone->getMessageFactory());
        $this->assertSame($this->serializer, $timeZone->getSerializer());
        $this->assertSame(TimeZoneService::FORMAT_JSON, $timeZone->getFormat());
        $this->assertFalse($timeZone->hasBusinessAccount());
    }

    /** @throws Exception */
    public function testTimeZoneFormat(): void
    {
        $this->loadConfiguration($this->container, 'time_zone_format');
        $this->container->compile();

        $this->assertSame(TimeZoneService::FORMAT_XML, $this->container->get('ivory.google_map.time_zone')->getFormat());
    }

    /** @throws Exception */
    public function testTimeZoneApiKey(): void
    {
        $this->loadConfiguration($this->container, 'time_zone_api_key');
        $this->container->compile();

        $this->assertSame('key', $this->container->get('ivory.google_map.time_zone')->getKey());
    }

    /** @throws Exception */
    public function testTimeZoneBusinessAccount(): void
    {
        $this->loadConfiguration($this->container, 'time_zone_business_account');
        $this->container->compile();

        $timeZone = $this->container->get('ivory.google_map.time_zone');

        $this->assertTrue($timeZone->hasBusinessAccount());
        $this->assertSame('my-client', $timeZone->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $timeZone->getBusinessAccount()->getSecret());
        $this->assertFalse($timeZone->getBusinessAccount()->hasChannel());
    }

    /** @throws Exception */
    public function testTimeZoneBusinessAccountChannel(): void
    {
        $this->loadConfiguration($this->container, 'time_zone_business_account_channel');
        $this->container->compile();

        $timeZone = $this->container->get('ivory.google_map.time_zone');

        $this->assertTrue($timeZone->hasBusinessAccount());
        $this->assertSame('my-client', $timeZone->getBusinessAccount()->getClientId());
        $this->assertSame('my-secret', $timeZone->getBusinessAccount()->getSecret());
        $this->assertSame('my-channel', $timeZone->getBusinessAccount()->getChannel());
    }

    public function testTimeZoneBusinessAccountInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'time_zone_business_account_invalid');
        $this->container->compile();
    }

    public function testTimeZoneInvalid(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->loadConfiguration($this->container, 'time_zone_invalid');
        $this->container->compile();
    }

    public function testMissingExtendableRendererClassTagAttribute(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No "class" attribute found for the tag "ivory.google_map.helper.renderer.extendable" on the service "acme.map.helper.renderer.extendable".');
        $this->loadConfiguration($this->container, 'extendable');
        $this->container->compile();
    }

    /** @return MockObject|ClientInterface */
    private function createClientMock()
    {
        return $this->createMock(ClientInterface::class);
    }

    /** @return MockObject|RequestFactoryInterface */
    private function createMessageFactoryMock()
    {
        return $this->createMock(RequestFactoryInterface::class);
    }

    /** @return MockObject|SerializerInterface */
    private function createSerializerMock()
    {
        return $this->createMock(SerializerInterface::class);
    }
}