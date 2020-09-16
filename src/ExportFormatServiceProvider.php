<?php

namespace PluginExportFormatTutorial2;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\DataExchangeServiceProvider;

class ExportFormatServiceProvider extends DataExchangeServiceProvider
{
	public function register()
	{
	}

	public function exports(ExportPresetContainer $container)
	{
		$container->add(
			'ExportFormat',
            'PluginExportFormatTutorial2\ResultField\ExportFormat',
            'PluginExportFormatTutorial2\Generator\ExportFormat',
            '',
			true,
            true
		);
	}
}
