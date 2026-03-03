<?php

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

declare(strict_types=1);

namespace Horde\Db\Adapter;

use InvalidArgumentException;

/**
 * Trait providing default implementation of CapabilityDetection::hasCapability().
 *
 * This trait can be used by adapters implementing CapabilityDetection to
 * provide the convenience hasCapability() method without duplicating logic.
 *
 * The adapter must implement getServerCapabilities() to return an object
 * with methods like supportsJSON(), supportsCTE(), etc.
 *
 * Usage:
 * <code>
 * class Mysqli extends Base implements CapabilityDetection
 * {
 *     use CapabilityDetectionTrait;
 *
 *     public function getServerCapabilities(): object
 *     {
 *         return $this->serverInfo ??= Mysql\ServerInfo::fromMysqli($this->connection);
 *     }
 * }
 * </code>
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @since    Horde_Db 3.0.0
 */
trait CapabilityDetectionTrait
{
    /**
     * Check if a specific capability is supported.
     *
     * Maps capability names to ServerInfo methods:
     * - 'json' -> supportsJSON()
     * - 'cte' -> supportsCTE()
     * - 'window_functions' -> supportsWindowFunctions()
     * - etc.
     *
     * @param string $capability  Capability name (e.g., 'json', 'cte').
     *
     * @return bool  True if the capability is supported, false otherwise.
     *
     * @throws InvalidArgumentException  If the capability name is not recognized.
     */
    public function hasCapability(string $capability): bool
    {
        $capabilities = $this->getServerCapabilities();

        // Map capability names to method names
        $method = $this->mapCapabilityToMethod($capability);

        if (!method_exists($capabilities, $method)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown capability "%s". Available capabilities: %s',
                    $capability,
                    implode(', ', $this->getAvailableCapabilities($capabilities))
                )
            );
        }

        return $capabilities->$method();
    }

    /**
     * Map a capability name to the corresponding method name.
     *
     * Converts snake_case or kebab-case capability names to camelCase
     * method names with 'supports' prefix.
     *
     * Examples:
     * - 'json' -> 'supportsJSON'
     * - 'window_functions' -> 'supportsWindowFunctions'
     * - 'cte' -> 'supportsCTE'
     *
     * @param string $capability  Capability name.
     *
     * @return string  Method name.
     */
    protected function mapCapabilityToMethod(string $capability): string
    {
        // Handle special cases for acronyms
        $specialCases = [
            'json' => 'supportsJSON',
            'cte' => 'supportsCTE',
            'sql' => 'supportsSQL',
        ];

        $normalized = strtolower($capability);
        if (isset($specialCases[$normalized])) {
            return $specialCases[$normalized];
        }

        // Convert snake_case or kebab-case to camelCase
        $parts = preg_split('/[_-]/', $capability);
        $camelCase = array_shift($parts);
        foreach ($parts as $part) {
            $camelCase .= ucfirst(strtolower($part));
        }

        return 'supports' . ucfirst($camelCase);
    }

    /**
     * Get list of available capability names.
     *
     * Extracts capability names from all supports*() methods on the
     * server info object.
     *
     * @param object $capabilities  Server capability object.
     *
     * @return array  List of capability names.
     */
    protected function getAvailableCapabilities(object $capabilities): array
    {
        $methods = get_class_methods($capabilities);
        $capabilityNames = [];

        foreach ($methods as $method) {
            if (str_starts_with($method, 'supports')) {
                // Convert supportsJSON -> json, supportsWindowFunctions -> window_functions
                $capability = substr($method, 8); // Remove 'supports'
                $capability = preg_replace('/([a-z])([A-Z])/', '$1_$2', $capability);
                $capabilityNames[] = strtolower($capability);
            }
        }

        return $capabilityNames;
    }
}
