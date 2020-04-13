<?php

namespace Spec\Minds\Core\Email;

use Minds\Core\Data\Cassandra\Client;
use Minds\Core\Data\Cassandra\Prepared\Custom;
use Minds\Core\Di\Di;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use Minds\Core\Email\Repository;
use Minds\Core\Email\CampaignLogs\Repository as CampaignLogsRepository;
use Minds\Entities\User;
use Minds\Core\Email\EmailSubscription;
use Minds\Core\Email\CampaignLogs\CampaignLog;
use Spec\Minds\Mocks\Cassandra\FutureRow;

class ManagerSpec extends ObjectBehavior
{
    private $repository;
    private $campaignLogsRepository;

    public function let(Repository $repository, CampaignLogsRepository $campaignLogsRepository)
    {
        $this->repository = $repository;
        $this->campaignLogsRepository = $campaignLogsRepository;
        $this->beConstructedWith($this->repository, $this->campaignLogsRepository);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Minds\Core\Email\Manager');
    }

    public function it_should_get_subscribers(EmailSubscription $emailSub1, EmailSubscription $emailSub2, Client $client)
    {
        $opts = [
            'campaign' => 'when',
            'topic' => 'boost_completed',
            'value' => true,
            'limit' => 2000,
        ];

        $subscriptions = [
            'data' => [
                $emailSub1,
                $emailSub2
            ],
            'token' => '120123iasjdojqwoeij'
        ];


        Di::_()->bind('Database\Cassandra\Cql', function ($di) use ($client) {
            return $client;
        });

        $futureRow = new FutureRow('something');

        $this->repository->getList(Argument::type('array'))->shouldBeCalled()->willReturn($subscriptions);
        $emailSub1->getUserGuid()->shouldBeCalled()->willReturn('1001');
        $emailSub2->getUserGuid()->shouldBeCalled()->willReturn('1002');
        //$client->request(Argument::type(Custom::class), true)->shouldBeCalled()->willReturn($futureRow);

        /* TODO: We can't mock Call because it's called directly via Entities::get() call */
        $this->shouldThrow()->during('getSubscribers', [$opts]);
    }

    public function it_should_unsubscribe_a_user_from_a_campaign()
    {
        $user = new User();
        $user->guid = '123';
        $user->username = 'user1';

        $this->repository->delete(Argument::type('Minds\Core\Email\EmailSubscription'))
            ->shouldBeCalled()
            ->willReturn(true);

        $this->unsubscribe($user, [ 'when' ], [ 'boost_received' ])
            ->shouldReturn(true);
    }

    public function it_should_unsubscribe_from_all_emails()
    {
        $user = new User();
        $user->guid = '123';

        $subscriptions = [
            (new EmailSubscription)
                ->setUserGuid($user->guid)
                ->setCampaign('when')
                ->setTopic('unread_notifications'),
            (new EmailSubscription)
                ->setUserGuid($user->guid)
                ->setCampaign('with')
                ->setTopic('top_posts'),
        ];

        $this->repository->getList([
            'campaigns' => [ 'when', 'with', 'global' ],
            'topics' => [
                'unread_notifications',
                'wire_received',
                'boost_completed',
                'top_posts',
                'channel_improvement_tips',
                'posts_missed_since_login',
                'new_channels',
                'minds_news',
                'minds_tips',
                'exclusive_promotions',
            ],
            'user_guid' => $user->guid,
        ])
            ->shouldBeCalled()
            ->willReturn($subscriptions);

        $this->repository->delete($subscriptions[0])
            ->shouldBeCalled();

        $this->repository->delete($subscriptions[1])
            ->shouldBeCalled();

        $this->unsubscribe($user)
            ->shouldReturn(true);
    }

    public function it_should_save_a_campaign_log()
    {
        $campaignLog = new CampaignLog();
        $this->campaignLogsRepository->add($campaignLog)->shouldBeCalled();
        $this->saveCampaignLog($campaignLog);
    }

    public function it_should_get_campaign_logs()
    {
        $user = new User();
        $user->guid = '123';
        $options = [
            'receiver_guid' => $user->guid
        ];
        $this->campaignLogsRepository->getList($options)->shouldBeCalled();
        $this->getCampaignLogs($user);
    }

    public function it_should_unset_all_campaigns()
    {
        $user = new User();
        $user->guid = '123';
        $subscriptions = [[
            (new EmailSubscription)
                ->setUserGuid($user->guid)
                ->setCampaign('when')
                ->setTopic('unread_notifications'),
            (new EmailSubscription)
                ->setUserGuid($user->guid)
                ->setCampaign('with')
                ->setTopic('top_posts'),
        ]];
    
        $this->repository->getList([
            'campaigns' => [ 'when', 'with', 'global' ],
            'topics' => [
                'unread_notifications',
                'wire_received',
                'boost_completed',
                'top_posts',
                'channel_improvement_tips',
                'posts_missed_since_login',
                'new_channels',
                'minds_news',
                'minds_tips',
                'exclusive_promotions',
            ],
            'user_guid' => $user->guid,
        ])
            ->shouldBeCalled()
            ->willReturn($subscriptions);

        $this->repository->add(Argument::that(function ($model) {
            return $model->getValue() === '0';
        }))
            ->shouldBeCalled()
            ->willReturn(true);

        $this->unsetAllCampaigns($user->guid);
    }
}
