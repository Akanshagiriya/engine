<?php
/**
 * @author: eiennohi.
 */

namespace Minds\Core\Media\YouTubeImporter;

use Minds\Common\Repository\Response;
use Minds\Core\Config\Config;
use Minds\Core\Data\ElasticSearch\Client;
use Minds\Core\Data\ElasticSearch\Prepared\Count;
use Minds\Core\Data\ElasticSearch\Prepared\Search;
use Minds\Core\Di\Di;
use Minds\Core\EntitiesBuilder;
use Minds\Entities\Entity;

class Repository
{
    const ALLOWED_STATUSES = ['queued', 'transcoding', 'completed'];

    /** @var Client */
    protected $client;

    /** @var EntitiesBuilder */
    protected $entitiesBuilder;

    public function __construct(Client $client = null, EntitiesBuilder $entitiesBuilder = null)
    {
        $this->client = $client ?: Di::_()->get('Database\ElasticSearch');
        $this->entitiesBuilder = $entitiesBuilder ?: Di::_()->get('EntitiesBuilder');
    }

    /**
     * Returns saved videos
     * @param array $opts
     * @return Response
     * @throws \Exception
     */
    public function getVideos(array $opts): Response
    {
        $opts = array_merge([
            'limit' => 12,
            'offset' => 0,
            'user_guid' => null,
            'youtube_id' => null,
            'status' => null,
            'time_created' => [
                'lt' => null,
                'gt' => null,
            ],
        ], $opts);

        $filter = [];

        if (isset($opts['status'])) {
            if (!in_array($opts['status'], static::ALLOWED_STATUSES, true)) {
                throw new \Exception('Invalid status param');
            }

            $filter[] = [
                'term' => [
                    'transcoding_status' => $opts['status'],
                ],
            ];
        }

        $timeCreatedRange = [];

        if (isset($opts['time_created'])) {
            if (isset($opts['time_created']['lt'])) {
                $timeCreatedRange['lt'] = $opts['time_created']['lt'];
            }

            if (isset($opts['time_created']['gt'])) {
                $timeCreatedRange['gt'] = $opts['time_created']['gt'];
            }
        }

        if (isset($opts['youtube_id'])) {
            $filter[] = [
                'term' => [
                    'youtube_id' => $opts['youtube_id'],
                ],
            ];
        }

        if (count($timeCreatedRange) > 0) {
            $filter[]['range'] = [
                'time_created' => [
                    $timeCreatedRange,
                ],
            ];
        }

        $query = [
            'index' => 'minds_badger',
            'type' => 'object:video',
            'size' => $opts['limit'],
            'from' => $opts['offset'],
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => $filter,
                    ],
                ],
            ],
        ];

        $response = new Response();

        $prepared = new Search();
        $prepared->query($query);
        try {
            $result = $this->client->request($prepared);

            if (!isset($result) || !(isset($result['hits'])) || !isset($result['hits']['hits'])) {
                return $response;
            }

            $guids = [];
            foreach ($result['hits']['hits'] as $entry) {
                $guids[] = $entry['_source']['guid'];
            }

            $response = new Response($this->entitiesBuilder->get(['guid' => $guids]));
        } catch (\Exception $e) {
            error_log('[YouTubeImporter\Repository]' . $e->getMessage());
        }

        return $response;
    }

    public function checkOwnerEligibility(array $guids): array
    {
        $result = [];

        for ($i = count($guids) - 1; $i >= 0; $i--) {
            $guid = $guids[$i];

            /* check for all transcoded videos created in a 24 hour
             * period that correspond to a youtube video */
            $filter = [
                [
                    'range' => [
                        'time_created' => [
                            'lt' => time(),
                            'gte' => strtotime('-10 day'),
                        ],
                    ],
                ],
                [
                    'exists' => [
                        'field' => 'youtube_id',
                    ],
                ],
                [
                    'term' => [
                        'transcoding_status' => 'completed',
                    ],
                ],
                [
                    'term' => [
                        'owner_guid' => $guid,
                    ],
                ],
            ];

            $query = [
                'index' => 'minds_badger',
                'type' => 'object:video',
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => $filter,
                        ],
                    ],
                ],
            ];

            $prepared = new Count();
            $prepared->query($query);

            $response = $this->client->request($prepared);

            $count = $response['count'] ?? 0;
            $result[$guid] = $count;
        }

        return $result;
    }
}
