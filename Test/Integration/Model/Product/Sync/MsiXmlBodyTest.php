<?php

declare(strict_types=1);

namespace Klevu\ApiRequest\Test\Integration\Model\Product\Sync;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use Klevu\Search\Model\Product\Sync as ProductSync;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request;
use Laminas\Http\Response as HttpResponse;
use Magento\Backend\Model\Session;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogUrlRewrite\Model\Map\UrlRewriteFinder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * All test implement loadStockFixtures to generate stock at none default scope.
 * The Klevu\Search version of this test deals with default scope
 */
class MsiXmlBodyTest extends TestCase
{
    public const SESSION_ID_FIXTURE = 'ABCDE12345';

    /**
     * @var ObjectManager
     */
    private $objectManager;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var UrlRewriteFinder
     */
    private $urlRewriteFinder;
    /**
     * @var string
     */
    private $baseUrl = '';
    /**
     * @var MockObject&HttpClient
     */
    private $clientMock;
    /**
     * @var int
     */
    private $decimalPrecision;

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testSimpleProduct_ZeroPrice(): void
    {
        self::loadProductFixturesActual(['klevu_simple_synctest_xmlbody']);

        $store = $this->getStore('klevu_test_store_1');
        $product = $this->productRepository->get('klevu_simple_synctest_xmlbody', false, $store->getId());

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Simple Product</value>',
            '<key>sku</key><value>klevu_simple_synctest_xmlbody</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>0</value>',
            '<key>salePrice</key><value>0</value>',
            '<key>startPrice</key><value/>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value>' . $this->getDecimalString(1) . '</value>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value>' . $this->getDecimalString(0) . '</value>',
            '<key>special_from_date</key><value/>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog-search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>product_type</key><value>simple</value>',
            '<key>isCustomOptionsAvailable</key><value>no</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($product, $store) . '</value>',
            '<key>inStock</key><value>yes</value>',
            '<key>itemGroupId</key><value/>',
            '<key>id</key><value>' . $product->getId() . '</value>',
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testVirtualProduct(): void
    {
        self::loadProductFixturesActual(['klevu_virtual_synctest_xmlbody']);

        $store = $this->getStore('klevu_test_store_1');
        $product = $this->productRepository->get('klevu_virtual_synctest_xmlbody', false, $store->getId());

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Virtual Product</value>',
            '<key>sku</key><value>klevu_virtual_synctest_xmlbody</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>1.23</value>',
            '<key>salePrice</key><value>0.99</value>',
            '<key>startPrice</key><value>0.99</value>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value/>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value>' . $this->getDecimalString(0.987654) . '</value>',
            '<key>special_from_date</key><value>' . date('Y-m-d') . ' 00:00:00</value>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:No</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:No</value>',
            '<key>product_type</key><value>virtual</value>',
            '<key>isCustomOptionsAvailable</key><value>no</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($product, $store) . '</value>',
            '<key>inStock</key><value>yes</value>',
            '<key>itemGroupId</key><value/>',
            '<key>id</key><value>' . $product->getId() . '</value>',
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testDownloadableProduct(): void
    {
        self::loadProductFixturesActual(['klevu_downloadable_synctest_xmlbody']);

        $store = $this->getStore('klevu_test_store_1');
        $product = $this->productRepository->get(
            'klevu_downloadable_synctest_xmlbody',
            false,
            $store->getId()
        );

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Downloadable Product</value>',
            '<key>sku</key><value>klevu_downloadable_synctest_xmlbody</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>100</value>',
            '<key>salePrice</key><value>50</value>',
            '<key>startPrice</key><value>50</value>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value/>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value>' . $this->getDecimalString(50) . '</value>',
            '<key>special_from_date</key><value>' . date('Y-m-d') . ' 00:00:00</value>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:No</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:No</value>',
            '<key>product_type</key><value>downloadable</value>',
            '<key>isCustomOptionsAvailable</key><value>yes</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($product, $store) . '</value>',
            '<key>inStock</key><value>no</value>',
            '<key>itemGroupId</key><value/>',
            '<key>id</key><value>' . $product->getId() . '</value>',
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testConfigurableProduct_OutOfStock(): void
    {
        self::loadProductFixturesActual([
            'klevu_configchild_synctest_xmlbody_oos',
            'klevu_config_synctest_xmlbody_instock_child_oos',
        ]);

        $store = $this->getStore('klevu_test_store_1');
        $parentProduct = $this->productRepository->get(
            'klevu_config_synctest_xmlbody_instock_child_oos',
            false,
            $store->getId()
        );
        $childProduct = $this->productRepository->get(
            'klevu_configchild_synctest_xmlbody_oos',
            false,
            $store->getId()
        );

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Configurable Product In Stock Child OOS</value>',
            // phpcs:ignore Generic.Files.LineLength
            '<key>sku</key><value>klevu_config_synctest_xmlbody_instock_child_oos;;;;klevu_configchild_synctest_xmlbody_oos</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>20</value>',
            '<key>salePrice</key><value>20</value>',
            '<key>startPrice</key><value/>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value>' . $this->getDecimalString(1) . '</value>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value/>',
            '<key>special_from_date</key><value/>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog-search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>product_type</key><value>configurable</value>',
            '<key>isCustomOptionsAvailable</key><value>yes</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($parentProduct, $store) . '</value>',
            '<key>inStock</key><value>no</value>',
            '<key>itemGroupId</key><value>' . $parentProduct->getId() . '</value>',
            sprintf(
                '<key>id</key><value>%s-%s</value>',
                $parentProduct->getId(),
                $childProduct->getId()
            ),
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testConfigurableProduct_InStock(): void
    {
        self::loadProductFixturesActual([
            'klevu_configchild_synctest_xmlbody_instock',
            'klevu_config_synctest_xmlbody_instock_childinstock',
        ]);

        $store = $this->getStore('klevu_test_store_1');
        $parentProduct = $this->productRepository->get(
            'klevu_config_synctest_xmlbody_instock_childinstock',
            false,
            $store->getId()
        );
        $childProduct = $this->productRepository->get(
            'klevu_configchild_synctest_xmlbody_instock',
            false,
            $store->getId()
        );

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Configurable Product In Stock Child In Stock</value>',
            // phpcs:ignore Generic.Files.LineLength
            '<key>sku</key><value>klevu_config_synctest_xmlbody_instock_childinstock;;;;klevu_configchild_synctest_xmlbody_instock</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>20</value>',
            '<key>salePrice</key><value>20</value>',
            '<key>startPrice</key><value>20</value>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value>' . $this->getDecimalString(1) . '</value>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value/>',
            '<key>special_from_date</key><value/>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog-search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>product_type</key><value>configurable</value>',
            '<key>isCustomOptionsAvailable</key><value>yes</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($parentProduct, $store) . '</value>',
            '<key>inStock</key><value>yes</value>',
            '<key>itemGroupId</key><value>' . $parentProduct->getId() . '</value>',
            sprintf(
                '<key>id</key><value>%s-%s</value>',
                $parentProduct->getId(),
                $childProduct->getId()
            ),
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testGroupedProduct(): void
    {
        self::loadProductFixturesActual([
            'klevu_groupchild_synctest_xmlbody',
            'klevu_grouped_synctest_xmlbody',
        ]);

        $store = $this->getStore('klevu_test_store_1');
        $parentProduct = $this->productRepository->get(
            'klevu_grouped_synctest_xmlbody',
            false,
            $store->getid()
        );

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Grouped Product</value>',
            '<key>sku</key><value>klevu_grouped_synctest_xmlbody</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>50</value>',
            '<key>salePrice</key><value>50</value>',
            '<key>startPrice</key><value>50</value>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value/>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value/>',
            '<key>special_from_date</key><value/>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog-search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:No</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:No</value>',
            '<key>product_type</key><value>grouped</value>',
            '<key>isCustomOptionsAvailable</key><value>yes</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($parentProduct, $store) . '</value>',
            '<key>inStock</key><value>no</value>',
            '<key>itemGroupId</key><value/>',
            '<key>id</key><value>' . $parentProduct->getId() . '</value>',
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testBundleProduct(): void
    {
        self::loadProductFixturesActual([
            'klevu_bundlechild_synctest_xmlbody',
            'klevu_bundle_synctest_xmlbody',
        ]);

        $store = $this->getStore('klevu_test_store_1');
        $parentProduct = $this->productRepository->get(
            'klevu_bundle_synctest_xmlbody',
            false,
            $store->getId()
        );

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Bundle Product</value>',
            '<key>sku</key><value>klevu_bundle_synctest_xmlbody</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value/>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>50</value>',
            '<key>salePrice</key><value>50</value>',
            '<key>startPrice</key><value>50</value>',
            '<key>toPrice</key><value>50</value>',
            '<key>weight</key><value>' . $this->getDecimalString(1) . '</value>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value/>',
            '<key>special_from_date</key><value/>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog-search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>product_type</key><value>bundle</value>',
            '<key>isCustomOptionsAvailable</key><value>yes</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($parentProduct, $store) . '</value>',
            '<key>inStock</key><value>no</value>',
            '<key>itemGroupId</key><value/>',
            '<key>id</key><value>' . $parentProduct->getId() . '</value>',
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default JPY
     * @magentoConfigFixture default/currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default_store currency/options/default JPY
     * @magentoConfigFixture default_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default JPY
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow JPY,EUR,GBP
     * @magentoConfigFixture default/klevu_search/product_sync/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/enabled 1
     * @magentoConfigFixture default/klevu_search/developer/collection_method 1
     * @magentoConfigFixture default/klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/include_oos 1
     * @magentoConfigFixture default/cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture klevu_test_store_1_store cataloginventory/options/show_out_of_stock 0
     * @magentoConfigFixture default/klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/product_sync/catalogvisibility 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_search/attributes/other klevu_boolean_attribute,meta_keyword
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadBooleanAttributeFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testGiftcardProduct(): void
    {
        self::loadProductFixturesActual(['klevu_giftcard_synctest_xmlbody']);

        $store = $this->getStore('klevu_test_store_1');
        try {
            $product = $this->productRepository->get(
                'klevu_giftcard_synctest_xmlbody',
                false,
                $store->getId()
            );
        } catch (NoSuchEntityException $e) {
            $this->markTestSkipped('Giftcard testing not applicable for CE');
        }

        $expectedPairs = [
            '<key>name</key><value>[Klevu] Sync Test: Giftcard Product</value>',
            '<key>sku</key><value>klevu_giftcard_synctest_xmlbody</value>',
            '<key>image</key><value/>',
            '<key>small_image</key><value>no_selection</value>',
            '<key>media_gallery</key><value><images/><values/></value>',
            '<key>status</key><value><label>Enable Product</label><values>Enabled</values></value>',
            '<key>desc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>shortDesc</key><value><![CDATA[[Klevu Test Fixtures]]]></value>',
            '<key>price</key><value>0</value>',
            '<key>salePrice</key><value>25</value>',
            '<key>startPrice</key><value>25</value>',
            '<key>toPrice</key><value/>',
            '<key>weight</key><value>' . $this->getDecimalString(1) . '</value>',
            '<key>rating</key><value/>',
            '<key>rating_count</key><value/>',
            '<key>special_price</key><value/>',
            '<key>special_from_date</key><value/>',
            '<key>special_to_date</key><value/>',
            '<key>visibility</key><value>catalog-search</value>',
            // Fixture is freshly created so should always return today's date (no time)
            '<key>dateAdded</key><value>' . date('Y-m-d') . '</value>',
            '<key>other</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>otherAttributeToIndex</key><value>klevu_boolean_attribute:Klevu Boolean Attribute:Yes</value>',
            '<key>product_type</key><value>giftcard</value>',
            '<key>isCustomOptionsAvailable</key><value>yes</value>',
            '<key>currency</key><value>USD</value>',
            '<key>otherPrices</key><value/>',
            '<key>category</key><value/>',
            '<key>listCategory</key><value>KLEVU_PRODUCT</value>',
            '<key>categoryIds</key><value/>',
            '<key>categoryPaths</key><value/>',
            '<key>groupPrices</key><value/>',
            // Contains dynamic base url and randomly generated URL key, so need to take from source
            '<key>url</key><value>' . $this->getProductUrl($product, $store) . '</value>',
            '<key>inStock</key><value>yes</value>',
            '<key>itemGroupId</key><value/>',
            '<key>id</key><value>' . $product->getId() . '</value>',
        ];

        $this->addSetRawBodyToClientMock($this->clientMock, $expectedPairs);

        /** @var ProductSync $productSync */
        $productSync = $this->objectManager->get(ProductSync::class);
        $productSync->syncData($store);
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        if (!class_exists('\Klevu\Msi\Service\Catalog\Product\GetStockStatusById')) {
            $this->markTestSkipped('MSI module not installed, skip this test');
        }

        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->scopeConfig = $this->objectManager->get(ScopeConfigInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->urlRewriteFinder = $this->objectManager->get(UrlRewriteFinder::class);

        /** @var ResourceConnection $resourceModel */
        $resourceModel = $this->objectManager->get(ResourceConnection::class);
        $connection = $resourceModel->getConnection();
        $eavDecimalTable = $connection->describeTable(
            $resourceModel->getTableName('catalog_product_entity_decimal')
        );
        $this->decimalPrecision = (int)$eavDecimalTable['value']['SCALE'];

        $this->baseUrl = $this->scopeConfig->getValue('web/unsecure/base_url');
        $useRewrites = $this->scopeConfig->isSetFlag('web/seo/use_rewrites');
        if (!$useRewrites) {
            $this->baseUrl .= 'index.php/';
        }

        /** @var Session $backendSession */
        $backendSession = $this->objectManager->get(Session::class);
        $backendSession->setKlevuSessionId(self::SESSION_ID_FIXTURE);

        $mockClientBuilder = $this->getMockBuilder(HttpClient::class);
        if (method_exists($mockClientBuilder, 'onlyMethods')) {
            $mockClientBuilder->onlyMethods(['setRawBody', 'send']);
        } else {
            $mockClientBuilder->setMethods(['setRawBody', 'send']);
        }
        $this->clientMock = $mockClientBuilder
            ->disableOriginalConstructor()
            ->getMock();

        $this->clientMock->method('send')
            ->willReturnCallback(function (?Request $request = null) {
                return $this->objectManager->create(HttpResponse::class);
            });

        $this->objectManager->addSharedInstance($this->clientMock, HttpClient::class);
    }

    /**
     * @param MockObject $clientMock
     * @param array $expectedPairs
     *
     * @return void
     */
    private function addSetRawBodyToClientMock($clientMock, array $expectedPairs): void
    {
        $clientMock->method('setRawBody')
            ->willReturnCallback(function ($content) use ($expectedPairs) {
                if (function_exists('gzencode') && false !== gzencode('foo')) {
                    $content = gzdecode($content);
                }

                $this->assertStringContainsStringBackCompat(
                    '<request><sessionId>' . self::SESSION_ID_FIXTURE . '</sessionId><records><record>',
                    $content,
                    'XML Content contains header and sessionId'
                );

                foreach ($expectedPairs as $expectedPair) {
                    $this->assertStringContainsStringBackCompat($expectedPair, $content);
                }

                $actualKeys = [];
                preg_match_all('#<pair><key>(.*?)</key>#', $content, $actualKeys);
                $expectedKeys = array_map(static function ($pair) {
                    return preg_replace(
                        '#<key>(.*?)</key>(<value>(.*?)</value>|<value/>)#',
                        '$1',
                        $pair
                    );
                }, $expectedPairs);

                $this->assertEquals($expectedKeys, $actualKeys[1], 'Pair keys');
            });
    }

    /**
     * @param string $storeCode
     *
     * @return StoreInterface
     */
    private function getStore(string $storeCode): StoreInterface
    {
        $storeRepository = $this->objectManager->get(StoreRepositoryInterface::class);

        try {
            return $storeRepository->get($storeCode);
        } catch (NoSuchEntityException $e) {
            $this->fail(sprintf('Store: %s could not be loaded.', $storeCode));
        }
    }

    /**
     * Loads website stock creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStockFixtures(): void
    {
        include __DIR__ . '/../../../_files/stockFixtures.php';
    }

    /**
     * Rolls back website stock creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStockFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/stockFixtures_rollback.php';
    }

    /**
     * Loads store and website creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadWebsiteFixtures(): void
    {
        include __DIR__ . '/../../../_files/websiteFixtures.php';
    }

    /**
     * Rolls back store and website creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadWebsiteFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/websiteFixtures_rollback.php';
    }

    /**
     * Loads attribute creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadBooleanAttributeFixtures(): void
    {
        include __DIR__ . '/_files/productAttributeBooleanFixtures.php';
    }

    /**
     * Rolls attribute creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadBooleanAttributeFixturesRollback(): void
    {
        include __DIR__ . '/_files/productAttributeBooleanFixtures_rollback.php';
    }

    /**
     * Loads product creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadProductFixturesActual(?array $CREATE_SKUS = null): void
    {
        include __DIR__ . '/_files/productFixturesXmlBody.php';
    }

    public static function loadProductFixtures(): void
    {
        // Intentionally empty
    }

    /**
     * Rolls back product creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadProductFixturesRollback(): void
    {
        include __DIR__ . '/_files/productFixturesXmlBody_rollback.php';
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    private function assertStringContainsStringBackCompat(string $needle, string $haystack, string $message = ''): void
    {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
            return;
        }

        $this->assertContains($needle, $haystack, $message);
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     *
     * @return string
     */
    private function getProductUrl(ProductInterface $product, StoreInterface $store): string
    {
        $urlRewrites = $this->urlRewriteFinder->findAllByData(
            $product->getId(),
            $store->getId(),
            UrlRewriteFinder::ENTITY_TYPE_PRODUCT
        );

        if (empty($urlRewrites)) {
            if (method_exists($this, 'addWarning')) {
                $this->addWarning(sprintf(
                    'Url Rewrites not created for %s in store %s',
                    $product->getSku(),
                    $store->getCode()
                ));
            }

            return $this->baseUrl
                . 'catalog/product/view/id/'
                . $product->getId();
        }

        $urlRewrite = current($urlRewrites);

        return $this->baseUrl . $urlRewrite->getRequestPath();
    }

    /**
     * @param int|float $value
     *
     * @return string
     */
    private function getDecimalString($value): string
    {
        $decVal = abs($value - (int)$value);

        if (0 === $decVal) {
            return sprintf(
                "%d.%-'0" . $this->decimalPrecision . "s",
                (int)$value,
                0
            );
        }

        return sprintf(
            "%d.%'0" . $this->decimalPrecision . "s",
            (int)$value,
            round($decVal * pow(10, $this->decimalPrecision))
        );
    }
}
