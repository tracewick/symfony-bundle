<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;

/**
 * Prepends {@see TracewickMessengerMiddleware} onto every Messenger bus middleware list.
 *
 * FrameworkBundle stores each bus stack as the `{busId}.middleware` parameter. Listing the
 * service only under a tag is not enough — buses must reference it explicitly.
 */
final class AddTracewickMessengerMiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TracewickMessengerMiddleware::class)) {
            return;
        }

        foreach ($container->getParameterBag()->all() as $name => $middleware) {
            if (!\is_string($name) || !str_ends_with($name, '.middleware') || !\is_array($middleware)) {
                continue;
            }

            $normalized = $this->normalizeBusMiddlewareList($middleware);
            if (null === $normalized) {
                continue;
            }

            if ($this->alreadyRegistered($normalized)) {
                continue;
            }

            array_unshift($normalized, ['id' => TracewickMessengerMiddleware::class]);
            $container->setParameter($name, $normalized);
        }
    }

    /**
     * @param array<mixed> $middleware
     *
     * @return list<array{id: string}>|null
     */
    private function normalizeBusMiddlewareList(array $middleware): ?array
    {
        $normalized = [];
        foreach ($middleware as $item) {
            if (!\is_array($item) || !isset($item['id']) || !\is_string($item['id'])) {
                return null;
            }
            $normalized[] = ['id' => $item['id']];
        }

        return $normalized;
    }

    /**
     * @param list<array{id: string}> $middleware
     */
    private function alreadyRegistered(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if ($item['id'] === TracewickMessengerMiddleware::class) {
                return true;
            }
        }

        return false;
    }
}
