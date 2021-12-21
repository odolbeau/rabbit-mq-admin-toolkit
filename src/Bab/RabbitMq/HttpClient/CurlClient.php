<?php

namespace Bab\RabbitMq\HttpClient;

use Bab\RabbitMq\HttpClient;

class CurlClient implements HttpClient
{
    private $host;
    private $port;
    private $user;
    private $pass;

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $verb, string $uri, array $parameters = null): string
    {
        $handle = curl_init();

        curl_setopt_array($handle, [
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            \CURLOPT_PORT => $this->port,
            \CURLOPT_VERBOSE => false,
            \CURLOPT_HEADER => false,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_USERPWD => sprintf(
                '%s:%s',
                $this->user,
                $this->pass
            ),
        ]);

        curl_setopt($handle, \CURLOPT_URL, $this->host.$uri);

        if ('GET' === $verb) {
            curl_setopt($handle, \CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($handle, \CURLOPT_CUSTOMREQUEST, $verb);
        }

        if (null !== $parameters) {
            curl_setopt($handle, \CURLOPT_POSTFIELDS, json_encode($parameters));
        } elseif ('GET' !== $verb && 'DELETE' !== $verb) {
            curl_setopt($handle, \CURLOPT_POSTFIELDS, '{}');
        }

        $response = curl_exec($handle);
        if (false === $response) {
            throw new \RuntimeException(sprintf('Curl error: %s', curl_error($handle)));
        }

        $httpCode = curl_getinfo($handle, \CURLINFO_HTTP_CODE);

        if (!\in_array($httpCode, [200, 201, 204])) {
            throw new \RuntimeException(sprintf('Receive code %d instead of 200, 201 or 204. Url: %s. Body: %s', $httpCode, $uri, $response));
        }

        curl_close($handle);

        return $response;
    }
}
