<?php
namespace Fave\PaymentGateway\Block\Product;

use Magento\Payment\Gateway\ConfigInterface;
use \Magento\CatalogWidget\Block\Product\ProductsList as MagentoProductsList;
use \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;

class ProductsList extends \Magento\CatalogWidget\Block\Product\ProductsList
{
    private $config;
    protected $_storeManager;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Rule\Model\Condition\Sql\Builder $sqlBuilder,
        \Magento\CatalogWidget\Model\Rule $rule,
        \Magento\Widget\Helper\Conditions $conditionsHelper,
        CategoryCollectionFactory $categoryCollectionFactory,
        array $data = [],
        Json $json = null,
        ConfigInterface $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->_storeManager = $storeManager;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data,
            $json
        );
    }

    public function getCashbackRate()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        return $this->config->getValue('cashback_rate', $store_id);
    }

    public function isPromoMessagingEnabled()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        return $this->config->getValue('enable_product_promotional_messaging', $store_id);
    }

    public function getCountryCode()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));
        return $country_code;
    }
}