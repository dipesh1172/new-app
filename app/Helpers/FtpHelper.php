<?php

namespace App\Helpers;

use App\Models\ProviderIntegration;

/**
 * Class FtpHelper
 * @package App\Helpers
 */
class FtpHelper
{
    /**
     * @param string $brandId
     * @param int $serviceTypeId
     * @param int $integrationTypeId
     * @param int $envId
     * @return array|null
     */
    public static function getSettings(
        string $brandId,
        int $serviceTypeId,
        int $integrationTypeId,
        int $envId
    ): ?array
    {
        $pi = ProviderIntegration::select(
            'username',
            'password',
            'hostname'
        )
            ->where('brand_id', $brandId)
            ->where('service_type_id', $serviceTypeId)
            ->where('provider_integration_type_id', $integrationTypeId) // SFTP
            ->where('env_id', $envId)
            ->first();

        if(!$pi) {
            return null;
        }

        $settings = [
            'host' => $pi->hostname,
            'username' => $pi->username,
            'password' => $pi->password,
            'port' => 22,
            'root' => 'outgoing',
            'timeout' => 30
        ];

        return $settings;
    }
}
