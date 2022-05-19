<?php
namespace Fave\PaymentGateway\Controller\Fastpay;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Fave\PaymentGateway\Helper\Data;
use Fave\PaymentGateway\Controller\Fastpay\CustomerToken;

class UpdateOrder extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
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
		return parent::__construct($context);
	}

	public function execute()
	{
        $post = $this->getRequest()->getContent();
        $array = json_decode($post, true);
        
        //Get grand total
        $quote_results = $this->get_grand_total($array);
        
        $resultJson = $this->resultJsonFactory->create();
        
        return $resultJson->setData([
            'total_amount_cents' => $quote_results['grand_total'] * 100,
        ]);
	}


    private function get_grand_total($array) {
        $store_code = $this->_storeManager->getStore()->getCode();
        $store_url = $this->_storeManager->getStore()->getBaseUrl();
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));

        $shipping_first_name = $array['shipping_address']['first_name'];
        $shipping_last_name = $array['shipping_address']['last_name'];
        $shipping_address1 = $array['shipping_address']['address1'];
        $shipping_address2 = $array['shipping_address']['address2'];
        $shipping_city = $array['shipping_address']['city'];
        $shipping_state = $array['shipping_address']['state'];
        $shipping_postcode = $array['shipping_address']['postcode'];
        $shipping_country = $array['shipping_address']['country'];
        $shipping_email = $array['shipping_address']['email'];
        $shipping_phone = $array['shipping_address']['phone'];

        $billing_first_name = $array['billing_address']['first_name'];
        $billing_last_name = $array['billing_address']['last_name'];
        $billing_address1 = $array['billing_address']['address1'];
        $billing_address2 = $array['billing_address']['address2'];
        $billing_city = $array['billing_address']['city'];
        $billing_state = $array['billing_address']['state'];
        $billing_postcode = $array['billing_address']['postcode'];
        $billing_country = $array['billing_address']['country'];
        $billing_email = $array['billing_address']['email'];
        $billing_phone = $array['billing_address']['phone'];

        $shipping_line_id = $array['shipping_line']['id'];
        $shipping_line_method_id = $array['shipping_line']['method_id'];
        $shipping_method = explode('_', $shipping_line_method_id);
        $shipping_carrier_code = $shipping_method[0];
        $shipping_method_code = $shipping_method[1];
        $customer_note = $array['note'];
        //==========================================================================================
        $order = $this->orderRepository->get($array['id']);
        
        foreach ($order->getAllItems() as $item) {
            $qty = (int) $item->getData()['qty_ordered'];
            $product_sku = $item->getData()['sku'];
        }
        //==========================================================================================
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
        $quote = $this->quoteRepository->get($quote_id);
        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());

        //Add shipping information
        $add_shipping_url = $store_url . 'index.php/rest/' . $store_code . '/V1/guest-carts/' . $cart_token . '/shipping-information';

        $shipping_params = array(
            'region'                 => strtoupper($country_code),
            'region_id'              => 0,
            'country_id'             => strtoupper($country_code),
            'street'                 => [ $shipping_address1 ],
            'telephone'              => $shipping_phone,
            'postcode'               => $shipping_postcode,
            'city'                   => $shipping_city,
            'firstname'              => $shipping_first_name,
            'lastname'               => $shipping_last_name,
            'email'                  => $shipping_email
        );

        $shipping_address_params = array(
            'shippingAddress'        => $shipping_params,
            'billingAddress'         => $shipping_params,
            'shipping_method_code'   => $shipping_method_code,
            'shipping_carrier_code'  => $shipping_carrier_code
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
        //=========================================================================================
        //Update order details
        if (!empty($customer_note)) {
            $order->addStatusHistoryComment($customer_note); 
        }
        
        $order->setCustomerEmail($billing_email);
        $order->setCustomerFirstname($billing_first_name);
        $order->setCustomerLastname($billing_last_name);
        
        // set totals
        $order->setBaseGrandTotal($result['totals']['base_grand_total']);
        $order->setGrandTotal($grand_total);
        $order->setBaseSubtotal($result['totals']['base_subtotal']);
        $order->setSubtotal($subtotal);
        $order->setBaseTaxAmount($result['totals']['base_tax_amount']);
        $order->setTaxAmount($tax_amount);
        $order->setBaseDiscountAmount($result['totals']['base_discount_amount']);
        $order->setDiscountAmount($discount_amount);
        $order->setBaseSubtotalInclTax($result['totals']['subtotal_incl_tax']);
        $order->setSubtotalInclTax($result['totals']['subtotal_incl_tax']);
        $order->setTotalItemCount($qty);
        $order->setTotalQtyOrdered($qty);

        // set shipping amounts
        $order->setShippingAmount($shipping_amount);
        $order->setBaseShippingAmount($result['totals']['base_shipping_amount']);
        $order->setShippingTaxAmount($result['totals']['base_shipping_tax_amount']);
        $order->setBaseShippingTaxAmount($result['totals']['base_shipping_tax_amount']);
        $order->setShippingMethod($shipping_method_code);
        $order->setShippingDescription($shipping_carrier_code . ' - ' . $shipping_method_code);        
        
        $order->save();
        //=========================================================================================
        //Update shipping address
        $shipping_address_id = $order->getShippingAddress()->getId();

        $shipAddress = $this->repositoryAddress->get($shipping_address_id);
        
        if ($shipAddress->getId())
        {
            $shipAddress->setStreet($shipping_address1 . ', ' . $shipping_address2);
            $shipAddress->setTelephone($shipping_phone);
            $shipAddress->setPostcode($shipping_postcode);     
            $shipAddress->setCity($shipping_city);
            $shipAddress->setFirstname($shipping_first_name);
            $shipAddress->setLastname($shipping_last_name);
            $this->repositoryAddress->save($shipAddress);
        }

        $billing_address_id = $order->getBillingAddress()->getId();

        $billAddress = $this->repositoryAddress->get($billing_address_id);
        
        if ($billAddress->getId())
        {
            $billAddress->setStreet($billing_address1 . ', ' . $billing_address2);
            $billAddress->setTelephone($billing_phone);
            $billAddress->setPostcode($billing_postcode);    
            $billAddress->setCity($billing_city);
            $billAddress->setFirstname($billing_first_name);
            $billAddress->setLastname($billing_last_name);
            $this->repositoryAddress->save($billAddress);
        }

        //=========================================================================================
        $resultJson = array(
            'grand_total' => $grand_total,
            'subtotal' => number_format($subtotal, 2),
            'discount_amount' => number_format($discount_amount, 2),
            'shipping_amount' => number_format($shipping_amount, 2),
            'tax_amount' => number_format($tax_amount, 2)
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

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

