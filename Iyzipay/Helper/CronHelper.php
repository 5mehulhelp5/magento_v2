<?php

namespace Iyzico\Iyzipay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CronHelper extends AbstractHelper
{
    const XML_PATH_COMMON_CRON_SETTINGS = 'payment/iyzipay/common_cron_settings';
    const XML_PATH_CUSTOM_CRON_SETTINGS = 'payment/iyzipay/custom_cron_settings';
    const XML_PATH_EFFECTIVE_CRON_SCHEDULE = 'crontab/default/jobs/iyzico_process_pending_orders/schedule/cron_expr';

    protected $configWriter;
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
    }

    public function getCronSchedule()
    {
        $commonSettings = $this->scopeConfig->getValue(self::XML_PATH_COMMON_CRON_SETTINGS, ScopeInterface::SCOPE_STORE);

        if ($commonSettings && $commonSettings !== 'custom') {
            $this->saveEffectiveCronSchedule($commonSettings);
            return $commonSettings;
        }

        $customCronSettings = $this->scopeConfig->getValue(self::XML_PATH_CUSTOM_CRON_SETTINGS, ScopeInterface::SCOPE_STORE);

        if ($customCronSettings) {
            $this->saveEffectiveCronSchedule($customCronSettings);
            return $customCronSettings;
        }

        return '0 0 * * *';
    }

    private function saveEffectiveCronSchedule($schedule)
    {
        $this->configWriter->save(self::XML_PATH_EFFECTIVE_CRON_SCHEDULE, $schedule, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        $this->configWriter->save(self::XML_PATH_EFFECTIVE_CRON_SCHEDULE, $schedule, ScopeInterface::SCOPE_STORE, 0);
    }
}
