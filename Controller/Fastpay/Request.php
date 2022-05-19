<?php
namespace Fave\PaymentGateway\Controller\Fastpay;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Fave\PaymentGateway\Helper\Data;
use Fave\PaymentGateway\Controller\Fastpay\CustomerToken;

class Request extends \Magento\Framework\App\Action\Action
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
		return parent::__construct($context);
	}

	public function execute()
	{
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $is_fastpay_enabled = $this->config->getValue('enable_fastpay', $store_id);

        if ($is_fastpay_enabled != "1") {
            $resultJson = $this->resultJsonFactory->create();
            return $resultJson->setData(['message' => "FastPay is not enabled. Please enable it in payment settings."]);
        }
        //=========================================================================================
        $order_currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        $store_code = $this->_storeManager->getStore()->getCode();
        $store_url = $this->_storeManager->getStore()->getBaseUrl();
        $identifier = strtok(parse_url($store_url)['host'], '.');
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));

        $host = $this->config->getValue('host', $store_id);

        $prefix = $this->get_prefix($country_code, $host);
        
        $omni_reference = uniqid($prefix . '-' . $country_code . substr($identifier, 0, 5));
        $redirect_url = $store_url . 'checkout/onepage/success?country=' . $country_code;

        $route_params = $this->getRequest()->getParams();
        $productId = $route_params['product_id'];
        $qty = $route_params['qty'];
        $op1_id = array_key_exists('op1_id', $route_params) ?  $route_params['op1_id'] : null;
        $op1_value = array_key_exists('op1_value', $route_params) ?  $route_params['op1_value'] : null;
        $op2_id = array_key_exists('op2_id', $route_params) ?  $route_params['op2_id'] : null;
        $op2_value = array_key_exists('op2_value', $route_params) ?  $route_params['op2_value'] : null;
        
        $product = $this->_product->load($productId);
        $product_price = $product->getFinalPrice();
        $product_sku = $product->getSku();
        $product_name =  $product->getName();
        $smallImage = $this->getImageUrl($product, 'product_page_image_small');
        
        $is_configurable = $product->getTypeId() == "configurable";

        //Place order
        $place_order_results = $this->place_order($qty, $product_sku, $is_configurable, $op1_id, $op1_value, $op2_id, $op2_value);

		$fastpay_endpoint = $host . "api/fastpay/v1/" . $country_code . "/express_qr";   

        $line_items = array(
            array(
                'name' => $product_name,
                'product_id' => $productId,
                'variation_id' => $product_sku,
                'image' => $smallImage,
                'currency' => $currency_code,
                'price' => number_format($product_price, 2),
                'subtotal' => $place_order_results["subtotal"],
                'quantity' => (int) $qty,
                'total_tax' => $place_order_results["tax_amount"]
            ),
        );

        $order_params = array(
            'id'                => $place_order_results["order_id"],
            'currency'          => $currency_code,
            'discount_total'    => $place_order_results["discount_amount"],
            'shipping_total'    => $place_order_results["shipping_amount"],
            'tax_total'         => $place_order_results["tax_amount"],
            'total'             => number_format($place_order_results["grand_total"], 2),
            'line_items'        => $line_items
        );

        $callback_url = $store_url . 'paymentgateway/callback/index?order_id=' .  $place_order_results['reserved_order_id'];

		$params = array(
            'omni_reference'     => $omni_reference,
            'total_amount_cents' => $place_order_results["grand_total"] * 100,
            'app_id'             => $this->config->getValue('merchant_gateway_key', $store_id),
            'outlet_id'          => $this->config->getValue('outlet_id', $store_id),
            'qr_format'          => "web_url",
            'shipping_info_url'  => $store_url . "paymentgateway/fastpay/shipping",
            'update_order_url'   => $store_url . "paymentgateway/fastpay/updateorder",
            'skip_shipping'      => false,
            'redirect_url'       => $redirect_url,
            'callback_url'       => $callback_url,
            'order'              => $order_params
        );

		// Generate API signature
        $params['sign'] = $this->generate_api_signature($params, $store_id);

        $result = $this->curl_request($fastpay_endpoint, $params);
		$code = isset($result['code']) ? $result['code'] : null;
        $this->_redirect($code);
	}


    private function place_order($qty, $product_sku, $is_configurable, $op1_id, $op1_value, $op2_id, $op2_value) {
        $store_code = $this->_storeManager->getStore()->getCode();
        $store_url = $this->_storeManager->getStore()->getBaseUrl();
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));

        //Create empty cart
        $create_cart_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/';
        $cart_token = $this->curl_request($create_cart_url);

        //Add product 
        $add_product_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/' . $cart_token . '/items';

        //=====================================================================================================
        //Set configurable product
        $optionsList = array();
        $optionsList[0]['option_id']     = $op1_id;
        $optionsList[0]['option_value']  = (int) $op1_value;
        $optionsList[1]['option_id']     = $op2_id;
        $optionsList[1]['option_value']  = (int) $op2_value;

        $extension_attributes_params = array(
            'configurable_item_options'     => $optionsList
        );

        $product_option_params = array(
            'extension_attributes'     => $extension_attributes_params
        );
        
        $configurable_cart_item_params = array(
            'quote_id'          => $cart_token,
            'sku'               => $product_sku,
            'qty'               => (int) $qty,
            'product_option'    => $product_option_params
        );

        //Set simple product
        $simple_cart_item_params = array(
            'quote_id'     => $cart_token,
            'sku'          => $product_sku,
            'qty'          => (int) $qty
        );

        if ($is_configurable) {
            $cart_item_params = $configurable_cart_item_params;
        }
        else {
            $cart_item_params = $simple_cart_item_params;
        }
                
        $add_product_params = array(
            'cartItem'     => $cart_item_params
        );

        $result = $this->curl_request($add_product_url, $add_product_params);
        $quote_id = isset($result['quote_id']) ? $result['quote_id'] : null;
        //=====================================================================================================
        //Add shipping information
        $add_shipping_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/' . $cart_token . '/shipping-information';

        $shipping_params = array(
            'region'                 => strtoupper($country_code),
            'region_id'              => 0,
            'country_id'             => strtoupper($country_code),
            'street'                 => [ 'Default' ],
            'telephone'              => "1111111",
            'postcode'               => "12223",
            'city'                   => "Default",
            'firstname'              => "Fastpay",
            'lastname'               => "User",
            'email'                  => "test@example.com"
        );

        $shipping_address_params = array(
            'shippingAddress'        => $shipping_params,
            'billingAddress'         => $shipping_params,
            'shipping_method_code'   => "flatrate",
            'shipping_carrier_code'  => "flatrate"
        );

        $add_shipping_params = array(
            'addressInformation'     => $shipping_address_params
        );
        $result = $this->curl_request($add_shipping_url, $add_shipping_params);
        $grand_total = $result['totals']['grand_total'];
        $subtotal = $result['totals']['subtotal'];
        $discount_amount = $result['totals']['discount_amount'];
        $shipping_amount = $result['totals']['shipping_amount'];
        $tax_amount = $result['totals']['tax_amount'];


        //Place order
        $place_order_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/' . $cart_token . '/payment-information';

        $payment_method_params = array(
            'method'     => "fave_gateway"
        );

        $place_order_params = array(
            'email'             => "fastpay_magento@myfave.com",
            'paymentMethod'     => $payment_method_params
        );
        $orderId = $this->curl_request($place_order_url, $place_order_params);
        $order = $this->orderRepository->get($orderId);
        $orderIncrementId = $order->getIncrementId();

        $quote = $this->quoteRepository->get($quote_id);
        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());

        $resultJson = array(
            'grand_total' => $grand_total,
            'subtotal' => number_format($subtotal, 2),
            'discount_amount' => number_format($discount_amount, 2),
            'shipping_amount' => number_format($shipping_amount, 2),
            'tax_amount' => number_format($tax_amount, 2),
            'order_id' => $orderId,
            'reserved_order_id' => $orderIncrementId
        );

        return $resultJson;
    }

    //make request
    private function curl_request($endpoint, $params = null) {
        $this->_curl->addHeader("Content-Type", "application/json");
		$this->_curl->post($endpoint, json_encode($params));
		$result = $this->_curl->getBody();
		return json_decode($result, true);
    }

	// Generate API signature
    private function generate_api_signature(array $params, $store_id) {

        unset( $params['sign'] );

        $encoded_params = json_encode( $params, JSON_UNESCAPED_SLASHES );
        $api_key = $this->config->getValue('private_api_key', $store_id);

        return hash_hmac( 'sha256', $encoded_params, $api_key );

    }

    private function get_prefix($country_code, $host) {
        $isStaging = $this->is_staging($host);

        if ($isStaging) {
            $prefix = "FPO";
        }
        elseif ($country_code == "my") {
            $prefix = "MGMY";
        }
        else {
            $prefix = "MGSG";
        }
        return $prefix;
    }

    private function is_staging($host) {
        if (strpos($host, 'app') !== false) {
            return true;
        }
        return false;
    }

    protected function getImageUrl($product, string $imageType = '')
    {
        $storeId = $this->_storeManager->getStore()->getId();

        $this->_appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);

        $imageBlock =  $this->_blockFactory->createBlock('Magento\Catalog\Block\Product\ListProduct');
        $productImage = $imageBlock->getImage($product, $imageType);
        $imageUrl = $productImage->getImageUrl();

        $this->_appEmulation->stopEnvironmentEmulation();

        return $imageUrl;
    }
}

