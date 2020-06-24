<?php

namespace PluginExportFormatTutorial;

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
            'PluginExportFormatTutorial\ResultField\ExportFormat',
            'PluginExportFormatTutorial\Generator\ExportFormat',
            '',
			true,
            true
		);
	}
}
