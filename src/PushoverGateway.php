<?php

/*
 * This file is part of NotifyMe.
 *
 * (c) Cachet HQ <support@cachethq.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NotifyMeHQ\Pushover;

use GuzzleHttp\Client;
use NotifyMeHQ\NotifyMe\Arr;
use NotifyMeHQ\Contracts\GatewayInterface;
use NotifyMeHQ\NotifyMe\HttpGatewayTrait;
use NotifyMeHQ\NotifyMe\Response;

class PushoverGateway implements GatewayInterface
{
    use HttpGatewayTrait;

    /**
     * Gateway api endpoint.
     *
     * @var string
     */
    protected $endpoint = 'https://api.pushover.net';

    /**
     * Pushover api version.
     *
     * @var string
     */
    protected $version = '1';

    /**
     * Pushover allowed sounds.
     *
     * @var string[]
     */
    protected $allowedSounds = [
        'pushover',
        'bike',
        'bugle',
        'cashregister',
        'classical',
        'cosmic',
        'falling',
        'gamelan',
        'incoming',
        'intermission',
        'magic',
        'mechanical',
        'pianobar',
        'siren',
        'spacealarm',
        'tugboat',
        'alien',
        'climb',
        'persistent',
        'echo',
        'updown',
        'none',
    ];

    /**
     * The http client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Configuration options.
     *
     * @var string[]
     */
    protected $config;

    /**
     * Create a new pushover gateway instance.
     *
     * @param \GuzzleHttp\Client $client
     * @param string[]           $config
     *
     * @return void
     */
    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Send a notification.
     *
     * @param string   $to
     * @param string   $message
     * @param string[] $options
     *
     * @return \NotifyMeHQ\Contracts\ResponseInterface
     */
    public function notify($to, $message, array $options = [])
    {
        $options['to'] = $to;

        $params = $this->addMessage($message, $params, $options);

        return $this->commit('post', $this->buildUrlFromString('messages.json'), $params);
    }

    /**
     * Add a message to the request.
     *
     * @param string   $message
     * @param string[] $params
     * @param string[] $options
     *
     * @return array
     */
    protected function addMessage($message, array $params, array $options)
    {
        $params['token'] = Arr::get($options, 'token', $this->config['token']);
        $params['user'] = Arr::get($options, 'to', '');
        $params['device'] = Arr::get($options, 'device', '');
        $params['title'] = Arr::get($options, 'title', '');
        $params['message'] = $message;

        if (isset($params['sound'])) {
            $params['sound'] = in_array($params['sound'], $this->allowedSounds) ? $params['sound'] : 'pushover';
        }

        return $params;
    }

    /**
     * Commit a HTTP request.
     *
     * @param string   $method
     * @param string   $url
     * @param string[] $params
     * @param string[] $options
     *
     * @return mixed
     */
    protected function commit($method = 'post', $url, array $params = [], array $options = [])
    {
        $success = false;

        $rawResponse = $this->client->{$method}($url, [
            'exceptions'      => false,
            'timeout'         => '80',
            'connect_timeout' => '30',
            'headers'         => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $params,
        ]);

        if ($rawResponse->getStatusCode() == 200) {
            $response = $this->parseResponse($rawResponse->getBody());
            $success = (bool) $response['status'];
        } else {
            $response = $this->responseError($rawResponse);
        }

        return $this->mapResponse($success, $response);
    }

    /**
     * Map HTTP response to response object.
     *
     * @param bool  $success
     * @param array $response
     *
     * @return \NotifyMeHQ\Contracts\ResponseInterface
     */
    protected function mapResponse($success, $response)
    {
        return (new Response())->setRaw($response)->map([
            'success' => $success,
            'message' => $success ? 'Message sent' : implode(', ', $response['errors']),
        ]);
    }

    /**
     * Get the default json response.
     *
     * @param string $rawResponse
     *
     * @return array
     */
    protected function jsonError($rawResponse)
    {
        $msg = 'API Response not valid.';
        $msg .= " (Raw response API {$rawResponse->getBody()})";

        return [
            'errors' => [$msg],
        ];
    }

    /**
     * Get the request url.
     *
     * @return string
     */
    protected function getRequestUrl()
    {
        return $this->endpoint.'/'.$this->version;
    }
}
