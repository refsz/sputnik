<?php

declare(strict_types=1);

namespace Sputnik\DependencyInjection;

use Nette\DI\Container as NetteContainer;
use Nette\DI\MissingServiceException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class PsrContainerAdapter implements ContainerInterface
{
    public function __construct(
        private readonly NetteContainer $container,
    ) {
    }

    public function get(string $id): mixed
    {
        try {
            // First try to get by type (class name) if it looks like a valid class
            if (class_exists($id) || interface_exists($id)) {
                $service = $this->container->getByType($id, throw: false);
                if ($service !== null) {
                    return $service;
                }
            }

            // Then try by service name
            return $this->container->getService($id);
        } catch (MissingServiceException $missingServiceException) {
            throw new class($missingServiceException->getMessage(), 0, $missingServiceException) extends \Exception implements NotFoundExceptionInterface {};
        }
    }

    public function has(string $id): bool
    {
        // Check by type first if it looks like a valid class
        if (class_exists($id) || interface_exists($id)) {
            $service = $this->container->getByType($id, throw: false);
            if ($service !== null) {
                return true;
            }
        }

        // Then check by service name
        return $this->container->hasService($id);
    }
}
