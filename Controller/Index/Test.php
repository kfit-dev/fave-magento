<?php
namespace Fave\PaymentGateway\Controller\Index;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Payment\Gateway\ConfigInterface;

class Test extends \Magento\Framework\App\Action\Action
{
	protected $_pageFactory;
	protected $_curl;
    private $checkoutSession;
    protected $_storeManager;
    protected $orderRepository;
    private $config;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $pageFactory,
		\Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ConfigInterface $config,
        Session $checkoutSession)
	{
		$this->_pageFactory = $pageFactory;
		$this->_curl = $curl;
        $this->_storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
		return parent::__construct($context);
	}

	public function execute()
	{
		//echo "Hello World";
        //Get order details
        $order = $this->checkoutSession->getLastRealOrder();
        $order_id = $order->getId(); //order ID
        $order_amount = $order->getGrandTotal(); //Order amount
        $order_amount_cents = isset($order_amount) ? (int) $order_amount * 100 : null;
        //$order_currency = $order->getBaseCurrenyCode(); //order currency code
        $customer_email = $order->getCustomerEmail(); //customer email ID

        $order_currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();

        $payment = $order->getPayment();
        $trxId = $payment->getLastTransId();

        $additional_info = $order->getPayment()->getAdditionalInformation();
        $gateway_url = isset($additional_info) ? $additional_info['code'] : null;

		$url = "https://omni.app.fave.ninja/api/fpo/v1/sg/qr_codes";
		$params = array(
            'omni_reference'     => uniqid('PL-magento'),
            'total_amount_cents' => 1000,
            'app_id'             => "a3osyvuayt",
            'outlet_id'          => 11633,
            'redirect_url'       => "http://sonal.magento.com/checkout/onepage/success/",
            'callback_url'       => "https://d933d1eb83c039172e45161c68bf0c2c.m.pipedream.net",
            'format'             => 'web_url',
        );

		// Generate API signature
        // $params['sign'] = $this->generate_api_signature( $params );

		// $this->_curl->post($url, $params);
		// $result = $this->_curl->getBody();
		// $result = json_decode($result, true);
		// $code = isset($result['code']) ? $result['code'] : null;
        // $this->_redirect($code);
        $this->_redirect($gateway_url);
        //$this->messageManager->addError(__('Payment has been cancelled.'));
        //$this->_redirect('http://sonal.magento.com/checkout/onepage/success/');
		//exit;
	}

	// Generate API signature
    private function generate_api_signature( array $params ) {

        unset( $params['sign'] );

        foreach ( $params as $key => $value ) {
            if ( is_array( $value ) ) {
                $params[ $key ] = $this->format_api_signature_array_params( $value );
            }
        }

        $encoded_params = http_build_query( $params );
        $api_key = "d25f1p1zf6ww8eja";

        return hash_hmac( 'sha256', $encoded_params, $api_key );

    }

	// Format array parameters for API signature
    private function format_api_signature_array_params( $params ) {

        $formatted_params = array();

        // Format array to json-like
        // from ['abc'=>'def'] to {'abc'=>'def'}
        foreach ( $params as $key => $value) {
            if ( is_array( $value ) ) {
                $formatted_params[] = '"' . $key . '"=>' . $this->format_api_signature_array_params( $value );
            } else {
                $formatted_params[] = '"' . $key . '"=>"' . $value . '"';
            }
        }

        return '{' . implode( ', ', $formatted_params ) . '}';

    }
}

