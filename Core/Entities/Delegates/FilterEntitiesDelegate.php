<?php
/**
 * ResolverDelegate.
 *
 * @author juanmsolaro
 */

namespace Minds\Core\Entities\Delegates;

use Minds\Core\Security\ACL;
use Minds\Entities\User;

class FilterEntitiesDelegate
{
    /** @var ACL */
    protected $acl;

    /** @var User */
    protected $user;

    protected $time;

    public function __construct($user, $time, $acl = null)
    {
        $this->acl = $acl ?: ACL::_();
        $this->user = $user;
        $this->time = $time;
    }

    /**
     * Filter entities by read rights and write rights for scheduled activities, images, videos or blogs
     * @param array $entities
     * @return array
     */
    public function filter($entities)
    {
        return array_values(array_filter($entities, function ($entity) {
            $filterByScheduled = false;
            if ($this->shouldFilterScheduled($entity->getType())) {
                $filterByScheduled = !ACL::_()->write($entity, $this->user) 
                    && !($entity->getTimeCreated() <= $this->time);
            }
            return $this->acl->read($entity, $this->user) && !$filterByScheduled;
        }));
    }

    private function shouldFilterScheduled($type)
    {
        return $type == 'activity'
            || $type == 'blog'
            || $type == 'video'
            || $type == 'image';
    }
}
