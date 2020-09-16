<?php

namespace PluginExportFormatTutorial2\Generator;


use ElasticExport\Helper\ElasticExportItemHelper;
use ElasticExport\Helper\ElasticExportPriceHelper;
use ElasticExport\Helper\ElasticExportPropertyHelper;
use ElasticExport\Helper\ElasticExportStockHelper;
use ElasticExport\Services\FiltrationService;
use ElasticExport\Services\PriceDetectionService;
use PluginExportFormatTutorial2\Helper\AttributeHelper;
use PluginExportFormatTutorial2\Helper\PriceHelper;
use Plenty\Legacy\Services\Item\Variation\DetectSalesPriceService;
use Plenty\Modules\DataExchange\Contracts\CSVPluginGenerator;
use Plenty\Modules\Helper\Services\ArrayHelper;
use ElasticExport\Helper\ElasticExportCoreHelper;
use Plenty\Modules\Helper\Models\KeyValue;
use Plenty\Modules\Item\Search\Contracts\VariationElasticSearchScrollRepositoryContract;
use Plenty\Modules\Item\Variation\Contracts\VariationExportServiceContract;
use Plenty\Modules\Item\Variation\Services\ExportPreloadValue\ExportPreloadValue;
use Plenty\Modules\Order\Currency\Contracts\CurrencyRepositoryContract;
use Plenty\Modules\Order\Currency\Models\Currency;
use Plenty\Plugin\Log\Loggable;
use PluginExportFormatTutorial2\Helper\ImageHelper;


/**
 * Class GoogleShopping
 *
 * @package PluginExportFormatTutorial2\Generator
 */
class ExportFormat extends CSVPluginGenerator
{

    use Loggable;


    const CHARACTER_TYPE_GENDER                    = 'gender';

    const CHARACTER_TYPE_AGE_GROUP                 = 'age_group';

    const CHARACTER_TYPE_SIZE_TYPE                 = 'size_type';

    const CHARACTER_TYPE_SIZE_SYSTEM               = 'size_system';

    const CHARACTER_TYPE_ENERGY_EFFICIENCY_CLASS   = 'energy_efficiency_class';

    const CHARACTER_TYPE_EXCLUDED_DESTINATION      = 'excluded_destination';

    const CHARACTER_TYPE_ADWORDS_REDIRECT          = 'adwords_redirect';

    const CHARACTER_TYPE_MOBILE_LINK               = 'mobile_link';

    const CHARACTER_TYPE_SALE_PRICE_EFFECTIVE_DATE = 'sale_price_effective_date';

    const CHARACTER_TYPE_CUSTOM_LABEL_0            = 'custom_label_0';

    const CHARACTER_TYPE_CUSTOM_LABEL_1            = 'custom_label_1';

    const CHARACTER_TYPE_CUSTOM_LABEL_2            = 'custom_label_2';

    const CHARACTER_TYPE_CUSTOM_LABEL_3            = 'custom_label_3';

    const CHARACTER_TYPE_CUSTOM_LABEL_4            = 'custom_label_4';

    const CHARACTER_TYPE_DESCRIPTION               = 'description';

    const CHARACTER_TYPE_COLOR                     = 'color';

    const CHARACTER_TYPE_SIZE                      = 'size';

    const CHARACTER_TYPE_PATTERN                   = 'pattern';

    const CHARACTER_TYPE_MATERIAL                  = 'material';

    const ISO_CODE_2                               = 'isoCode2';

    const ISO_CODE_3                               = 'isoCode3';

    const GOOGLE_SHOPPING                          = 7.00;

    /**
     * @var ElasticExportCoreHelper $elasticExportHelper
     */
    private $elasticExportHelper;

    /**
     * @var ArrayHelper $arrayHelper
     */
    private $arrayHelper;

    /**
     * @var AttributeHelper $attributeHelper
     */
    private $attributeHelper;

    /**
     * @var PriceHelper $priceHelper
     */
    private $priceHelper;

    /**
     * @var ElasticExportStockHelper $elasticExportStockHelper
     */
    private $elasticExportStockHelper;

    /**
     * @var ElasticExportPriceHelper $elasticExportPriceHelper
     */
    private $elasticExportPriceHelper;

    /**
     * @var ElasticExportItemHelper $elasticExportItemHelper
     */
    private $elasticExportItemHelper;

    /**
     * @var ElasticExportPropertyHelper $elasticExportPropertyHelper
     */
    private $elasticExportPropertyHelper;

    /**
     * @var ImageHelper $imageHelper
     */
    private $imageHelper;

    /**
     * @var int
     */
    private $errorIterator = 0;

    /**
     * @var array
     */
    private $errorBatch = [];

    /**
     * @var FiltrationService
     */
    private $filtrationService;

    /**
     * @var VariationExportServiceContract
     */
    private $variationExportService;

    /**
     * @var PriceDetectionService
     */
    private $priceDetectionService;


    /**
     * GoogleShopping constructor.
     *
     * @param ArrayHelper                    $arrayHelper
     * @param AttributeHelper                $attributeHelper
     * @param PriceHelper                    $priceHelper
     * @param ImageHelper                    $imageHelper
     * @param VariationExportServiceContract $variationExportService
     */
    public function __construct(
        ArrayHelper $arrayHelper,
        AttributeHelper $attributeHelper,
        PriceHelper $priceHelper,
        ImageHelper $imageHelper,
        VariationExportServiceContract $variationExportService
    )
    {

        $this->arrayHelper = $arrayHelper;
        $this->attributeHelper = $attributeHelper;
        $this->priceHelper = $priceHelper;
        $this->imageHelper = $imageHelper;
        $this->variationExportService = $variationExportService;
    }


