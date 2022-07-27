<?php


namespace FileManager\Modules\Http\Exception;

/**
 * Raised when a user sends a malformed request.
 */
class BadRequestException extends \UnexpectedValueException implements RequestExceptionInterface
{
}
