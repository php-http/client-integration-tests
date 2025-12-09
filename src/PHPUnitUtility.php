<?php

namespace Http\Client\Tests;

/**
 * PHPUnit utility.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class PHPUnitUtility
{
    /**
     * Gets the uri.
     *
     * @return string|bool The uri or FALSE if there is none.
     */
    public static function getUri()
    {
        return array_key_exists('TEST_SERVER', $_SERVER) ? $_SERVER['TEST_SERVER'] : false;
    }

    /**
     * Gets the file.
     *
     * @param bool $tmp  TRUE if the file should be in the "/tmp" directory else FALSE.
     *
     * @return string The file.
     */
    public static function getFile(bool $tmp = true, ?string $name = null): string
    {
        return ($tmp ? realpath(sys_get_temp_dir()) : '').'/'.(null === $name ? uniqid() : $name);
    }
}
