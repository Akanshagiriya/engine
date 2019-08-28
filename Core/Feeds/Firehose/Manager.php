<?php

namespace Minds\Core\Feeds\Firehose;

use Minds\Entities\Activity;
use Minds\Entities\User;
use Minds\Entities\Entity;
use Minds\Core\EntitiesBuilder;
use Minds\Core\Data\Call;
use Minds\Core\Entities\Actions\Save;
use Minds\Core\Di\Di;
use Minds\Core\Feeds\Top\Manager as TopFeedsManager;
use Minds\Core\Entities\PropogateProperties;

class Manager
{
    /** @var topFeedsManager */
    protected $topFeedsManager;
    /** @var ModerationCache */
    protected $moderationCache;
    /** @var Save */
    protected $save;
    /** @var PropogateProperties */
    protected $propogateProperties;

    public function __construct(
        TopFeedsManager $topFeedsManager = null,
        ModerationCache $moderationCache = null,
        Save $save = null,
        PropogateProperties $propogateProperties = null
    ) {
        $this->topFeedsManager = $topFeedsManager ?: Di::_()->get('Feeds\Top\Manager');
        $this->moderationCache = $moderationCache ?: new ModerationCache();
        $this->save = $save ?: new Save(); //Mockable, else instantiate a new one on save.
        $this->propogateProperties = $propogateProperties ?? Di::_()->get('PropogateProperties');
    }

    /**
     * Gets the top feed and filters out any entities that have been moderated
     * It caches entities for 1 hour in redis so moderators don't do double work.
     *
     * @param array $opts filtering options
     *                    Pass in a moderation_user to cache the returned entities for that user
     *
     * @return array entities that don't contain moderator_guids
     */
    public function getList(array $opts = [])
    {
        $opts = array_merge([
            'moderation_user' => null,
            'exclude_moderated' => true,
            'moderation_reservations' => null,
        ], $opts);

        if ($opts['moderation_user']) {
            $opts['moderation_reservations'] = $this->moderationCache->getKeysLockedByOtherUsers($opts['moderation_user']);
        }

        $response = $this->topFeedsManager->getList($opts);

        $response = $response->map(function ($entity) {
            return $entity->getEntity();
        });

        if ($opts['moderation_user']) {
            foreach ($response->toArray() as $entity) {
                $this->moderationCache->store($entity->guid, $opts['moderation_user']);
            }
        }

        return $response->filter(function ($entity) {
            return (!$entity->getModeratorGuid());
        });
    }

    /**
     * Marks an entity as moderated.
     *
     * @param $entity the entity to mark as moderated, typeless because images do not inherit entity
     * @param User $moderator the moderator
     * @param int  $time
     */
    public function save(
        $entity,
        User $moderator,
        int $time = null)
    {
        if (!$time) {
            $time = time();
        }

        $this->saveEntity($entity, $moderator, $time);
        $this->propogateProperties->from($entity);
    }

    private function saveEntity(
        $entity,
        User $moderator,
        int $time = null)
    {
        $entity->setModeratorGuid($moderator->getGUID());
        $entity->setTimeModerated($time);

        $this->save
            ->setEntity($entity)
            ->save();
    }
}
