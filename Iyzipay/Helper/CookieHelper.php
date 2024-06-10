<?php

namespace Iyzico\Iyzipay\Helper;

class CookieHelper
{
    public function ensureCookiesSameSite()
    {
        $cookieNamesToCheck = [
            'PHPSESSID',
            'adminhtml',
            'frontend',
            'mage-cache-sessid',
            'mage-cache-storage',
            'mage-cache-storage-section-invalidation',
            'mage-messages',
            'mage-translation-file-version',
            'mage-translation-storage'
        ];

        foreach ($_COOKIE as $cookieName => $value) {
            foreach ($cookieNamesToCheck as $checkCookieName) {
                if (stripos($cookieName, $checkCookieName) === 0) {
                    $this->applyCookieSettings($cookieName, $value, time() + 86400, $_SERVER['SERVER_NAME']);
                }
            }
        }
    }

    private function applyCookieSettings($name, $value, $expire, $domain)
    {
        if (PHP_VERSION_ID < 70300) {
            setcookie($name, $value, $expire, "/; samesite=None", $domain, true, true);
        } else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => "/",
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => true,
                'httponly' => true
            ]);
        }
    }
}
