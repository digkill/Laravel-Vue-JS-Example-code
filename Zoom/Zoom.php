<?php

namespace App\Services\Zoom;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class Zoom implements ZoomInterface
{

    private $client;

    /**
     * Zoom constructor.
     * @param Client $client
     */
    function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function capture(Client $client)
    {
        return new self($client);
    }

    /**
     * @return string
     */
    private function generateZoomToken()
    {
        $key = env('ZOOM_API_KEY', '');
        $secret = env('ZOOM_API_SECRET', '');
        $payload = [
            'iss' => $key,
            'exp' => strtotime('+1 minute'),
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * @return mixed
     */
    private function retrieveZoomUrl()
    {
        return env('ZOOM_API_URL', 'https://api.zoom.us/v2/');
    }

    /**
     * @param string $path
     * @param array $options
     * @param string $method
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function makeRequest(string $path, array $options = [], string $method = 'POST')
    {
        $url = $this->retrieveZoomUrl();
        $jwt = $this->generateZoomToken();

        $pathUrl = $url . $path;

        $res = $this->client->request(
            $method,
            $pathUrl,
            array_merge([
                'headers' => [
                    'authorization' => 'Bearer ' . $jwt,
                    'content-type' => 'application/json'
                ],

            ], $options)
        );

        return $res->getBody()->getContents();
    }

    /**
     * @param string $path
     * @param array $query
     * @return string
     */
    public function zoomGet(string $path, array $query = [])
    {
        return $this->makeRequest($path, ['query' => $query], 'GET');
    }

    /**
     * @param string $path
     * @param array $body
     * @return string
     */
    public function zoomPost(string $path, array $body = [])
    {
        return $this->makeRequest($path, ['json' => $body]);
    }

    /**
     * @param string $path
     * @param array $body
     * @return string
     */
    public function zoomPatch(string $path, array $body = [])
    {
        return $this->makeRequest($path, ['body' => $body], 'PATCH');
    }

    /**
     * @param string $path
     * @param array $body
     * @return string
     */
    public function zoomDelete(string $path, array $body = [])
    {
        return $this->makeRequest($path, ['body' => $body], 'DELETE');
    }

    /**
     * @param string $dateTime
     * @return string
     */
    public function toZoomTimeFormat(string $dateTime)
    {
        try {
            $date = new \DateTime($dateTime);
            return $date->format('Y-m-d\TH:i:s');
        } catch (\Exception $e) {
            Log::error('ZoomJWT->toZoomTimeFormat : ' . $e->getMessage());
            return '';
        }
    }

    /**
     * @param string $dateTime
     * @param string $timezone
     * @return int|string
     */
    public function toUnixTimeStamp(string $dateTime, string $timezone)
    {
        try {
            $date = new \DateTime($dateTime, new \DateTimeZone($timezone));
            return $date->getTimestamp();
        } catch (\Exception $e) {
            Log::error('ZoomJWT->toUnixTimeStamp : ' . $e->getMessage());
            return '';
        }
    }
}
