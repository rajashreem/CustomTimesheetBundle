<?php

namespace KimaiPlugin\CustomTimesheetBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class CustomTimesheetExtension extends Extension
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        try {
            $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
            $loader->load('services.yaml');
        } catch (\Exception $e) {
            echo '[CustomTimesheetExtension] invalid services config found: ' . $e->getMessage();
        }
    }

}
