<?php

namespace Intaro\RuleEngineBundle\Loader;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\FileLocator;
use Intaro\RuleEngineBundle\Event\Mapper\EventsMap;

class AnnotationFileLoader extends FileLoader
{
    protected $loader;

    /**
     * Constructor.
     *
     * @param FileLocator           $locator A FileLocator instance
     * @param AnnotationClassLoader $loader  An AnnotationClassLoader instance
     * @param string|array          $paths   A path or an array of paths where to look for resources
     */
    public function __construct(FileLocator $locator, AnnotationClassLoader $loader, $paths = array())
    {
        if (!function_exists('token_get_all')) {
            throw new \RuntimeException('The Tokenizer extension is required for the routing annotation loaders.');
        }

        parent::__construct($locator, $paths);

        $this->loader = $loader;
    }

    /**
     * Loads from annotations from a file.
     *
     * @param string $file A PHP file path
     * @param string $type The resource type
     *
     * @return SecurityPolicyRules A Rules instance
     *
     * @throws \InvalidArgumentException When annotations can't be parsed
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $map = new EventsMap();
        if ($class = $this->findClass($path)) {
            $map->addResource(new FileResource($path));
            $map->merge($this->loader->load($class, $type));
        }

        return $map;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'php' === pathinfo($resource, PATHINFO_EXTENSION) && (!$type || 'annotation' === $type);
    }

    /**
     * Returns the full class name for the first class in the file.
     *
     * @param string $file A PHP file path
     *
     * @return string|false Full class name if found, false otherwise
     */
    protected function findClass($file)
    {
        $class = false;
        $namespace = false;
        $tokens = token_get_all(file_get_contents($file));
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if (true === $class && T_STRING === $token[0]) {
                return $namespace.'\\'.$token[1];
            }

            if (true === $namespace && T_STRING === $token[0]) {
                $namespace = '';
                do {
                    $namespace .= $token[1];
                    $token = $tokens[++$i];
                } while ($i < $count && is_array($token) && in_array($token[0], array(T_NS_SEPARATOR, T_STRING)));
            }

            if (T_CLASS === $token[0]) {
                $class = true;
            }

            if (T_NAMESPACE === $token[0]) {
                $namespace = true;
            }
        }

        return false;
    }
}
