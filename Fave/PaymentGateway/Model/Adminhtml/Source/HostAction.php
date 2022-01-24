<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fave\PaymentGateway\Model\Adminhtml\Source;
 
use Magento\Payment\Model\Method\AbstractMethod;
 
/**
 * Class HostAction
 */
class HostAction implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'https://omni.myfave.com/',
                'label' => __('Production (https://omni.myfave.com)')
            ],
            [
                'value' => 'https://omni.app.fave.ninja/',
                'label' => __('Staging (https://omni.app.fave.ninja)')
            ]
        ];
    }
}
 