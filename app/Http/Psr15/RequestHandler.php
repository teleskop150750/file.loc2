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

namespace App\Http\Psr15;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Http\Psr7\Response;


class RequestHandler implements RequestHandlerInterface
{
    /**
     * Промежуточное программное обеспечение в очереди готово к запуску.
     */
    protected array $queue = [];

    /**
     * После того как было вызвано последнее промежуточное программное обеспечение, запасной обработчик должен
     * проанализируйте запрос и дайте соответствующий ответ.
     */
    protected ?RequestHandlerInterface $fallbackHandler = null;

    public function __construct(?RequestHandlerInterface $fallbackHandler = null)
    {
        if ($fallbackHandler instanceof RequestHandlerInterface) {
            $this->fallbackHandler = $fallbackHandler;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(MiddlewareInterface $middleware): void
    {
        $this->queue[] = $middleware;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (0 === count($this->queue)) {
            return $this->final($request);
        }

        return array_shift($this->queue)?->process($request, $this);
    }

    protected function final(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->fallbackHandler) {
            return new Response();
        }

        return $this->fallbackHandler->handle($request);
    }
}
