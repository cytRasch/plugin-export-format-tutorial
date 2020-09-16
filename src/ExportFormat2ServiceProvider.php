<?php

namespace PluginExportFormatTutorial2;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\DataExchangeServiceProvider;

class ExportFormat2ServiceProvider extends DataExchangeServiceProvider
{
	public function register()
	{
	}

	public function exports(ExportPresetContainer $container)
	{
		$container->add(
			'ExportFormat2',
            'PluginExportFormatTutorial2\ResultField\ExportFormat2',
            'PluginExportFormatTutorial2\Generator\ExportFormat2',
            '',
			true,
            true
		);
	}
}
