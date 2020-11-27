<?php

namespace App\Services\payments;


use App\Models\Invoice;
use App\PaymentNotificationInterface;
use Exception;

class YandexKassaProvider extends BasicProvider
{

    const PAYMENTS_ENDPOINT_URL = 'https://payment.yandex.net/api/v3/payments';

    public $shopId = null;
    public $secretKey = null;
    public $sum = 0;
    public $currency = 'RUB';
    public $capture = true;
    public $return_url = null;
    public $description = 'Payment';
    public $idempotenceKey = null;
    public $paymentId = null;
    public $count = 0;
    public $type_service = null;
    public $email = null;
    public $orderId = null;


    public function __construct()
    {
        $config = app('config')->get('payment');
        $this->shopId = $config['provider']['yandexKassa']['shopId'] ?? null;
        $this->secretKey = $config['provider']['yandexKassa']['secret'] ?? null;
    }


    public function getPaymentConfirmation(): PaymentConfirmation
    {

        if (empty($this->sum) || empty($this->return_url) || empty($this->idempotenceKey)) {
            throw new \Exception('Нет обязательных параметров: sum' . empty($this->sum)
                . ' return_url: ' . empty($this->return_url) . ' idempotenceKey: ' . empty($this->idempotenceKey));
        }

        $response = $this::request(
            'POST',
            self::PAYMENTS_ENDPOINT_URL,
            ['username' => $this->shopId, 'password' => $this->secretKey],
            json_encode([
                'amount' => [
                    'value' => $this->sum,
                    'currency' => $this->currency,
                ],
                'capture' => $this->capture,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $this->return_url,
                ],
                'metadata' => [
                    'order_id' => $this->orderId,
                ],
                'description' => $this->description,
                "receipt" => [
                    "customer" => [
                        "email" => $this->email,
                    ],
                    "items" => [
                        [
                            "description" => 'Оплата услуг по отправке SMS сообщений',
                            "quantity" => 1,
                            "amount" => [
                                "value" => $this->sum,
                                "currency" => $this->currency,
                            ],
                            "vat_code" => "1",
                            "payment_mode" => "full_prepayment",
                            "payment_subject" => "service"
                        ],
                    ]
                ]
            ]),
            [
                "Idempotence-Key:{$this->idempotenceKey}",
                "Content-Type:application/json",
            ]
        );


        if (!isset($response['confirmation']['confirmation_url'])) {
            throw new Exception('Яндекс Касса не вернула url для оплаты');
        }

        $confirmation = new PaymentConfirmation();
        $confirmation->paymentId = $response['id'] ?? null;
        $confirmation->confirmationUrl = $response['confirmation']['confirmation_url'];

        return $confirmation;
    }


    public function handleRequest(PaymentNotificationInterface $notify): array
    {
        if ($notify->event === 'payment.succeeded') {
            return $notify->applyPayment();
        } elseif ($notify->event === 'payment.waiting_for_capture') {
            return $notify->pendingPayment();
        } elseif ($notify->event === 'payment.canceled') {
            return $notify->canceledPayment();
        } elseif ($notify->event === 'refund.succeeded') {
            return $notify->refundPayment();
        }

        return [];
    }

    public function getPaymentStatus(): int
    {
        $response = $this::request(
            'GET',
            static::PAYMENTS_ENDPOINT_URL . '/' . $this->paymentId,
            ['username' => $this->shopId, 'password' => $this->secretKey],
            null,
            null
        );

        if (!isset($response['status'])) {
            return Invoice::STATUS_FAIL;
        }

        switch ($response['status']) {
            case 'waiting_for_capture':
                return Invoice::STATUS_WAITING_CAPTURE;
            case 'pending':
                return Invoice::STATUS_PENDING_PAY;
            case 'succeeded':
                return Invoice::STATUS_PAID;
            case 'canceled':
                return Invoice::STATUS_CANCELED;
            default:
                return Invoice::STATUS_FAIL;
        }
    }
}
