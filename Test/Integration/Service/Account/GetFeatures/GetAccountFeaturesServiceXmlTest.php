<?php

declare(strict_types=1);

namespace Klevu\ApiRequest\Test\Integration\Service\Account\GetFeatures;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use Klevu\Search\Api\Service\Account\Model\AccountFeaturesInterface;
use Klevu\Search\Service\Account\GetFeatures;
use Klevu\Search\Service\Account\Model\AccountFeatures;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\ClientFactory as HttpClientFactory;
use Laminas\Http\Response as HttpResponse;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class GetAccountFeaturesServiceXmlTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var HttpClient
     */
    private $httpClientMock;

    /**
     * @var string
     */
    private $httpClientCurrentEndpoint;

    /**
     * @var HttpResponse[]
     */
    private $httpClientResponseMock = [];

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadStoreFixtures
     * @magentoConfigFixture es_es_store klevu_search/general/js_api_key klevu-1234567890
     * @magentoConfigFixture es_es_store klevu_search/general/rest_api_key ABCDE1234567890
     * @magentoConfigFixture es_es_store klevu_search/features_api/last_sync_date 0
     * @magentoConfigFixture es_es_store klevu_search/general/tiers_url tiers.klevu.com
     */
    public function testExecute_MappedV2FieldsOverrideV1Values_Disabled(): void
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeaturesAndUpgradeLink']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <upgradeUrl>https://box.klevu.com/analytics/km</upgradeUrl>
    <upgradeMessage>UPGRADE MESSAGE</upgradeMessage>
    <preserveLayoutMessage>PRESERVE LAYOUT MESSAGE</preserveLayoutMessage>
    <enabled>enabledaddtocartfront,boosting,enabledcmsfront,allowgroupprices,enabledcategorynavigation,enabledrecommendations,preserves_layout</enabled>
    <disabled>enabledpopulartermfront</disabled>
    <userPlanForStore>Enterprise</userPlanForStore>
    <response>success</response>
</data>
XML
            );
        // phpcs:enable Generic.Files.LineLength.TooLong

        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeatureValues']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <feature>
        <key>s.enablecategorynavigation</key>
        <value>no</value>
    </feature>
    <feature>
        <key>allow.personalizedrecommendations</key>
        <value>no</value>
    </feature>
    <feature>
        <key>s.preservedlayout</key>
        <value>no</value>
    </feature>
</data>
XML
            );

        /** @var GetFeatures $getFeaturesService */
        $getFeaturesService = $this->objectManager->get(GetFeatures::class);

        $result = $getFeaturesService->execute('es_es');

        $this->assertInstanceOf(AccountFeaturesInterface::class, $result);

        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION),
            'Feature Enabled: CatNav'
        );
        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS),
            'Feature Enabled: RECs'
        );
        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT),
            'Feature Enabled: PL'
        );

        $enabledFeatures = $result->getEnabledFeatures();
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION, $enabledFeatures);
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS, $enabledFeatures);
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT, $enabledFeatures);

        $disabledFeatures = $result->getDisabledFeatures();
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION, $disabledFeatures);
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS, $disabledFeatures);
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT, $disabledFeatures);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadStoreFixtures
     * @magentoConfigFixture es_es_store klevu_search/general/js_api_key klevu-1234567890
     * @magentoConfigFixture es_es_store klevu_search/general/rest_api_key ABCDE1234567890
     * @magentoConfigFixture es_es_store klevu_search/features_api/last_sync_date 0
     * @magentoConfigFixture es_es_store klevu_search/general/tiers_url tiers.klevu.com
     */
    public function testExecute_MappedV2FieldsOverrideV1Values_Enabled(): void
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeaturesAndUpgradeLink']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <upgradeUrl>https://box.klevu.com/analytics/km</upgradeUrl>
    <upgradeMessage>UPGRADE MESSAGE</upgradeMessage>
    <preserveLayoutMessage>PRESERVE LAYOUT MESSAGE</preserveLayoutMessage>
    <enabled>enabledaddtocartfront,boosting,enabledcmsfront,enabledcategorynavigation,allowgroupprices,enabledcategorynavigation,enabledrecommendations,preserves_layout</enabled>
    <disabled>enabledpopulartermfront</disabled>
    <userPlanForStore>Enterprise</userPlanForStore>
    <response>success</response>
