<?php
/*
 * This file is part of the App\Http package.
 *
 * (c) Terry L. <contact@terryl.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Http\Psr17;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use App\Http\Psr17\UriFactory;
use App\Http\Psr17\StreamFactory;
use App\Http\Psr17\Utils\SuperGlobal;
use App\Http\Psr7\Request;

use function str_replace;
use function extract;

class RequestFactory implements RequestFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        extract(SuperGlobal::extract());

        $protocol = $server['SERVER_PROTOCOL'] ?? '1.1';
        $protocol = str_replace('HTTP/', '',  $protocol);

        $uriFactory = new UriFactory();
        $streamFactory = new StreamFactory();

        $uri = $uriFactory->createUri($uri);
        $body = $streamFactory->createStream();

        return new Request(
            $method,
            $uri,
            $body,
            $header, // from extract.
            $protocol
        );
    }

    public static function fromNew(): RequestInterface
    {
        return new Request();
    }
}
