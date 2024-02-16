<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

namespace Klevu\ApiRequest\Model\Api\Request;

use Klevu\ApiRequest\Model\Api\Request;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request as HttpRequest;

class Get extends Request
{
    /**
     * @return string
     */
    public function __toString()
    {
        $string = parent::__toString();

        $parameters = $this->getData();
        if (count($parameters) > 0) {
            array_walk($parameters, function (&$value, $key) {
                $value = sprintf("%s: %s", $key, $value);
            });
        }

        return sprintf("%s\nGET parameters:\n%s\n", $string, implode("\n", $parameters));
    }

    /**
     * Add GET parameters to the request, force GET method.
     *
     * @return HttpClient
     */
    protected function build()
    {
        $client = parent::build();
        $client->setMethod(HttpRequest::METHOD_GET);
        $client->setParameterGet($this->getData());

        return $client;
    }
}
