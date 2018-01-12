<?php

namespace PluginExportFormatTutorial;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\ServiceProvider;

/**
 * Class ExportFormatServiceProvider
 * @package PluginExportFormatTutorial
 */
class ExportFormatServiceProvider extends ServiceProvider
{
    /**
     * Abstract function for registering the service provider.
     */
    public function register()
    {

    }

    /**
     * Adds the export format to the export container.
     *
     * @param ExportPresetContainer $container
     */
    public function boot(ExportPresetContainer $container)
    {
        $container->add(
            'ExportFormat',
            'PluginExportFormatTutorial\ResultField\ExportFormatResultFields',
            'PluginExportFormatTutorial\Generator\ExportFormatGenerator',
            '',
            true,
			true,
            'item'
        );
    }
}