<?php

namespace ExportFormatPluginTutorial\Generator;

use ElasticExport\Helper\ElasticExportCoreHelper;
use ElasticExport\Helper\ElasticExportPriceHelper;
use ElasticExport\Helper\ElasticExportStockHelper;
use Plenty\Modules\DataExchange\Contracts\CSVPluginGenerator;
use Plenty\Modules\Helper\Services\ArrayHelper;
use Plenty\Modules\Helper\Models\KeyValue;
use Plenty\Modules\Item\Search\Contracts\VariationElasticSearchScrollRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class ExportFormatGenerator
 * @package ExportFormatPluginTutorial\Generator
 */
class ExportFormatGenerator extends CSVPluginGenerator
{
	use Loggable;

    /**
     * @var ElasticExportCoreHelper $elasticExportCoreHelper
     */
    private $elasticExportCoreHelper;

	/**
	 * @var ElasticExportPriceHelper $elasticExportPriceHelper
	 */
    private $elasticExportPriceHelper;

	/**
	 * @var ElasticExportStockHelper $elasticExportStockHelper
	 */
    private $elasticExportStockHelper;

    /**
     * @var ArrayHelper $arrayHelper
     */
    private $arrayHelper;

    /**
     * ExportFormatGenerator constructor.
     * @param ArrayHelper $arrayHelper
     */
    public function __construct(ArrayHelper $arrayHelper)
    {
        $this->arrayHelper = $arrayHelper;
    }

    /**
     * Generates and populates the data into the CSV file.
     *
     * @param VariationElasticSearchScrollRepositoryContract $elasticSearch
     * @param array $formatSettings
     * @param array $filter
     */
    protected function generatePluginContent($elasticSearch, array $formatSettings = [], array $filter = [])
    {
        $this->elasticExportCoreHelper = pluginApp(ElasticExportCoreHelper::class);
        $this->elasticExportPriceHelper = pluginApp(ElasticExportPriceHelper::class);

        /** @var KeyValue $settings */
		$settings = $this->arrayHelper->buildMapFromObjectList($formatSettings, 'key', 'value');

		$this->setDelimiter(";");

		// add header
		$this->addCSVContent([
            'Varianten-ID',
            'Variantennummer',
            'Model',
            'Artikelname',
            'Artikelbeschreibung',
            'Bild',
            'Marke',
            'EAN',
            'Währung',
            'Versandkosten',
            'Preis (UVP)',
            'reduzierter Preis',
            'Grundpreis',
            'Grundpreis Einheit',
            'Kategorien',
            'Link'
		]);

		if($elasticSearch instanceof VariationElasticSearchScrollRepositoryContract)
		{
			$limitReached = false;
			$lines = 0;
			
			do
			{
				if($limitReached === true)
				{
					break;
				}

				$resultList = $elasticSearch->execute();

				foreach($resultList['documents'] as $variation)
				{
					if($lines == $filter['limit'])
					{
						$limitReached = true;
						break;
					}

					if(is_array($resultList['documents']) && count($resultList['documents']) > 0)
					{
					    // filter manually for stock limitations
						if($this->elasticExportStockHelper->isFilteredByStock($variation, $filter) === true)
						{
							continue;
						}

						try
						{
							$this->buildRow($variation, $settings);
						}
						catch(\Throwable $exception)
						{
							$this->getLogger('ExportFormatPluginTutorial')->logException($exception);
						}
						
						$lines++;
					}
				}
			} while ($elasticSearch->hasNext());
		}
    }

	/**
     * Builds one data row.
     * 
	 * @param array $variation
	 * @param KeyValue $settings
	 */
    private function buildRow($variation, $settings)
	{
		$priceList = $this->elasticExportPriceHelper->getPriceList($variation, $settings, 2, '.');

		if((float)$priceList['recommendedRetailPrice'] > 0)
		{
			$price = $priceList['recommendedRetailPrice'] > $priceList['price'] ? $priceList['price'] : $priceList['recommendedRetailPrice'];
		}
		else
		{
			$price = $priceList['price'];
		}

		$rrp = $priceList['recommendedRetailPrice'] > $priceList['price'] ? $priceList['recommendedRetailPrice'] : $priceList['price'];
		
		if((float)$rrp == 0 || (float)$price == 0 || (float)$rrp == (float)$price)
		{
			$rrp = '';
		}

		$basePriceList = $this->elasticExportPriceHelper->getBasePriceDetails($variation, (float) $priceList['price'], $settings->get('lang'));
		$deliveryCost = $this->elasticExportCoreHelper->getShippingCost($variation['data']['item']['id'], $settings);
		
		if(!is_null($deliveryCost))
		{
			$deliveryCost = number_format((float)$deliveryCost, 2, '.', '');
		}
		else
		{
			$deliveryCost = '';
		}

		$data = [
			'Varianten-ID' => $variation['id'],
			'Variantennummer' => $variation['data']['variation']['number'],
			'Model' => $variation['data']['variation']['model'],
			'Artikelname' => $this->elasticExportCoreHelper->getName($variation, $settings, 256),
			'Artikelbeschreibung' => $this->elasticExportCoreHelper->getMutatedDescription($variation, $settings, 256),
			'Bild' => $this->elasticExportCoreHelper->getMainImage($variation, $settings),
			'Marke' => $this->elasticExportCoreHelper->getExternalManufacturerName((int)$variation['data']['item']['manufacturer']['id']),
			'EAN' => $this->elasticExportCoreHelper->getBarcodeByType($variation, $settings->get('barcode')),
			'Währung' => $priceList['currency'],
			'Versandkosten' => $deliveryCost,
			'Preis (UVP)' => $rrp,
			'reduzierter Preis' => $price,
			'Grundpreis' => $this->elasticExportPriceHelper->getBasePrice($variation, $priceList['price'], $settings->get('lang'), '/', false, false, $priceList['currency']),
			'Grundpreis Einheit' => $basePriceList['lot'],
			'Kategorien' => $this->getCategories($variation, $settings),
			'Link' => $this->elasticExportCoreHelper->getMutatedUrl($variation, $settings),
		];

		$this->addCSVContent(array_values($data));
	}

    /**
     * Get list of categories.
     *
     * @param  array    $variation
     * @param  KeyValue $settings
     * @return string
     */
    private function getCategories($variation, KeyValue $settings):string
    {
        $categoryList = [];

        if(is_array($variation['data']['ids']['categories']['all']) && count($variation['data']['categories']['all']) > 0)
        {
			// go though the list of the category details
			foreach($variation['data']['ids']['categories']['all'] as $category)
			{
				// pass the category id to construct the category path
				$category = $this->elasticExportCoreHelper->getCategory((int)$category['id'], $settings->get('lang'), $settings->get('plentyId'));

				if(strlen($category))
				{
					$categoryList[] = $category;
				}
			}

			return implode(';', $categoryList);
		}

		return '';
    }
}
