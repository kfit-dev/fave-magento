<?php
namespace Fave\PaymentGateway\Controller\Fastpay;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;

class CustomerToken extends \Magento\Customer\Controller\AbstractAccount
{
	protected $_customerSession;
    
	public function __construct(
        Context $context,
        Session $customerSession,
        \Magento\Integration\Model\Oauth\TokenFactory $tokenModelFactory
    ) {
        $this->_customerSession = $customerSession;
        $this->_tokenModelFactory = $tokenModelFactory;
        parent::__construct($context);
    }

	public function execute()
    {
        $this->getToken();
    }

    public function getToken()
    {
        $customerId = $this->_customerSession->getCustomer()->getId();
        $customerToken = $this->_tokenModelFactory->create();
        echo "Customer-token=> ".$tokenKey = $customerToken->createCustomerToken($customerId)->getToken();
        return $tokenKey;
    }
}