    /**
     * @param VariationElasticSearchScrollRepositoryContract $elasticSearch
     * @param array                                          $formatSettings
     * @param array                                          $filter
     */
    protected function generatePluginContent( $elasticSearch, array $formatSettings = [], array $filter = [] )
    {

        $this->elasticExportPriceHelper = pluginApp(ElasticExportPriceHelper::class);
        $this->elasticExportStockHelper = pluginApp(ElasticExportStockHelper::class);
        $this->elasticExportHelper = pluginApp(ElasticExportCoreHelper::class);
        $this->elasticExportItemHelper = pluginApp(ElasticExportItemHelper::class);
        $this->elasticExportPropertyHelper = pluginApp(ElasticExportPropertyHelper::class);
        $this->priceDetectionService = pluginApp(PriceDetectionService::class);

        $this->attributeHelper->setPropertyHelper();

        $settings = $this->arrayHelper->buildMapFromObjectList($formatSettings, 'key', 'value');
        $this->filtrationService = pluginApp(FiltrationService::class, ['settings'       => $settings,
                                                                        'filterSettings' => $filter
        ]);

        $this->setDelimiter("	"); // this is tab character!

        $shardIterator = 0;

        // preload prices for comparison of the IDs of the list and a variation bulk
        $this->priceDetectionService->preload($settings);

        $this->attributeHelper->loadLinkedAttributeList($settings);

        $this->addCSVContent($this->getHeader());

        if ( $elasticSearch instanceof VariationElasticSearchScrollRepositoryContract ) {
            $elasticSearch->setNumberOfDocumentsPerShard(250);

            $limitReached = false;
            $lines = 0;
            do {
                if ( $limitReached === true ) {
                    break;
                }

                // execute and get page
                $resultList = $elasticSearch->execute();
                $shardIterator++;

                if ( count($resultList['error']) > 0 ) {
                    $this->getLogger(__METHOD__)
                        ->addReference('failedShard', $shardIterator)
                        ->error('PluginExportFormatTutorial2::log.esError', [
                            'Error message' => $resultList['error'],
                        ]);
                }

                if ( $shardIterator == 1 ) {
                    $this->getLogger(__METHOD__)
                        ->addReference('total', (int) $resultList['total'])
                        ->debug('PluginExportFormatTutorial2::logs.esResultAmount');
                }

                $this->variationExportService->addPreloadTypes([
                    //'VariationStock',
                    'VariationSalesPrice'
                ]);

                // collection variation IDs
                $preloadObjects = [];
                foreach ( $resultList['documents'] as $variation ) {
                    $preloadObjects[] = pluginApp(ExportPreloadValue::class, [
                        (int) $variation['data']['item']['id'],
                        (int) $variation['id']
                    ]);
                }

                // execute and preload
                $this->variationExportService->preload($preloadObjects);

                foreach ( $resultList['documents'] as $variation ) {
                    if ( $lines == $filter['limit'] ) {
                        $limitReached = true;
                        break;
                    }

                    if ( is_array($resultList['documents']) && count($resultList['documents']) > 0 ) {
                        if ( $this->filtrationService->filter($variation) ) {
                            continue;
                        }

                        if($this->elasticExportHelper->getBarcodeByType($variation, $settings->get('barcode')) === '' || !$this->elasticExportHelper->getBarcodeByType($variation, $settings->get('barcode')))
                            continue;

                        try {
                            $this->buildRow($variation, $settings);
                        } catch ( \Throwable $throwable ) {
                            $this->errorBatch['rowError'][] = [
                                'Error message ' => $throwable->getMessage(),
                                'Error line'     => $throwable->getLine(),
                                'VariationId'    => $variation['id']
                            ];

                            $this->errorIterator++;

                            if ( $this->errorIterator == 100 ) {
                                $this->getLogger(__METHOD__)->error('PluginExportFormatTutorial2::logs.fillRowError', [
                                    'error list' => $this->errorBatch['rowError']
                                ]);

                                $this->errorIterator = 0;
                            }
                        }
                        $lines = $lines + 1;
                    }
                }
            } while ( $elasticSearch->hasNext() );

            if ( is_array($this->errorBatch) && count($this->errorBatch['rowError']) ) {
                $this->getLogger(__METHOD__)->error('PluginExportFormatTutorial2::logs.fillRowError', [
                    'errorList' => $this->errorBatch['rowError']
                ]);

                $this->errorIterator = 0;
            }
        }
    }


    /**
     * @param array    $variation
     * @param KeyValue $settings
     */
    private function buildRow( $variation, $settings )
    {

        $allowedVariation = [
            '28',
            '29',
        ];

        $variationAttributes = $this->attributeHelper->getVariationAttributes($variation, $settings);

        $data = [
            'title'        => $this->elasticExportHelper->getMutatedName($variation, $settings, 256) . '-' . $variationAttributes[self::CHARACTER_TYPE_SIZE],
            'availability' => $this->elasticExportHelper->getAvailability($variation, $settings, false),
            'gtin'         => $this->elasticExportHelper->getBarcodeByType($variation, $settings->get('barcode')),
            'stock'        => $this->elasticExportStockHelper->getStock($variation)
        ];

        if(in_array(substr($data['title'], 0, 2), $allowedVariation))
            $this->addCSVContent(array_values($data));
    }

    /**
     * @return array
     */
    private function getHeader()
    {

        return [
            'id',
            'gtin',
            'availability',
            'stock',
        ];
    }
}
