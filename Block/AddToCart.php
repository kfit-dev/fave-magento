<?php
namespace Fave\PaymentGateway\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Payment\Gateway\ConfigInterface;
 
class AddToCart extends \Magento\Catalog\Block\Product\View
{
    private $config;
    protected $_storeManager;
   
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Url\EncoderInterface $urlEncoder,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Magento\Customer\Model\Session $customerSession,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        ConfigInterface $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_attributeSetFactory = $attributeSetFactory;
        $this->config = $config;
        $this->_storeManager = $storeManager;
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );
    }

    public function isFastPayEnabled()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        return $this->config->getValue('enable_fastpay', $store_id);
    }

    public function isPromoMessagingEnabled()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        return $this->config->getValue('enable_product_promotional_messaging', $store_id);
    }

    public function getCashbackRate()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        return $this->config->getValue('cashback_rate', $store_id);
    }

    public function getCountryCode()
    {
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));
        return $country_code;
    }
}
 