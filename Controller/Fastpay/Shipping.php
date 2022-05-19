<?php
namespace Fave\PaymentGateway\Controller\Fastpay;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Fave\PaymentGateway\Helper\Data;
use Fave\PaymentGateway\Controller\Fastpay\CustomerToken;

class Shipping extends \Magento\Framework\App\Action\Action
{
	protected $_pageFactory;
	protected $_curl;
    protected $_storeManager;
    protected $_appEmulation;
    protected $_blockFactory;
    protected $orderRepository;
    protected $_product;
    private $checkoutSession;
    private $config;
    private $helper;
    private $customerToken;
    private $quoteRepository;
    private $resultJsonFactory;
    private $repositoryAddress;
    private $scopeConfig; 
    private $shipconfig;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $pageFactory,
		\Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Element\BlockFactory $blockFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Catalog\Model\Product $product,
        \Magento\Sales\Model\Order\AddressRepository $repositoryAddress,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Shipping\Model\Config $shipconfig,
        ConfigInterface $config,
        Data $helper,
        CustomerToken $customerToken,
        JsonFactory $resultJsonFactory,
        Session $checkoutSession)
	{
		$this->_pageFactory = $pageFactory;
		$this->_curl = $curl;
        $this->_storeManager = $storeManager;
        $this->_blockFactory = $blockFactory;
        $this->_appEmulation = $appEmulation;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->helper = $helper;
        $this->customerToken = $customerToken;
        $this->_product = $product;
        $this->quoteRepository = $quoteRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->repositoryAddress= $repositoryAddress;
        $this->shipconfig = $shipconfig;
        $this->scopeConfig = $scopeConfig;
		return parent::__construct($context);
	}

	public function execute()
	{
        $route_params = $this->getRequest()->getParams();
        
        $orderId = $route_params['order_id'];
        
        $order = $this->orderRepository->get($orderId);
        
        foreach ($order->getAllItems() as $item) {
            $qty = (int) $item->getData()['qty_ordered'];
            $product_sku = $item->getData()['sku'];
        }

        $methodsList = $this->get_shipping_amount($route_params, $product_sku, $qty);

        $resultJson = $this->resultJsonFactory->create();
        
        return $resultJson->setData($methodsList);
	}

    private function get_shipping_amount($route_params, $product_sku, $qty) {
        $store_code = $this->_storeManager->getStore()->getCode();
        $store_url = $this->_storeManager->getStore()->getBaseUrl();
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));

        //Create empty cart
        $create_cart_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/';
        $cart_token = $this->curl_request($create_cart_url);

        //Add product 
        $add_product_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/' . $cart_token . '/items';

        $cart_item_params = array(
            'quote_id'     => $cart_token,
            'sku'          => $product_sku,
            'qty'          => $qty
        );

        $add_product_params = array(
            'cartItem'     => $cart_item_params
        );
        $result = $this->curl_request($add_product_url, $add_product_params);
        $quote_id = isset($result['quote_id']) ? $result['quote_id'] : null;

        //Get list of shipping options
        $estimate_shipping_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/' . $cart_token . '/estimate-shipping-methods';

        $address_params = array(
            'region'                 => strtoupper($country_code),
            'region_id'              => 0,
            'region_code'            => $route_params['country_code'],
            'country_id'             => strtoupper($country_code),
            'postcode'               => $route_params['postcode']
        );

        $estimate_shipping_params = array(
            'address'                => $address_params
        );

        $result = $this->curl_request($estimate_shipping_url, $estimate_shipping_params);

        $methodsList = array();

        for ($i = 0; $i < count($result); $i += 1) {
            $methodsList[$i]['id'] = $i;
            $methodsList[$i]['method_id'] = $result[$i]['carrier_code'] . '_' . $result[$i]['method_code'];
            $methodsList[$i]['title'] = $result[$i]['method_title'];            
            $methodsList[$i]['cost'] = number_format($result[$i]['amount'], 2);
        } 

        return $methodsList;
    }

    //make request
    private function curl_request($endpoint, $params = null) {
        $this->_curl->addHeader("Content-Type", "application/json");
		$this->_curl->post($endpoint, json_encode($params));
		$result = $this->_curl->getBody();
		return json_decode($result, true);
    }
}

