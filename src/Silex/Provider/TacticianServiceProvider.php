<?php

namespace Silex\Provider;

use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\CommandNameExtractor\CommandNameExtractor;
use League\Tactician\Handler\Locator\HandlerLocator;
use League\Tactician\Handler\Locator\InMemoryLocator;
use League\Tactician\Handler\MethodNameInflector\HandleClassNameInflector;
use League\Tactician\Handler\MethodNameInflector\HandleClassNameWithoutSuffixInflector;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use League\Tactician\Handler\MethodNameInflector\InvokeInflector;
use League\Tactician\Handler\MethodNameInflector\MethodNameInflector;
use League\Tactician\Middleware;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class TacticianServiceProvider
 * @package Silex\Provider
 */
class TacticianServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $app
     */
    public function register(Container $app)
    {
        // register default locator if haven't defined yet
        $app['tactician.locator'] = function () {
            return new InMemoryLocator();
        };

        // register default command extractor if haven't defined yet
        $app['tactician.command_extractor'] = function () {
            return new ClassNameExtractor();
        };

        // if inflector is string then resolve it
        $app['tactician.inflector'] = function (Container $c) {
            return $this->resolveStringBaseMethodInflector($c['tactician.config.inflector']);
        };

        $app['tactician.command_bus'] = function (Container $c) {
            // type checking, make sure all command bus component are valid
            if (!$c['tactician.command_extractor'] instanceof CommandNameExtractor) {
                throw new \InvalidArgumentException(sprintf(
                    'Tactician command extractor must implement %s',
                    CommandNameExtractor::class
                ));
            }

            if (!$c['tactician.locator'] instanceof HandlerLocator) {
                throw new \InvalidArgumentException(sprintf(
                    'Tactician locator must implement %s',
                    HandlerLocator::class
                ));
            }

            if (!$c['tactician.inflector'] instanceof MethodNameInflector) {
                throw new \InvalidArgumentException(sprintf(
                    'Tactician inflector must implement %s',
                    MethodNameInflector::class
                ));
            }

            $handler_middleware = new CommandHandlerMiddleware(
                $c['tactician.command_extractor'],
                $c['tactician.locator'],
                $c['tactician.inflector']
            );

            // combine middleware together
            $middleware = $c['tactician.middleware'];
            array_walk($middleware, function (&$value) use ($c) {
                $value = $this->resolveMiddleware($c, $value);
            });
            array_push($middleware, $handler_middleware);

            return new CommandBus($middleware);
        };
    }

    /**
     * @param string $string
     * @return MethodNameInflector
     */
    private function resolveStringBaseMethodInflector($string)
    {
        switch ($string) {
            case 'class_name':
                $inflector = new HandleClassNameInflector();
                break;
            case 'class_name_without_suffix':
                $inflector = new HandleClassNameWithoutSuffixInflector();
                break;
            case 'handle':
                $inflector = new HandleInflector();
                break;
            case 'invoke':
                $inflector = new InvokeInflector();
                break;
            default:
                $inflector = new HandleClassNameInflector();
                break;
        }

        return $inflector;
    }

    /**
     * @param string|Middleware $middleware
     * @return Middleware
     */
    public function resolveMiddleware(Container $pimple, $middleware)
    {
        if ($middleware instanceof Middleware) {
            return $middleware;
        }

        if ($pimple->offsetExists($middleware)) {
            $middleware = $pimple[$middleware];
            if ($middleware instanceof Middleware) {
                return $middleware;
            }
        }

        throw new \InvalidArgumentException(sprintf('Tactician middleware must implement %s', Middleware::class));
    }
}
