<?php

namespace Lucas\FileManager\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class FilemanagerService {

    public function __construct(private array $fileManagerConfig,private ContainerInterface $container) {
    }

    public function getBasePath(array $queryParameters): array {
        $conf = $queryParameters['conf'];
        $managerConf = $this->fileManagerConfig['conf'];
        if (isset($managerConf[$conf]['dir'])) {
            return $managerConf[$conf];
        }

        if (isset($managerConf[$conf]['service'])) {
            $extra = $queryParameters['extra'] ?? [];
            $confService = $this->container->get($managerConf[$conf]['service'])->getConf($extra);

            return array_merge($managerConf[$conf], $confService);
        }

        throw new \RuntimeException('Please define a "dir" or a "service" parameter in your config.yml');
    }
}
