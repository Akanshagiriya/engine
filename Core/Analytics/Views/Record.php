<?php

namespace Minds\Core\Analytics\Views;

use Minds\Core;
use Minds\Core\Di\Di;
use Minds\Entities;
use Minds\Helpers\Counters;

class Record
{
    /** @var Manager */
    protected $manager;
    /** @var string $lastError */
    protected $lastError = '';
    /** @var array $boostData */
    protected $boostData;
    /** @var string $identifier **/
    protected $identifier = '';
    /** @var array $clientMeta */
    protected $clientMeta;

    public function setClientMeta(array $clientMeta): self
    {
        $this->clientMeta = $clientMeta;
        return $this;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getBoostImpressionsData(): array
    {
        return $this->boostData;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function recordBoost(): bool
    {
        $expire = Di::_()->get('Boost\Network\Expire');
        $metrics = Di::_()->get('Boost\Network\Metrics');
        $manager = Di::_()->get('Boost\Network\Manager');

        $urn = "urn:boost:newsfeed:{$this->identifier}";

        $boost = $manager->get($urn, [ 'hydrate' => true ]);
        if (!$boost) {
            $this->lastError = 'Could not find boost';
            return false;
        }

        $count = $metrics->incrementViews($boost);

        if ($count > $boost->getImpressions()) {
            $expire->setBoost($boost);
            $expire->expire();
        }

        Counters::increment($boost->getEntity()->guid, "impression");
        Counters::increment($boost->getEntity()->owner_guid, "impression");

        try {
            $this->manager->record(
                (new View())
                    ->setEntityUrn($boost->getEntity()->getUrn())
                    ->setOwnerGuid((string) $boost->getEntity()->getOwnerGuid())
                    ->setClientMeta($this->clientMeta)
            );
        } catch (\Exception $e) {
            error_log($e);
        }

        $this->boostData = [
            'impressions' => $boost->getImpressions(),
            'impressions_met' => $count
        ];

        return true;
    }

    public function recordEntity(): bool
    {
        $entity = Entities\Factory::build($this->identifier);

        if (!$entity) {
            $this->lastError = 'Could not the entity';
            return false;
        }

        if ($entity->type === 'activity') {
            try {
                Core\Analytics\App::_()
                    ->setMetric('impression')
                    ->setKey($entity->guid)
                    ->increment();

                if ($entity->remind_object) {
                    Core\Analytics\App::_()
                        ->setMetric('impression')
                        ->setKey($entity->remind_object['guid'])
                        ->increment();

                    Core\Analytics\App::_()
                        ->setMetric('impression')
                        ->setKey($entity->remind_object['owner_guid'])
                        ->increment();
                }

                Core\Analytics\User::_()
                    ->setMetric('impression')
                    ->setKey($entity->owner_guid)
                    ->increment();
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        try {
            $this->manager->record(
                (new Core\Analytics\Views\View())
                    ->setEntityUrn($entity->getUrn())
                    ->setOwnerGuid((string) $entity->getOwnerGuid())
                    ->setClientMeta($this->clientMeta)
            );
        } catch (\Exception $e) {
            error_log($e);
        }

        Di::_()->get('Referrals\Cookie')
            ->setEntity($entity)
            ->create();
    }
}
