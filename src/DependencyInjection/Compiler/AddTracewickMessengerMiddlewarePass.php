<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;

/**
 * Prepends {@see TracewickMessengerMiddleware} onto every Messenger bus middleware list.
 *
 * Must run before Symfony's {@see \Symfony\Component\Messenger\DependencyInjection\MessengerPass}
 * (priority above 0): that pass reads `{busId}.middleware` and then removes the parameter.
 *
 * Existing middleware entries are left intact (including `arguments`); only our id is prepended.
 */
final class AddTracewickMessengerMiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TracewickMessengerMiddleware::class)) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds('messenger.bus')) as $busId) {
            $parameter = $busId . '.middleware';
            if (!$container->hasParameter($parameter)) {
                continue;
            }

            $middleware = $container->getParameter($parameter);
            if (!\is_array($middleware)) {
                continue;
            }

            if ($this->alreadyRegistered($middleware)) {
                continue;
            }

            array_unshift($middleware, ['id' => TracewickMessengerMiddleware::class]);
            $container->setParameter($parameter, $middleware);
        }
    }

    /**
     * @param array<mixed> $middleware
     */
    private function alreadyRegistered(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if (\is_array($item) && ($item['id'] ?? null) === TracewickMessengerMiddleware::class) {
                return true;
            }
        }

        return false;
    }
}
