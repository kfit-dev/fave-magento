<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fave\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class VoidRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;
    protected $_storeManager;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->_storeManager = $storeManager;
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

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];

        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        $store_id = $this->_storeManager->getStore()->getStoreId();
        $store_url = $this->_storeManager->getStore()->getBaseUrl();
        $currency_code = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $country_code = strtolower(substr($currency_code, 0, 2));
    
        $trx_id = $payment->getParentTransactionId();
        $omni_reference = explode("-", $trx_id, 3)[0] . '-' . explode("-", $trx_id, 3)[1]    ;

        $host = $this->config->getValue('host', $store_id);
        $endpoint = $host . 'api/fpo/v1/' . $country_code . '/transactions';

        $params = array(
            'omni_reference'     => $omni_reference,
            'app_id'             => $this->config->getValue('merchant_gateway_key', $store_id),
            'status'             => 'refunded',
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
        //$api_key = "d25f1p1zf6ww8eja";
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
}
