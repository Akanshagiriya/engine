<?php

namespace Minds\Core\Email\Batches;

use Minds\Core\Di\Di;
use Minds\Core\Security\ACL;
use Minds\Core\Email\Campaigns;
use Minds\Core\Email\EmailSubscribersIterator;
use Minds\Traits\MagicAttributes;

class WithBlogs implements EmailBatchInterface
{
    use MagicAttributes;

    /** @var Manager */
    protected $manager;
    /** @var Repository */
    protected $repository;
    /** @var EntitiesBuilder */
    protected $builder;

    /** @var string $offset */
    protected $offset;

    /** @var string $offset */
    protected $templatePath;

    /** @var string $subject */
    protected $subject = "Top blogs from August";

    public function __construct($manager = null, $trendingRepository = null, $builder = null)
    {
        $this->manager = $manager ?: Di::_()->get('Email\Manager');
        $this->repository = $trendingRepository ?: Di::_()->get('Trending\Repository');
        $this->builder = $builder ?: Di::_()->get('EntitiesBuilder');
    }

    public function setDryRun($dry)
    {
        return $this;
    }

    /**
     * @param string $offset
     *
     * @return Catchup
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @param string $templatePath
     *
     * @return Catchup
     */
    public function setTemplateKey($template)
    {
        $this->templatePath = $template;

        return $this;
    }

    /**
     * @param string $subject
     *
     * @return Catchup
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        if (!$this->templatePath || $this->templatePath == '') {
            //    throw new \Exception('You must set the templatePath');
        }
        if (!$this->subject || $this->subject == '') {
            //    throw new \Exception('You must set the subject');
        }

        $iterator = new EmailSubscribersIterator();
        $iterator->setCampaign('with')
            ->setTopic('posts_missed_since_login')
            ->setValue(true)
            ->setOffset($this->offset);

        $blogs = $this->getTrendingBlogs();

        $i = 0;
        foreach ($iterator as $user) {
            // $user = new \Minds\Entities\User('mark');
            ++$i;
            echo "\n[$i]: $user->guid ($iterator->offset)";

            //if ($user->getTimeCreated() > strtotime('-28 days ago')) {
            //    echo "[done]";
            //    return true;
            //}

            $campaign = new Campaigns\WithBlogs();

            $campaign
                ->setUser($user)
                ->setTemplateKey($this->templatePath)
                ->setSubject($this->subject)
                ->setBlogs($blogs)
                ->send();

            echo ' sent';
            // exit;
        }
    }

    private function getTrendingBlogs()
    {
        ACL::$ignore = true;
        /*$result = $this->repository->getList([
            'type' => 'blogs',
            'limit' => 10
        ]);

        if (!$result || !$result['guids'] || count($result['guids']) === 0) {
            return [];
        }

        ksort($result['guids']);
        $options['guids'] = $result['guids'];*/

        $options['guids'] = [
            '1006629205218992128',
            '1005557700564246528',
            '1010584762553004032',
            '1006684770592301056',
            '977770227199016960',
            '1002988949318565888',
            '996468067362422784',
            '1001223653028548608',
        ];

        $blogs = $this->builder->get(array_merge([
            'subtype' => 'blog',
        ], $options));

        return $blogs;
    }
}
