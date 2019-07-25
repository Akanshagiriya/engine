<?php
/**
 * pro
 * @author edgebal
 */

namespace Minds\Controllers\api\v2;

use Exception;
use Minds\Core\Di\Di;
use Minds\Core\Pro\Manager;
use Minds\Core\Session;
use Minds\Interfaces;
use Minds\Api\Factory;

class pro implements Interfaces\Api
{
    /**
     * Equivalent to HTTP GET method
     * @param array $pages
     * @return mixed|null
     * @throws Exception
     */
    public function get($pages)
    {
        /** @var Manager $manager */
        $manager = Di::_()->get('Pro\Manager');
        $manager
            ->setUser(Session::getLoggedinUser());

        return Factory::response([
            'isActive' => $manager->isActive(),
        ]);
    }

    /**
     * Equivalent to HTTP POST method
     * @param array $pages
     * @return mixed
     */
    public function post($pages)
    {
        /** @var Manager $manager */
        $manager = Di::_()->get('Pro\Manager');
        $manager
            ->setUser(Session::getLoggedinUser());

        // TODO: Send and process payment data
        $success = $manager->enable(time() + (365 * 86400));

        if (!$success) {
            return Factory::response([
                'status' => 'error',
                'message' => 'Error activating Pro',
            ]);
        }

        return Factory::response([
            'isActive' => $manager->isActive(),
            'settings' => $manager->get(),
        ]);
    }

    /**
     * Equivalent to HTTP PUT method
     * @param array $pages
     * @return mixed|null
     */
    public function put($pages)
    {
        return Factory::response([]);
    }

    /**
     * Equivalent to HTTP DELETE method
     * @param array $pages
     * @return mixed
     */
    public function delete($pages)
    {
        /** @var Manager $manager */
        $manager = Di::_()->get('Pro\Manager');
        $manager
            ->setUser(Session::getLoggedinUser());

        $success = $manager->disable();

        if (!$success) {
            return Factory::response([
                'status' => 'error',
                'message' => 'Error disabling Pro',
            ]);
        }

        return Factory::response([]);
    }
}