</data>
XML
            );
        // phpcs:enable Generic.Files.LineLength.TooLong

        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeatureValues']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <feature>
        <key>s.enablecategorynavigation</key>
        <value>yes</value>
    </feature>
    <feature>
        <key>allow.personalizedrecommendations</key>
        <value>yes</value>
    </feature>
    <feature>
        <key>s.preservedlayout</key>
        <value>yes</value>
    </feature>
</data>
XML
            );

        /** @var GetFeatures $getFeaturesService */
        $getFeaturesService = $this->objectManager->get(GetFeatures::class);

        $result = $getFeaturesService->execute('es_es');

        $this->assertInstanceOf(AccountFeaturesInterface::class, $result);

        $this->assertTrue(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION),
            'Feature Enabled: CatNav'
        );
        $this->assertTrue(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS),
            'Feature Enabled: RECs'
        );
        $this->assertTrue(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT),
            'Feature Enabled: PL'
        );

        $enabledFeatures = $result->getEnabledFeatures();
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION, $enabledFeatures);
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS, $enabledFeatures);
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT, $enabledFeatures);

        $disabledFeatures = $result->getDisabledFeatures();
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION, $disabledFeatures);
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS, $disabledFeatures);
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT, $disabledFeatures);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadStoreFixtures
     * @magentoConfigFixture es_es_store klevu_search/general/js_api_key klevu-1234567890
     * @magentoConfigFixture es_es_store klevu_search/general/rest_api_key ABCDE1234567890
     * @magentoConfigFixture es_es_store klevu_search/features_api/last_sync_date 0
     * @magentoConfigFixture es_es_store klevu_search/general/tiers_url tiers.klevu.com
     * @depends testExecute_MappedV2FieldsOverrideV1Values_Enabled
     */
    public function testExecute_MappedV2Fields_InvalidReturnFlagValue(): void
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeaturesAndUpgradeLink']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <upgradeUrl>https://box.klevu.com/analytics/km</upgradeUrl>
    <upgradeMessage>UPGRADE MESSAGE</upgradeMessage>
    <preserveLayoutMessage>PRESERVE LAYOUT MESSAGE</preserveLayoutMessage>
    <enabled>enabledaddtocartfront,boosting,enabledcmsfront,enabledcategorynavigation,allowgroupprices,enabledcategorynavigation,enabledrecommendations,preserves_layout</enabled>
    <disabled>enabledpopulartermfront</disabled>
    <userPlanForStore>Enterprise</userPlanForStore>
    <response>success</response>
</data>
XML
            );
        // phpcs:enable Generic.Files.LineLength.TooLong

        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeatureValues']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <feature>
        <key>s.enablecategorynavigation</key>
        <value>enabled</value>
    </feature>
    <feature>
        <key>allow.personalizedrecommendations</key>
        <value>enabled</value>
    </feature>
    <feature>
        <key>s.preservedlayout</key>
        <value>enabled</value>
    </feature>
