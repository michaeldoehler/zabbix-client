<?php

namespace kamermans\ZabbixClient;

use Graze\GuzzleHttp\JsonRpc\Client as RpcClient;
use GuzzleHttp\Psr7\Stream as GuzzleStream;
use Psr\Http\Message\RequestInterface as HttpRequestInterface;

class ZabbixClient {

    private $client;
    private $auth_token;
    private $request_id;

    public function __construct($url, $user, $pass) {
        $this->request_id = time() * 10;
        $this->client = RpcClient::factory($url);
        $this->login($user, $pass);
    }

    public function request($method, $params=[]) {
        $request = $this->client->request(++$this->request_id, $method, $params);
        if ($this->auth_token !== null) {
            $request = $this->authenticateRequest($request);
        }
        return new ApiResponse($this->client->send($request));
    }

    public function ping() {
        $start = microtime(true);
        $version = $this->request('apiinfo.version');
        return round((microtime(true) - $start) * 100, 2);
    }

    private function authenticateRequest(HttpRequestInterface $request) {
        $body = json_decode((string)$request->getBody(), true);
        $body['auth'] = $this->auth_token;
        $json_body = self::jsonEncode($body);
        $request->withBody(\GuzzleHttp\Psr7\stream_for($json_body));
        return $request;
    }

    private function login($user, $pass) {
        try {
            $this->auth_token = $this->request('user.login', [
                'user' => $user,
                'password' => $pass,
            ])->getResult();
        } catch (ApiException $e) {
            throw new AuthException($e->getResponse());
        }
    }
    /**
     * Wrapper for json_encode that includes character escaping by default
     *
     * @param  mixed          $data
     * @param  boolean        $escapeChars
     * @return string|boolean
     */
    public static function jsonEncode($data, $escapeChars = true)
    {
        $options =
            \JSON_HEX_AMP  |
            \JSON_HEX_APOS |
            \JSON_HEX_QUOT |
            \JSON_HEX_TAG  |
            \JSON_UNESCAPED_UNICODE |
            \JSON_UNESCAPED_SLASHES;
        return \json_encode($data, $escapeChars ? $options : 0);
    }

}
