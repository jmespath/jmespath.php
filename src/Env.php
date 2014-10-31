<?php
namespace JmesPath;

use JmesPath\Runtime\AstRuntime;
use JmesPath\Runtime\CompilerRuntime;

/**
 * Provides a simple environment based search.
 *
 * The runtime utilized by the Env class can be customized via environment
 * variables. If the JP_PHP_COMPILE environment variable is specified, then the
 * CompilerRuntime will be utilized. If set to "on", JMESPath expressions will
 * be cached to the system's temp directory. Set the environment variable to
 * a string to cache expressions to a specific directory.
 */
final class Env
{
    const COMPILE_DIR = 'JP_PHP_COMPILE';

    /** @var \JmesPath\Runtime\RuntimeInterface */
    private static $runtime;

    /**
     * Returns data from the input array that matches a given JMESPath expression.
     *
     * @param string $expression JMESPath expression to evaluate
     * @param mixed  $data       JSON-like data to search
     *
     * @return mixed|null Returns the matching data or null
     */
    public static function search($expression, $data)
    {
        if (!self::$runtime) {
            self::$runtime = self::createRuntime();
        }

        return self::$runtime->search($expression, $data);
    }

    /**
     * Creates a JMESPath runtime based on environment variables and extensions
     * available on a system.
     *
     * @return AstRuntime|CompilerRuntime
     */
    public static function createRuntime()
    {
        $compileDir = getenv('COMPILE_DIR');

        if (!$compileDir) {
            return new AstRuntime();
        }

        return $compileDir === 'on'
            ? new CompilerRuntime()
            : new CompilerRuntime(['dir' => $compileDir]);
    }
}
