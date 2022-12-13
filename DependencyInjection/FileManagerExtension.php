<?php

namespace Lucas\FileManager\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class FileManagerExtension extends Extension
{
    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {

        $configuration = new Configuration($container->getParameter('kernel.project_dir'));
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('file_manager', $config);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../config/'));
        $loader->load('services.yml');
    }
}
