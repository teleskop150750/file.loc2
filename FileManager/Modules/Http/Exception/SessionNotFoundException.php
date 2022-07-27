<?php


namespace FileManager\Modules\Http\Exception;

/**
 * Raised when a session does not exists. This happens in the following cases:
 * - the session is not enabled
 * - attempt to read a session outside a request context (ie. cli script).
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SessionNotFoundException extends \LogicException implements RequestExceptionInterface
{
    public function __construct(string $message = 'There is currently no session available.', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
