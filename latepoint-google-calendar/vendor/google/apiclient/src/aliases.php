<?php

namespace LatePoint\GoogleCalendarAddon;

if (\class_exists('LatePoint\\GoogleCalendarAddon\\Google_Client', \false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}
$classMap = ['LatePoint\\GoogleCalendarAddon\\Google\\Client' => 'LatePoint\\GoogleCalendarAddon\Google_Client', 'LatePoint\\GoogleCalendarAddon\\Google\\Service' => 'LatePoint\\GoogleCalendarAddon\Google_Service', 'LatePoint\\GoogleCalendarAddon\\Google\\AccessToken\\Revoke' => 'LatePoint\\GoogleCalendarAddon\Google_AccessToken_Revoke', 'LatePoint\\GoogleCalendarAddon\\Google\\AccessToken\\Verify' => 'LatePoint\\GoogleCalendarAddon\Google_AccessToken_Verify', 'LatePoint\\GoogleCalendarAddon\\Google\\Model' => 'LatePoint\\GoogleCalendarAddon\Google_Model', 'LatePoint\\GoogleCalendarAddon\\Google\\Utils\\UriTemplate' => 'LatePoint\\GoogleCalendarAddon\Google_Utils_UriTemplate', 'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'LatePoint\\GoogleCalendarAddon\Google_AuthHandler_Guzzle6AuthHandler', 'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'LatePoint\\GoogleCalendarAddon\Google_AuthHandler_Guzzle7AuthHandler', 'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\Guzzle5AuthHandler' => 'LatePoint\\GoogleCalendarAddon\Google_AuthHandler_Guzzle5AuthHandler', 'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\AuthHandlerFactory' => 'LatePoint\\GoogleCalendarAddon\Google_AuthHandler_AuthHandlerFactory', 'LatePoint\\GoogleCalendarAddon\\Google\\Http\\Batch' => 'LatePoint\\GoogleCalendarAddon\Google_Http_Batch', 'LatePoint\\GoogleCalendarAddon\\Google\\Http\\MediaFileUpload' => 'LatePoint\\GoogleCalendarAddon\Google_Http_MediaFileUpload', 'LatePoint\\GoogleCalendarAddon\\Google\\Http\\REST' => 'LatePoint\\GoogleCalendarAddon\Google_Http_REST', 'LatePoint\\GoogleCalendarAddon\\Google\\Task\\Retryable' => 'LatePoint\\GoogleCalendarAddon\Google_Task_Retryable', 'LatePoint\\GoogleCalendarAddon\\Google\\Task\\Exception' => 'LatePoint\\GoogleCalendarAddon\Google_Task_Exception', 'LatePoint\\GoogleCalendarAddon\\Google\\Task\\Runner' => 'LatePoint\\GoogleCalendarAddon\Google_Task_Runner', 'LatePoint\\GoogleCalendarAddon\\Google\\Collection' => 'LatePoint\\GoogleCalendarAddon\Google_Collection', 'LatePoint\\GoogleCalendarAddon\\Google\\Service\\Exception' => 'LatePoint\\GoogleCalendarAddon\Google_Service_Exception', 'LatePoint\\GoogleCalendarAddon\\Google\\Service\\Resource' => 'LatePoint\\GoogleCalendarAddon\Google_Service_Resource', 'LatePoint\\GoogleCalendarAddon\\Google\\Exception' => 'LatePoint\\GoogleCalendarAddon\Google_Exception'];
foreach ($classMap as $class => $alias) {
    \class_alias($class, $alias);
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Google_Task_Composer extends \LatePoint\GoogleCalendarAddon\Google\Task\Composer
{
}
/** @phpstan-ignore-next-line */
if (\false) {
    class Google_AccessToken_Revoke extends \LatePoint\GoogleCalendarAddon\Google\AccessToken\Revoke
    {
    }
    class Google_AccessToken_Verify extends \LatePoint\GoogleCalendarAddon\Google\AccessToken\Verify
    {
    }
    class Google_AuthHandler_AuthHandlerFactory extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Google_AuthHandler_Guzzle5AuthHandler extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\Guzzle5AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle6AuthHandler extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle7AuthHandler extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Google_Client extends \LatePoint\GoogleCalendarAddon\Google\Client
    {
    }
    class Google_Collection extends \LatePoint\GoogleCalendarAddon\Google\Collection
    {
    }
    class Google_Exception extends \LatePoint\GoogleCalendarAddon\Google\Exception
    {
    }
    class Google_Http_Batch extends \LatePoint\GoogleCalendarAddon\Google\Http\Batch
    {
    }
    class Google_Http_MediaFileUpload extends \LatePoint\GoogleCalendarAddon\Google\Http\MediaFileUpload
    {
    }
    class Google_Http_REST extends \LatePoint\GoogleCalendarAddon\Google\Http\REST
    {
    }
    class Google_Model extends \LatePoint\GoogleCalendarAddon\Google\Model
    {
    }
    class Google_Service extends \LatePoint\GoogleCalendarAddon\Google\Service
    {
    }
    class Google_Service_Exception extends \LatePoint\GoogleCalendarAddon\Google\Service\Exception
    {
    }
    class Google_Service_Resource extends \LatePoint\GoogleCalendarAddon\Google\Service\Resource
    {
    }
    class Google_Task_Exception extends \LatePoint\GoogleCalendarAddon\Google\Task\Exception
    {
    }
    interface Google_Task_Retryable extends \LatePoint\GoogleCalendarAddon\Google\Task\Retryable
    {
    }
    class Google_Task_Runner extends \LatePoint\GoogleCalendarAddon\Google\Task\Runner
    {
    }
    class Google_Utils_UriTemplate extends \LatePoint\GoogleCalendarAddon\Google\Utils\UriTemplate
    {
    }
}
