<?php

namespace Ezplatform\SettingsBundle\DependencyInjection\Security;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Security\PolicyProvider\YamlPolicyProvider;

class PolicyProvider extends YamlPolicyProvider
{
    /**
     * Returns an array of files where the policy configuration lies.
     * Each file path MUST be absolute.
     *
     * @return array
     */
    public function getFiles()
    {
        return [
            __DIR__ . '/../../Resources/config/policies.yml',
        ];
    }
}
{

}