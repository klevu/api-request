<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\ApiRequest\Model\Api;

use Exception;
use Klevu\Logger\Constants as LoggerConstants;
use Klevu\Search\Helper\Config as ConfigHelper;
use Klevu\Search\Model\Api\Response;
use Klevu\Search\Model\Api\Response\Rempty as EmptyResponse;
use Laminas\Http\Client as LaminasClient;
use Laminas\Http\ClientFactory as LaminasClientFactory;
use Laminas\Http\Exception\RuntimeException;
use Laminas\Http\Request as LaminasRequest;
use Magento\Framework\DataObject;
use Psr\Log\LoggerInterface;

class Request extends DataObject
{
    /**
     * @var ConfigHelper
     */
    private $searchHelperConfig;
    /**
     * @var EmptyResponse
     */
    private $apiResponseEmpty;
    /**
     * @var string[]
     */
    private $maskFields = ['restApiKey', 'email', 'password', 'Authorization'];
    /**
     * @var string
     */
    private $endpoint;
    /**
     * @var string
     */
    private $method;
    /**
     * @var string[]
     */
    private $headers;
    /**
     * @var Response
     */
    private $responseModel;
    /**
     * @var LaminasClientFactory
     */
    private $httpClientFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigHelper $searchHelperConfig
     * @param EmptyResponse $apiResponseEmpty
     * @param LaminasClientFactory $httpClientFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigHelper $searchHelperConfig,
        EmptyResponse $apiResponseEmpty,
        LaminasClientFactory $httpClientFactory,
        LoggerInterface $logger
    ) {
        $this->searchHelperConfig = $searchHelperConfig;
        $this->apiResponseEmpty = $apiResponseEmpty;
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
        $this->method = LaminasRequest::METHOD_GET;
        $this->headers = [];

        parent::__construct();
    }

    /**
     * Set the target endpoint URL for this API request.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setEndpoint(string $url)
    {
        $this->endpoint = $url;

        return $this;
    }

    /**
     * Return the target endpoint for this API request.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Set the HTTP method to use for this API request.
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Get the HTTP method configured for this API request.
     *
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set a HTTP header for this API request.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function setHeader(string $name, $value)
    {
        $this->headers = [$name => $value];

        return $this;
    }

    /**
     * Get the array of HTTP headers configured for this API request.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set the response model to use for this API request.
     *
     * @param Response $responseModel
     *
     * @return $this
     */
    public function setResponseModel(Response $responseModel)
    {
        $this->responseModel = $responseModel;

        return $this;
    }

    /**
     * Return the response model used for this API request.
     *
     * @return Response
     */
    public function getResponseModel()
    {
        return $this->responseModel;
    }

    /**
     * Perform the API request and return the received response.
     *
     * @return Response
     * @throws Exception
     */
    public function send()
    {
        if (!$this->getEndpoint()) {
            // Can't make a request without a URL
            throw new Exception("Unable to send a Klevu Search API request: No URL specified.");
        }
        $logLevel = $this->searchHelperConfig->getLogLevel();

        $raw_request = $this->build();
        if ($logLevel === LoggerConstants::ZEND_LOG_DEBUG) {
            $this->logger->debug(
                sprintf("API EndPoint: %s", $this->getEndpoint())
            );
            $this->logger->debug(
                sprintf("API request:\n%s", $this->__toString())
            );
        }
        try {
            $raw_response = $raw_request->send();
        } catch (RuntimeException $exception) {
            // Return an empty response
            $this->logger->error(
                sprintf("HTTP error: %s", $exception->getMessage())
            );

            return $this->apiResponseEmpty;
        }
        $content = $raw_response->getBody();
        if ($logLevel >= LoggerConstants::ZEND_LOG_DEBUG) {
            $content = $this->applyMaskingOnResponse($content);
            $this->logger->debug(
                sprintf("API response:\n%s", $content)
            );
        }
        $response = $this->getResponseModel();
        $response->setRawResponse($raw_response);

        return $response;
    }

    /**
     * Return the string representation of the API request.
     *
     * @return string
     */
    public function __toString()
    {
        $headers = $this->getHeaders();
        if (!$headers) {
            return '';
        }

        array_walk($headers, function (&$value, $key) {
            $value = ($value !== null && $value !== false)
                ? sprintf("%s: %s", $key, $value)
                : null;
            if (in_array($key, $this->maskFields)) {
                $value = sprintf("%s: %s", $key, '***************');
            }
        });

        return sprintf(
            "%s %s\n%s\n",
            $this->getMethod(),
            $this->getEndpoint(),
            implode("\n", array_filter($headers))
        );
    }

    /**
     * Build the HTTP request to be sent.
     *
     * @return LaminasClient
     */
    protected function build()
    {
        $client = $this->httpClientFactory->create();
        $client->setUri($this->getEndpoint());
        $client->setMethod($this->getMethod());
        $client->setOptions(['sslverifypeer' => false]);
        if (!empty($this->getHeaders())) {
            $client->setHeaders($this->getHeaders());
        }

        return $client;
    }

    /**
     * Applying masking for sensitive fields
     *
     * @param string $content
     *
     * @return string
     */
    private function applyMaskingOnResponse(string $content)
    {
        $originalString = $content;
        try {
            switch ($content) {
                case strpos($content, 'email') !== false:
                    $emailPattern = '/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/';
                    if (preg_match($emailPattern, $content, $email_string)) {
                        //checking for first full pattern only
                        $content = str_replace($email_string[0], '***********', $content);
                    }
                    break;
                case strpos($content, 'restApiKey') !== false:
                    if (preg_match_all("%(<restApiKey>).*?(</restApiKey>)%i", $content, $restApi)) {
                        $content = str_replace($restApi[0], '<restApiKey>**********</restApiKey>', $content);
                    }
                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            $this->logger->error(
                sprintf("Exception while masking: %s", $e->getMessage())
            );

            return $originalString;
        }

        return $content;
    }
}
