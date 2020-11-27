<?php

namespace App\Services\payments;


use App\Invoice;
use App\PaymentNotificationInterface;

abstract class BasicProvider implements Provider
{

    public function request($method, $endpointUrl, $credentials = [], $data = null, $headers = [])
    {
        if (!in_array(strtolower($method), ['get', 'post'])) return null;

        return static::jsonRequest($method, $endpointUrl, $credentials, $data, $headers);
    }

    public static function updatedEndpoint($endpointUrl, $data)
    {
        if (!is_array($data)) return $endpointUrl;

        $parsedUrl = parse_url($endpointUrl);
        $urlParams = explode($parsedUrl['query']);
        $urlParamsAssoc = [];
        foreach ($urlParams as $param) {
            $parts = explode('=', $param);
            $urlParamsAssoc[$parts[0]] = $parts[1];
        }

        $urlParamsAssoc = array_merge($data, $urlParamsAssoc);

        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/' . $parsedUrl['path'] . '?' . http_build_query($urlParamsAssoc);
    }

    public static function jsonRequest($method, $endpoint, $credentials = [], $data = null, $headers = [])
    {
        if (strtolower($method) === 'get') {
            $endpoint = static::updatedEndpoint($endpoint, $data);
            $data = [];
        }

        return json_decode(static::curlRequest($method, $endpoint, $credentials, $data, $headers), true);
    }

    public static function curlRequest($method, $endpoint, $credentials = [], $data = null, $headers = [])
    {
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => (strtolower($method) === 'post' ? 1 : 0),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_FOLLOWLOCATION => 1,
        ];
        if (!empty($data)) {
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
        }
        curl_setopt_array($curl, $options);
        if (env('APP_DEBUG')) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
        }

        if (!empty($credentials) && isset($credentials['username']) && isset($credentials['password'])) {
            curl_setopt($curl, CURLOPT_USERPWD, "{$credentials['username']}:{$credentials['password']}");
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    public function getPaymentConfirmationObject(): PaymentConfirmation
    {
        // TODO: Implement createPayment() method.
        return new PaymentConfirmation();
    }

    public function handleRequest(PaymentNotificationInterface $notify): array
    {
        // TODO: Implement handleRequest() method.
        return [];
    }

    public function getPaymentStatus(): int
    {
        return Invoice::STATUS_FAIL;
    }
}
