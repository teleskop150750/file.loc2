<?php

namespace FileManager\Http;

class ServerBag extends ParameterBag
{
    /**
     * Gets the HTTP headers.
     */
    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->parameters as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            } elseif (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
