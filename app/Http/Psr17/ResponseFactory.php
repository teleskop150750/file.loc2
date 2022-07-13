<?php
declare(strict_types=1);

namespace App\Http\Psr17;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use App\Http\Psr17\StreamFactory;
use App\Http\Psr17\Utils\SuperGlobal;
use App\Http\Psr7\Response;

use function str_replace;
use function extract;

class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        extract(SuperGlobal::extract());

        $protocol = $server['SERVER_PROTOCOL'] ?? '1.1';
        $protocol = str_replace('HTTP/', '',  $protocol);

        $streamFactory = new streamFactory();

        $body = $streamFactory->createStream();

        return new Response(
            $code,
            $header, // from extract.
            $body,
            $protocol,
            $reasonPhrase
        );
    }

    public static function fromNew(): ResponseInterface
    {
        return new Response();
    }
}
