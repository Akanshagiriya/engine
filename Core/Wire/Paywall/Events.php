<?php

namespace Minds\Core\Wire\Paywall;

use Minds\Core;
use Minds\Core\Di\Di;
use Minds\Core\Session;
use Minds\Core\Features;
use Minds\Core\Events\Dispatcher;

class Events
{
    /** @var Features\Managers */
    private $featuresManager;

    /** @var SupportTier\Manager */
    private $supportTiersManager;

    public function __construct($featuresManager = null, $supportTiersManager = null)
    {
        $this->featuresManager = $featuresManager;
        $this->supportTiersManager = $supportTiersManager;
    }

    public function register()
    {
        /*
         * Removes important export fields if marked as paywall
         */
        Dispatcher::register('export:extender', 'all', function ($event) {
            if (!$this->featuresManager) { // Can not use DI in constructor due to init races
                $this->featuresManager = Di::_()->get('Features\Manager');
            }

            $params = $event->getParameters();
            $activity = $params['entity'];

            $export = $event->response() ?: [];
            $currentUser = Session::getLoggedInUserGuid();

            $dirty = false;

            if (!$activity instanceof PaywallEntityInterface) {
                return;
            }

            if ($activity->isPayWallUnlocked()) {
                $export['paywall'] = false;
                $export['paywall_unlocked'] = true;
                $event->setResponse($export);
                return;
            }

            if ($activity->isPaywall() && $activity->owner_guid != $currentUser) {
                $export['blurb'] = $this->extractTeaser($activity->blurb);
                $export['message'] = $this->extractTeaser($activity->message);

                if (!$this->featuresManager->has('paywall-2020')) {
                    $export['custom_type'] = null;
                    $export['custom_data'] = null;
                    $export['thumbnail_src'] = null;
                    $export['perma_url'] = null;
                    $export['title'] = null;
                }

                $dirty = true;
            }

            if (
                $activity->remind_object &&
                (int) $activity->remind_object['paywall'] &&
                $activity->remind_object['owner_guid'] != $currentUser
            ) {
                $export['remind_object'] = $activity->remind_object;
                $export['remind_object']['blurb'] = $this->extractTeaser($activity->remind_object['blurb']);
                $export['remind_object']['message'] = $this->extractTeaser($activity->remind_object['message']);

                if (!$this->featuresManager->has('paywall-2020')) {
                    $export['remind_object']['custom_type'] = null;
                    $export['remind_object']['custom_data'] = null;
                    $export['remind_object']['thumbnail_src'] = null;
                    $export['remind_object']['perma_url'] = null;
                    $export['remind_object']['title'] = null;
                }

                $dirty = true;
            }

            if ($dirty) {
                return $event->setResponse($export);
            }

            if (!$currentUser) {
                return;
            }
        });

        /*
         * Wire paywall hooks. Allows access if sent wire or is plus
         */
        Dispatcher::register('acl:read', 'object', function ($event) {
            $params = $event->getParameters();
            $entity = $params['entity'];
            $user = $params['user'];

            if (!$entity->isPayWall()) {
                return;
            }

            if (!$user) {
                return false;
            }

            //Plus hack

            if ($entity->owner_guid == '730071191229833224') {
                $plus = (new Core\Plus\Subscription())->setUser($user);

                if ($plus->isActive()) {
                    return $event->setResponse(true);
                }
            }

            try {
                $isAllowed = Di::_()->get('Wire\Thresholds')->isAllowed($user, $entity);
            } catch (\Exception $e) {
            }

            if ($isAllowed) {
                return $event->setResponse(true);
            }

            return $event->setResponse(false);
        });

        /*
         * Legcacy compatability for exclusive content
         */
        Dispatcher::register('export:extender', 'activity', function ($event) {
            $params = $event->getParameters();
            $activity = $params['entity'];
            if ($activity->type != 'activity') {
                return;
            }
            $export = $event->response() ?: [];
            $currentUser = Session::getLoggedInUserGuid();

            if ($activity->isPaywall() && !$activity->getWireThreshold()) {
                $export['wire_threshold'] = [
                  'type' => 'money',
                  'min' => $activity->getOwnerEntity()->getMerchant()['exclusive']['amount'],
                ];

                return $event->setResponse($export);
            }
        });

        /**
         * Pair the support tier with the output
         */
        Dispatcher::register('export:extender', 'all', function ($event) {
            if (!$this->supportTiersManager) { // Can not use DI in constructor due to init races
                $this->supportTiersManager = Di::_()->get('Wire\SupportTiers\Manager');
            }

            $params = $event->getParameters();
            $entity = $params['entity'];

            if (!$entity instanceof PaywallEntityInterface) {
                return; // Not paywallable
            }

            if (!$entity->isPayWall()) {
                return; // Not paywalled
            }

            $export = $event->response() ?: [];
            //$currentUser = Session::getLoggedInUserGuid();

            $wireThreshold = $entity->getWireThreshold();
            if (!$wireThreshold['support_tier']) {
                return; // This is a legacy paywalled post
            }

            $supportTier = $this->supportTiersManager->getByUrn($wireThreshold['support_tier']['urn']);

            if (!$supportTier) {
                return; // Not found?
            }

            // Array Merge so we keep the expires
            $wireThreshold['support_tier'] = array_merge($wireThreshold['support_tier'], $supportTier->export());

            $export['wire_threshold'] = $wireThreshold;

            if ($entity->isPayWallUnlocked()) {
                $export['paywall_unlocked'] = true;
            }

            return $event->setResponse($export);
        });
    }

    private function extractTeaser($fullText)
    {
        if (!isset($fullText)) {
            return null;
        }
        $teaserText = substr($fullText, 0, 200);

        return $teaserText;
    }
}
