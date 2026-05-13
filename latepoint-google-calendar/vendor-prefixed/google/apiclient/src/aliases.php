<?php
/**
 * @license Apache-2.0
 *
 * Modified by latepoint on 28-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

if (class_exists('LatePoint_GoogleCalendarAddon_Google_Client', false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}

$classMap = [
    'LatePoint\\GoogleCalendarAddon\\Google\\Client' => 'LatePoint_GoogleCalendarAddon_Google_Client',
    'LatePoint\\GoogleCalendarAddon\\Google\\Service' => 'LatePoint_GoogleCalendarAddon_Google_Service',
    'LatePoint\\GoogleCalendarAddon\\Google\\AccessToken\\Revoke' => 'LatePoint_GoogleCalendarAddon_Google_AccessToken_Revoke',
    'LatePoint\\GoogleCalendarAddon\\Google\\AccessToken\\Verify' => 'LatePoint_GoogleCalendarAddon_Google_AccessToken_Verify',
    'LatePoint\\GoogleCalendarAddon\\Google\\Model' => 'LatePoint_GoogleCalendarAddon_Google_Model',
    'LatePoint\\GoogleCalendarAddon\\Google\\Utils\\UriTemplate' => 'LatePoint_GoogleCalendarAddon_Google_Utils_UriTemplate',
    'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'LatePoint_GoogleCalendarAddon_Google_AuthHandler_Guzzle6AuthHandler',
    'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'LatePoint_GoogleCalendarAddon_Google_AuthHandler_Guzzle7AuthHandler',
    'LatePoint\\GoogleCalendarAddon\\Google\\AuthHandler\\AuthHandlerFactory' => 'LatePoint_GoogleCalendarAddon_Google_AuthHandler_AuthHandlerFactory',
    'LatePoint\\GoogleCalendarAddon\\Google\\Http\\Batch' => 'LatePoint_GoogleCalendarAddon_Google_Http_Batch',
    'LatePoint\\GoogleCalendarAddon\\Google\\Http\\MediaFileUpload' => 'LatePoint_GoogleCalendarAddon_Google_Http_MediaFileUpload',
    'LatePoint\\GoogleCalendarAddon\\Google\\Http\\REST' => 'LatePoint_GoogleCalendarAddon_Google_Http_REST',
    'LatePoint\\GoogleCalendarAddon\\Google\\Task\\Retryable' => 'LatePoint_GoogleCalendarAddon_Google_Task_Retryable',
    'LatePoint\\GoogleCalendarAddon\\Google\\Task\\Exception' => 'LatePoint_GoogleCalendarAddon_Google_Task_Exception',
    'LatePoint\\GoogleCalendarAddon\\Google\\Task\\Runner' => 'LatePoint_GoogleCalendarAddon_Google_Task_Runner',
    'LatePoint\\GoogleCalendarAddon\\Google\\Collection' => 'LatePoint_GoogleCalendarAddon_Google_Collection',
    'LatePoint\\GoogleCalendarAddon\\Google\\Service\\Exception' => 'LatePoint_GoogleCalendarAddon_Google_Service_Exception',
    'LatePoint\\GoogleCalendarAddon\\Google\\Service\\Resource' => 'LatePoint_GoogleCalendarAddon_Google_Service_Resource',
    'LatePoint\\GoogleCalendarAddon\\Google\\Exception' => 'LatePoint_GoogleCalendarAddon_Google_Exception',
];

foreach ($classMap as $class => $alias) {
    class_alias($class, $alias);
}

/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class LatePoint_GoogleCalendarAddon_Google_Task_Composer extends \LatePoint\GoogleCalendarAddon\Google\Task\Composer
{
}

/** @phpstan-ignore-next-line */
if (\false) {
    class LatePoint_GoogleCalendarAddon_Google_AccessToken_Revoke extends \LatePoint\GoogleCalendarAddon\Google\AccessToken\Revoke
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_AccessToken_Verify extends \LatePoint\GoogleCalendarAddon\Google\AccessToken\Verify
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_AuthHandler_AuthHandlerFactory extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_AuthHandler_Guzzle6AuthHandler extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_AuthHandler_Guzzle7AuthHandler extends \LatePoint\GoogleCalendarAddon\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Client extends \LatePoint\GoogleCalendarAddon\Google\Client
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Collection extends \LatePoint\GoogleCalendarAddon\Google\Collection
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Exception extends \LatePoint\GoogleCalendarAddon\Google\Exception
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Http_Batch extends \LatePoint\GoogleCalendarAddon\Google\Http\Batch
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Http_MediaFileUpload extends \LatePoint\GoogleCalendarAddon\Google\Http\MediaFileUpload
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Http_REST extends \LatePoint\GoogleCalendarAddon\Google\Http\REST
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Model extends \LatePoint\GoogleCalendarAddon\Google\Model
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Service extends \LatePoint\GoogleCalendarAddon\Google\Service
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Service_Exception extends \LatePoint\GoogleCalendarAddon\Google\Service\Exception
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Service_Resource extends \LatePoint\GoogleCalendarAddon\Google\Service\Resource
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Task_Exception extends \LatePoint\GoogleCalendarAddon\Google\Task\Exception
    {
    }
    interface LatePoint_GoogleCalendarAddon_Google_Task_Retryable extends \LatePoint\GoogleCalendarAddon\Google\Task\Retryable
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Task_Runner extends \LatePoint\GoogleCalendarAddon\Google\Task\Runner
    {
    }
    class LatePoint_GoogleCalendarAddon_Google_Utils_UriTemplate extends \LatePoint\GoogleCalendarAddon\Google\Utils\UriTemplate
    {
    }
}
