<?php

namespace Minds\Controllers\api\v1\wire;

use Minds\Api\Factory;
use Minds\Core\Di\Di;
use Minds\Core\Session;
use Minds\Entities;
use Minds\Interfaces;
use Minds\Core\Security\SignedUri;

class threshold implements Interfaces\Api
{
    /**
     * Checks if the amount of wires the logged user has sent to the entity owner passes the threshold, in which case
     * it unlocks/removes the paywall from the entity
     */
    public function get($pages)
    {
        $response = [];
        if (!isset($pages[0])) {
            return Factory::response($response);
        }

        $paywallManager = Di::_()->get('Wire\Paywall\Manager');

        // $entity = new Entities\Activity($pages[0]);
        $user = Session::getLoggedInUser();
        $entity = Entities\Factory::build($pages[0]);

        if (!$entity) {
            return Factory::response(['status' => 'error', 'message' => 'Entity couldn\'t be found']);
        }

        // if the user wires amounts to the threshold or more
        try {
            $entity = $paywallManager
                ->setUser($user)
                ->unlock($entity, $user);

            $response['entity'] = $entity->export();
            $response['entity']['paywall_unlocked'] = true;
        } catch (\Exception $e) {
            return Factory::response([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errorId' => str_replace('\\', '::', get_class($e)),
            ]);
        }

        return Factory::response($response);
    }

    public function post($pages)
    {
        return Factory::response([]);
    }

    public function put($pages)
    {
        return Factory::response([]);
    }

    public function delete($pages)
    {
        return Factory::response([]);
    }
}
