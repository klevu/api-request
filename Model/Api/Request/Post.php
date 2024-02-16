<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

namespace Klevu\ApiRequest\Model\Api\Request;

use Klevu\ApiRequest\Model\Api\Request;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request as HttpRequest;

class Post extends Request
{
    /**
     * @var string[]
     */
    private $maskFields = ['restApiKey', 'email', 'password', 'Authorization'];

    /**
     * @return string
     */
    public function __toString()
    {
        $string = parent::__toString();

        $parameters = $this->getData();
        if (!empty($parameters)) {
            array_walk($parameters, function (&$value, $key) {
                if (in_array($key, $this->maskFields, true)) {
                    $value = sprintf("%s: %s", $key, '***************');
                } else {
                    $value = sprintf("%s: %s", $key, $value);
                }
            });
        }

        return sprintf("%s\nPOST parameters:\n%s\n", $string, implode("\n", $parameters));
    }

    /**
     * Add POST parameters to the request, force POST method.
     *
     * @return HttpClient
     */
    protected function build()
    {
        $client = parent::build();
        $client->setMethod(HttpRequest::METHOD_POST);
        $client->setParameterPost($this->getData());

        return $client;
    }
}
