<?php
namespace Fave\PaymentGateway\Controller\Callback;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\DB\Transaction;
use Magento\Checkout\Model\Session;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
	protected $_pageFactory;
	protected $_curl;
    protected $_storeManager;
    protected $orderRepository;
    protected $transactionBuilder;
    protected $invoiceService;
    protected $invoiceSender;
    private $resultJsonFactory;
    private $config;
    private $order;
    private $checkoutSession;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $pageFactory,
		\Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        InvoiceService $invoiceService,
		InvoiceRepositoryInterface $invoiceRepository,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        JsonFactory $resultJsonFactory,
        ConfigInterface $config,
        \Magento\Sales\Api\Data\OrderInterface $order,
        Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager)
	{
		$this->_pageFactory = $pageFactory;
		$this->_curl = $curl;
        $this->_storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->invoiceService = $invoiceService;
		$this->invoiceRepository = $invoiceRepository;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->order = $order;
        $this->checkoutSession = $checkoutSession;
		return parent::__construct($context);
	}

    public function execute()
	{
        $post = $this->getRequest()->getContent();
        $array = json_decode($post, true);
        $omni_reference = $array['omni_reference'];
        $statusCode = $array['status_code'];

        $route_params = $this->getRequest()->getParams();
        $orderId = $route_params['order_id'];
        
        $order = $this->order->loadByIncrementId($orderId);
        
        $payment = $order->getPayment(); 
        $trxId = $payment->getLastTransId();
        $additional_info = $payment->getAdditionalInformation();

        if (array_key_exists('status_code', $additional_info)) {
            $comments = 'This transaction has been acknowledged.';
        }
        else {
            $comments = $this-> addTransactionToOrder($orderId, $order, $statusCode);
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'message' => $comments
        ]);

        exit;
	}

    public function addTransactionToOrder($orderId, $order, $statusCode) {
        try {

            
            // Prepare payment object
            $payment = $order->getPayment();
            $trxId = $payment->getLastTransId();
            $uuid = $trxId . '-' . $orderId;
    
            $payment->setParentTransactionId($trxId);
            $payment->setLastTransId($uuid);
            $payment->setTransactionId($uuid);
            $payment->setAdditionalInformation('status_code', $statusCode);
			$payment->canRefund();
            

            $trxId = $payment->getLastTransId();
            $orderCurrencyCode = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();

            $formatedPrice = $orderCurrencyCode . ' ' . number_format((float)$order->getGrandTotal(), 2, '.', '');            
 
            // Prepare transaction
            $transaction = $this->transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($uuid)
            ->setFailSafe(true)
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                        
            if ($statusCode == "4") {
                $this->cancelOrder($order);
                $comments = __('Payment of %1 was declined.', $formatedPrice);
            }
            elseif ($statusCode == "2") {
                $comments = __('Payment of %1 has been completed successfully.', $formatedPrice);
				$this-> createInvoice($order, $formatedPrice);
            }
            else {
                $comments = __('There was a problem processing the payment of %1. Please contact Fave for more information', $formatedPrice);
            }

            $payment->addTransactionCommentsToOrder($transaction, $comments);
            $payment->save();
 
            return  $comments;

        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }
    }

    private function cancelOrder($order) {
        if ($order->canCancel()) {
            try {
                $order->cancel();
        
                // remove status history set in _setState
                $order->getStatusHistoryCollection(true);      
                $order->save();
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
            }
        }
    }

	public function createInvoice($order, $formatedPrice) {
        // Prepare the invoice
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->setState(Invoice::STATE_PAID);
        $invoice->setTransactionId($order->getPayment()->getLastTransId());
        $invoice->setBaseGrandTotal((float)$order->getGrandTotal());
        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);
        $invoice->pay();


        // Create the transaction
        $transactionSave = $this->transaction
        ->addObject($invoice)
        ->addObject($order);
        $transactionSave->save();

        // Update the order
        $order->setTotalPaid($order->getTotalPaid());
        $order->setBaseTotalPaid($order->getBaseTotalPaid());
        $order->save();  

        // Save the invoice
        $this->invoiceRepository->save($invoice);
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

