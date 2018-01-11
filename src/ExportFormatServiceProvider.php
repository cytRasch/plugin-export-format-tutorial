<?php

namespace ExportFormatPluginTutorial;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\DataExchangeServiceProvider;

/**
 * Class ExportFormatServiceProvider
 * @package ExportFormatPluginTutorial
 */
class ExportFormatServiceProvider extends DataExchangeServiceProvider
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
    public function exports(ExportPresetContainer $container)
    {
        $container->add(
            'ExportFormat',
            'ExportFormatPluginTutorial\ResultField\ExportFormatResultFields',
            'ExportFormatPluginTutorial\Generator\ExportFormatGenerator',
            '',
            true,
			true
        );
    }
}