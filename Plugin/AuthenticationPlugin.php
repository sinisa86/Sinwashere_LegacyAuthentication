<?php
/**
 * Sinwashere_LegacyAuthentication
 *
 * @category   Sinwashere
 * @package    Sinwashere_LegacyAuthentication
 * @copyright  Copyright (c) 2017 Sinisa Nedeljkovic (contact@sinwashere.com).
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace Sinwashere\LegacyAuthentication\Plugin;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Encryption\Helper\Security;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Magento\Framework\Exception\State\UserLockedException;

class AuthenticationPlugin
{
    /**
     * MD5 Hash length
     */
    const MD5_HASH_LENGTH = 32;

    /**
     * Enable/disable MD5 authentication
     */
    const XML_ENABLE_MD5_AUTH = 'legacyauth/general/enable_md5_auth';

    /**
     * MD5 Salt
     */
    const XML_MD5_SALT = 'legacyauth/general/md5_salt';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CustomerRegistry
     */
    protected $customerRegistry;

    /**
     * @var Encryptor
     */
    protected $encryptor;


    /**
     * @param CustomerRegistry $customerRegistry
     * @param Encryptor $encryptor
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        CustomerRegistry $customerRegistry,
        Encryptor $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerRegistry = $customerRegistry;
        $this->encryptor = $encryptor;
    }

    /**
     * @param \Magento\Customer\Model\Authentication $subject
     * @param callable $proceed
     * @param int $customerId
     * @param string $password
     * @return bool
     */
    public function aroundAuthenticate(\Magento\Customer\Model\Authentication $subject, callable $proceed, $customerId, $password)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $enableMD5Auth = $this->scopeConfig->getValue(self::XML_ENABLE_MD5_AUTH, $storeScope);

        /**
         * Check if MD5 Authentication method is enabled
         */
        if ($enableMD5Auth) {
            $customerSecure = $this->customerRegistry->retrieveSecureData($customerId);
            $hash = $customerSecure->getPasswordHash();

            if (strlen($hash) == self::MD5_HASH_LENGTH) {
                $passwordSalt = $this->scopeConfig->getValue(self::XML_MD5_SALT, $storeScope);
                $password = $this->encryptor->hash($passwordSalt . $password, \Magento\Framework\Encryption\Encryptor::HASH_VERSION_MD5);

                $comparePasswordHash = Security::compareStrings($password, $hash);

                if (!$comparePasswordHash) {
                    $subject->processAuthenticationFailure($customerId);
                    if ($subject->isLocked($customerId)) {
                        throw new UserLockedException(__('The account is locked.'));
                    }
                    throw new InvalidEmailOrPasswordException(__('Invalid login or password.'));
                }
                return true;
            }
        }

        /**
         * Fallback on default authentication method
         */
        $returnValue = $proceed($customerId, $password);

        return $returnValue;
    }
}