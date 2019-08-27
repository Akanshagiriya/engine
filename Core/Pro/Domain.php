<?php
/**
 * Domain
 * @author edgebal
 */

namespace Minds\Core\Pro;

use Minds\Core\Config;
use Minds\Core\Di\Di;
use Zend\Diactoros\ServerRequest;

class Domain
{
    /** @var Config */
    protected $config;

    /** @var Repository */
    protected $repository;

    /**
     * Domain constructor.
     * @param Config $config
     * @param Repository $repository
     */
    public function __construct(
        $config = null,
        $repository = null
    ) {
        $this->config = $config ?: Di::_()->get('Config');
        $this->repository = $repository ?: new Repository();
    }

    /**
     * @param string $domain
     * @return Settings|null
     */
    public function lookup(string $domain)
    {
        $rootDomains = $this->config->get('root_domains') ?: [];

        if (in_array(strtolower($domain), $rootDomains, true)) {
            return null;
        }

        $settings = $this->repository->getList([
            'domain' => $domain,
        ])->first();

        return $settings;
    }

    public function validateRequest(ServerRequest $request)
    {
        return true;
    }
}
