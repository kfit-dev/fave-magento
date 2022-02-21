<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fave\PaymentGateway\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Checkout\Model\Session;

class ClientMock implements ClientInterface 
{
    const SUCCESS = 1;
    const FAILURE = 0;
    protected $_curl;
    protected $_messageManager;
    protected $_storeManager;
    private $config;
    private $checkoutSession;

    /**
     * @var array
     */
    private $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ConfigInterface $config,
        Session $checkoutSession
    ) {
        $this->logger = $logger;
        $this->_curl = $curl;
        $this->_messageManager = $messageManager;
        $this->_storeManager = $storeManager;
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $endpoint = isset($request['endpoint']) ? $request['endpoint'] : null;
        unset($request['endpoint']);
        $this->_curl->post($endpoint, $request);


		$response = $this->_curl->getBody();
		$response = json_decode($response, true);
        $code = array_key_exists('code', $response) ?  $response['code'] : null;

        $response['omni_reference'] = $request['omni_reference'];

        $this->logger->debug(
            [
                'request' => $transferObject->getBody(),
                'response' => $response
            ]
        );

        $http_status_code = $this->_curl->getStatus();
        //$http_status_code = 400;

        if ($http_status_code != 201) {
            if (array_key_exists('error_description', $response)) {
                throw new \InvalidArgumentException('Operation failed. ' . $response['error_description']);
            }
            else {
                throw new \InvalidArgumentException('There was a problem processing the payment.');
            }
            
        }

        return $response;
    }

    /**
     * Generates response
     *
     * @return array
     */
    protected function generateResponseForCode($resultCode)
    {

        return array_merge(
            [
                'RESULT_CODE' => $resultCode,
                'TXN_ID' => $this->generateTxnId()
            ],
            $this->getFieldsBasedOnResponseType($resultCode)
        );
    }

    /**
     * @return string
     */
    protected function generateTxnId()
    {
        return uniqid('PL-SG');
    }

    /**
     * Returns result code
     *
     * @param TransferInterface $transfer
     * @return int
     */
    private function getResultCode(TransferInterface $transfer)
    {
        $headers = $transfer->getHeaders();

        if (isset($headers['force_result'])) {
            return (int)$headers['force_result'];
        }

        return $this->results[random_int(0, 1)];
    }

    /**
     * Returns response fields for result code
     *
     * @param int $resultCode
     * @return array
     */
    private function getFieldsBasedOnResponseType($resultCode)
    {
        switch ($resultCode) {
            case self::FAILURE:
                return [
                    'FRAUD_MSG_LIST' => [
                        'Stolen card',
                        'Customer location differs'
                    ]
                ];
        }

        return [];
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
