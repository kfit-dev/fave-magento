<?php
    namespace Fave\PaymentGateway\Helper;
    
    use \Magento\Framework\App\Helper\AbstractHelper;
    
    class Data extends AbstractHelper
    {

        public function __construct(
            \Magento\Framework\App\Helper\Context $context,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Magento\Catalog\Model\ProductFactory $product,
            \Magento\Framework\Data\Form\FormKey $formkey,
            \Magento\Quote\Model\QuoteFactory $quote,
            \Magento\Quote\Model\QuoteManagement $quoteManagement,
            \Magento\Customer\Model\CustomerFactory $customerFactory,
            \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
            \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
            \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
            \Magento\Sales\Model\Service\OrderService $orderService,
            \Magento\Sales\Model\Order $order
            )
        {
            $this->_storeManager = $storeManager;
            $this->_product = $product;
            $this->_formkey = $formkey;
            $this->quote = $quote;
            $this->quoteManagement = $quoteManagement;
            $this->customerFactory = $customerFactory;
            $this->customerRepository = $customerRepository;
            $this->orderService = $orderService;
            $this->cartRepositoryInterface = $cartRepositoryInterface;
            $this->cartManagementInterface = $cartManagementInterface;
            $this->order = $order;
            parent::__construct($context);
        }

        public function createOrder($orderData) {
            $store=$this->_storeManager->getStore();
            $websiteId = $this->_storeManager->getStore()->getWebsiteId();
            $customer=$this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($orderData['email']);// load customer by email address
            
            if (!$customer->getEntityId()) {
                $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderData['shipping_address']['firstname'])
                ->setLastname($orderData['shipping_address']['lastname'])
                ->setEmail($orderData['email'])
                ->setPassword($orderData['email']);
                $customer->save();
            }
   
            $cartId = $this->cartManagementInterface->createEmptyCart(); //Create empty cart
            $quote = $this->cartRepositoryInterface->get($cartId); // load empty cart quote
            $quote->setStore($store);
            
            // if you have allready buyer id then you can load customer directly 
            $customer= $this->customerRepository->getById($customer->getEntityId());
            $quote->setCurrency();
            $quote->assignCustomer($customer); //Assign quote to customer

            //add items in quote
            foreach($orderData['items'] as $item){
                $product = $this->_product->create()->load($item['product_id']);
                $product->setPrice($item['price']);
                $quote->addProduct(
                      $product,
                      intval($item['qty'])
                );
           }
            
            //Set Address to quote
            $quote->getBillingAddress()->addData($orderData['shipping_address']);
            $quote->getShippingAddress()->addData($orderData['shipping_address']);
            
            // Collect Rates and Set Shipping & Payment Method      
            $shippingAddress=$quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)
                            ->collectShippingRates()
                            ->setShippingMethod('freeshipping_freeshipping'); // set shipping method
            
            $quote->setPaymentMethod('fave_gateway'); // set payment method
            
            $quote->setInventoryProcessed(false); // used to manage inventory, if set true then it update inventory after successful order.
            
            $quote->save(); // Save quote
            
            // Set Sales Order Payment
            $quote->getPayment()->importData(['method' => 'fave_gateway']);
            
            // Collect Totals
            $quote->collectTotals();

            // Create Order From Quote
            $quote = $this->cartRepositoryInterface->get($quote->getId());
            $orderId = $this->cartManagementInterface->placeOrder($quote->getId());
            $order = $this->order->load($orderId);
            
            $order->setEmailSent(0); // Set 1 to sent notification email for create order.
            
            $increment_id = $order->getRealOrderId();
            
            if ($order->getEntityId()) {
                $result['order_id']= $order->getRealOrderId();
            } else {
                $result=['error'=>1,'msg'=>'Error while creating order'];
            }
            
            return $result;
        }
 
    }