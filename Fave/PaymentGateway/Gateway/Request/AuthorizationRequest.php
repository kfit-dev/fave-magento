<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fave\PaymentGateway\Gateway\Request;
 
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Checkout\Model\Session;
 
class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;
    protected $_storeManager;
    private $checkoutSession;
    protected $_appState;
 
    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Session $checkoutSession,
        \Magento\Framework\App\State $appState
    ) {
        $this->config = $config;
        $this->_storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->_appState = $appState;
    }
 
    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
 
        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $address = $order->getShippingAddress();
        $order_amount = $order->getGrandTotalAmount();
        $order_amount_cents = isset($order_amount) ? $order_amount * 100 : null;
 
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $store_url = $this->_storeManager->getStore()->getBaseUrl();
        $identifier = strtok(parse_url($store_url)['host'], '.');
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));
       
       
        //$prefix = strtoupper($this->config->getValue('prefix', $store_id));
        $prefix = $this->get_prefix($country_code);
       
        $omni_reference = uniqid($prefix . '-' . $country_code . substr($identifier, 0, 5));
        $redirect_url = $store_url . 'checkout/onepage/success?country=' . $country_code;
        $reserved_order_id = $this->checkoutSession->getQuote()->getReservedOrderId();
       
       
        //$store_url =  'https://2125-42-191-231-233.ngrok.io/';
        $callback_url = $store_url . 'paymentgateway/callback/index?order_id=' . $reserved_order_id;
        //$callback_url = "https://d933d1eb83c039172e45161c68bf0c2c.m.pipedream.net";
 
 
        $host = $this->config->getValue('host', $store_id);
        $endpoint = $host . 'api/fpo/v1/' . $country_code . '/qr_codes';
 
        $params = array(
            'omni_reference'     => $omni_reference,
            'total_amount_cents' => $order_amount_cents,
            'app_id'             => $this->config->getValue('merchant_gateway_key', $store_id),
            'outlet_id'          => $this->config->getValue('outlet_id', $store_id),
            'redirect_url'       => $redirect_url,
            'callback_url'       => $callback_url,
            'format'             => 'web_url',
            'client_integration' => 'magento',
            'endpoint'           => $endpoint,
        );
 
        $params['sign'] = $this->generate_api_signature($params, $store_id);
 
        return $params;
    }
 
    // Generate API signature
    private function generate_api_signature(array $params, $store_id) {
 
        unset( $params['sign'] );
        unset( $params['endpoint'] );
 
        foreach ( $params as $key => $value ) {
            if ( is_array( $value ) ) {
                $params[ $key ] = $this->format_api_signature_array_params( $value );
            }
        }
 
        $encoded_params = http_build_query( $params );
        $api_key = $this->config->getValue('private_api_key', $store_id);
 
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
 
    private function get_prefix($country_code) {
        if ($country_code == "my") {
            $prefix = "FPO";
        }
        else {
            $prefix = "FPO";
        }
        return $prefix;
    }
}
