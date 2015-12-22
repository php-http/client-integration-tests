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
     * @return string|boolean The uri or FALSE if there is none.
     */
    public static function getUri()
    {
        return isset($_SERVER['TEST_SERVER']) ? $_SERVER['TEST_SERVER'] : false;
    }

    /**
     * Gets the file.
     *
     * @param boolean     $tmp  TRUE if the file should be in the "/tmp" directory else FALSE.
     * @param string|null $name The name.
     *
     * @return string The file.
     */
    public static function getFile($tmp = true, $name = null)
    {
        return ($tmp ? realpath(sys_get_temp_dir()) : '').'/'.($name === null ? uniqid() : $name);
    }
}
