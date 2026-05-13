<?php

namespace LatePoint\GoogleCalendarAddon;

// For older (pre-2.7.2) verions of google/apiclient
if (\file_exists(__DIR__ . '/../apiclient/src/Google/Client.php') && !\class_exists('LatePoint\\GoogleCalendarAddon\\Google_Client', \false)) {
    require_once __DIR__ . '/../apiclient/src/Google/Client.php';
    if (\defined('Google_Client::LIBVER') && \version_compare(Google_Client::LIBVER, '2.7.2', '<=')) {
        $servicesClassMap = ['LatePoint\\GoogleCalendarAddon\\Google\\Client' => 'Google_Client', 'LatePoint\\GoogleCalendarAddon\\Google\\Service' => 'Google_Service', 'LatePoint\\GoogleCalendarAddon\\Google\\Service\\Resource' => 'LatePoint\GoogleCalendarAddon\Google_Service_Resource', 'LatePoint\\GoogleCalendarAddon\\Google\\Model' => 'Google_Model', 'LatePoint\\GoogleCalendarAddon\\Google\\Collection' => 'Google_Collection'];
        foreach ($servicesClassMap as $alias => $class) {
            \class_alias($class, $alias);
        }
    }
}
\spl_autoload_register(function ($class) {
    if (0 === \strpos($class, 'LatePoint\GoogleCalendarAddon\Google_Service_')) {
        // Autoload the new class, which will also create an alias for the
        // old class by changing underscores to namespaces:
        //     Google_Service_Speech_Resource_Operations
        //      => Google\Service\Speech\Resource\Operations
        $classExists = \class_exists($newClass = \str_replace('_', '\\', $class));
        if ($classExists) {
            return \true;
        }
    }
}, \true, \true);
