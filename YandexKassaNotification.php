<?php

namespace App\Services\payments;

use App\InvoiceInterface;
use App\PaymentNotificationInterface;

class YandexKassaNotification implements PaymentNotificationInterface
{
    public $type;
    public $event;
    public $object;

    /**
     * @var InvoiceInterface $invoice
     */
    public $invoice;

    public function applyPayment()
    {
        return $this->invoice->applyPayment();
    }

    public function pendingPayment()
    {
        return $this->invoice->pendingPayment();
    }

    public function canceledPayment()
    {
        return $this->invoice->canceledPayment();
    }

    public function refundPayment()
    {
        return $this->invoice->refundPayment();
    }
}
