<?php
namespace Fave\PaymentGateway\Block;

use Magento\Payment\Gateway\ConfigInterface;
 
class Thankyou extends \Magento\Sales\Block\Order\Totals
{
    protected $checkoutSession;
    protected $customerSession;
    protected $_orderFactory;
    protected $_storeManager;
    protected $_messageManager;
    private $order;
    private $config;
   
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Api\Data\OrderInterface $order,
        ConfigInterface $config,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->_storeManager = $storeManager;
        $this->_messageManager = $messageManager;
        $this->order = $order;
        $this->config = $config;
    }
 
    public function getRealOrderId()
    {
        $lastorderId = $this->_checkoutSession->getLastOrderId();
        return $lastorderId;
    }
 
    public function getOrder()
    {
        if ($this->_checkoutSession->getLastRealOrderId()) {
            $order = $this->order->loadByIncrementId($this->_checkoutSession->getLastRealOrderId());
            return $order;
       }
       return false;
    }
 
    public function getCustomerId()
    {
        return $this->customerSession->getCustomer()->getId();
    }
 
    public function getShippingInfo()
    {
        $order = $this->getOrder();
       
        if($order) {
            $address = $order->getShippingAddress();    
 
            return $address;
        }
        return false;
    }
 
    public function getStatus()
    {
        $order = $this->getOrder();
        //$additional_info = $order->getPayment()->getAdditionalInformation();
        //$status_code = isset($additional_info) ? $additional_info['status_code'] : null;
 
        $route_params = $this->getRequest()->getParams();
        $status = isset($route_params['status']) ? $route_params['status'] : null;
       
        if (!empty($status)) {
            if ($status == "rejected") {
              $this->_messageManager->addError('Payment was declined.');
            }
        }

        //Fastpay
        $omni_ref = isset($route_params['omni_ref']) ? preg_replace('/\s/', '+', $route_params['omni_ref']) : null;

        if (!empty($omni_ref)) {           
           $store_id = $this->_storeManager->getStore()->getStoreId();
           $api_key = $this->config->getValue('private_api_key', $store_id);
           $cipher = "aes-128-cbc";
           $iv = null;
   
           $decrypted_data = openssl_decrypt($omni_ref, $cipher, $api_key, 0, $iv);
           parse_str($decrypted_data, $query_results); 
           $status = $query_results['status'];
        }

       return $status;
    }
 
    public function getFormattedPrice()
    {
        $order = $this->getOrder();
       
        if($order) {
            $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
            $formatted_price = $currency_code . ' ' . number_format((float)$order->getGrandTotal(), 2, '.', '');
            return $formatted_price;
        }
        return false;
    }
 
    public function getContinueUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
 