</data>
XML
            );

        /** @var GetFeatures $getFeaturesService */
        $getFeaturesService = $this->objectManager->get(GetFeatures::class);

        $result = $getFeaturesService->execute('es_es');

        $this->assertInstanceOf(AccountFeaturesInterface::class, $result);

        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION),
            'Feature Enabled: CatNav'
        );
        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS),
            'Feature Enabled: RECs'
        );
        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT),
            'Feature Enabled: PL'
        );

        $enabledFeatures = $result->getEnabledFeatures();
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION, $enabledFeatures);
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS, $enabledFeatures);
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT, $enabledFeatures);

        $disabledFeatures = $result->getDisabledFeatures();
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_CATEGORY_NAVIGATION, $disabledFeatures);
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_RECOMMENDATIONS, $disabledFeatures);
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_PRESERVES_LAYOUT, $disabledFeatures);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadStoreFixtures
     * @magentoConfigFixture es_es_store klevu_search/general/js_api_key klevu-1234567890
     * @magentoConfigFixture es_es_store klevu_search/general/rest_api_key ABCDE1234567890
     * @magentoConfigFixture es_es_store klevu_search/features_api/last_sync_date 0
     * @magentoConfigFixture es_es_store klevu_search/general/tiers_url tiers.klevu.com
     */
    public function testExecute_FilterDuplicates(): void
    {
        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeaturesAndUpgradeLink']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <upgradeUrl>https://box.klevu.com/analytics/km</upgradeUrl>
    <upgradeMessage>UPGRADE MESSAGE</upgradeMessage>
    <preserveLayoutMessage>PRESERVE LAYOUT MESSAGE</preserveLayoutMessage>
    <enabled>enabledaddtocartfront,boosting,enabledcmsfront,allowgroupprices</enabled>
    <disabled>enabledpopulartermfront,allowgroupprices</disabled>
    <userPlanForStore>Enterprise</userPlanForStore>
    <response>success</response>
</data>
XML
            );
        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeatureValues']
            ->method('getBody')
            ->willReturn(<<<XML
<data>
    <feature>
        <key>s.enablecategorynavigation</key>
        <value>yes</value>
    </feature>
    <feature>
        <key>allow.personalizedrecommendations</key>
        <value>yes</value>
    </feature>
    <feature>
        <key>s.preservedlayout</key>
        <value>yes</value>
    </feature>
</data>
XML
            );

        /** @var GetFeatures $getFeaturesService */
        $getFeaturesService = $this->objectManager->get(GetFeatures::class);

        $result = $getFeaturesService->execute('es_es');

        $this->assertInstanceOf(AccountFeaturesInterface::class, $result);

        $this->assertFalse(
            $result->isFeatureEnabled(AccountFeatures::PM_FEATUREFLAG_ALLOW_GROUP_PRICES),
            'Feature Enabled: Group Prices'
        );

        $enabledFeatures = $result->getEnabledFeatures();
        $this->assertNotContains(AccountFeatures::PM_FEATUREFLAG_ALLOW_GROUP_PRICES, $enabledFeatures);

        $disabledFeatures = $result->getDisabledFeatures();
        $this->assertContains(AccountFeatures::PM_FEATUREFLAG_ALLOW_GROUP_PRICES, $disabledFeatures);
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeaturesAndUpgradeLink'] =
        $this->getMockBuilder(HttpResponse::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBody'])
            ->getMock();

        $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeatureValues'] =
            $this->getMockBuilder(HttpResponse::class)
                ->disableOriginalConstructor()
                ->setMethods(['getBody'])
                ->getMock();

        $this->httpClientMock = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['setParameterPost', 'setUri', 'send'])
            ->getMock();
        $this->httpClientMock
            ->method('setUri')
            ->willReturnCallback(function ($endpoint) {
                $this->httpClientCurrentEndpoint = $endpoint;

                return $this->httpClientMock;
            });
        $this->httpClientMock
            ->method('setParameterPost')
            ->willReturnCallback(function (array $post) {
                $this->assertArrayHasKey('restApiKey', $post);
                $this->assertSame('ABCDE1234567890', $post['restApiKey']);

                if ('https://tiers.klevu.com/uti/getFeatureValues' === $this->httpClientCurrentEndpoint) {
                    $this->assertArrayHasKey('features', $post);
                    $postFeaturesArray = explode(',', $post['features']);
                    $this->assertContains('s.enablecategorynavigation', $postFeaturesArray);
                    $this->assertContains('allow.personalizedrecommendations', $postFeaturesArray);
                    $this->assertContains('s.preservedlayout', $postFeaturesArray);
                }

                return $this->httpClientResponseMock['https://tiers.klevu.com/uti/getFeatureValues'];
            });
        $this->httpClientMock
            ->method('send')
            ->willReturnCallback(function () {
                return $this->httpClientResponseMock[$this->httpClientCurrentEndpoint];
            });
        $this->objectManager->addSharedInstance($this->httpClientMock, HttpClient::class);

        $laminasClientFactoryMock = $this->getMockBuilder(HttpClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $laminasClientFactoryMock->method('create')
            ->willReturn($this->httpClientMock);

        $this->objectManager->addSharedInstance($laminasClientFactoryMock, HttpClientFactory::class);
    }

    /**
     * Loads store creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStoreFixtures(): void
    {
        include __DIR__ . '/../../../_files/storeFixtures.php';
    }

    /**
     * Rolls back store creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStoreFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/storeFixtures_rollback.php';
    }
}
