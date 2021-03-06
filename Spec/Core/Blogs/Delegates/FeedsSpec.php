<?php

namespace Spec\Minds\Core\Blogs\Delegates;

use Minds\Core\Blogs\Blog;
use Minds\Core\Feeds\FeedItem;
use Minds\Core\Feeds\Repository as FeedsRepository;
use Minds\Core\Queue\Interfaces\QueueClient;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class FeedsSpec extends ObjectBehavior
{
    /** @var FeedsRepository */
    protected $feedsRepository;

    /** @var QueueClient */
    protected $queue;

    public function let(
        FeedsRepository $feedsRepository,
        QueueClient $queue
    ) {
        $this->beConstructedWith($feedsRepository, $queue);

        $this->feedsRepository = $feedsRepository;
        $this->queue = $queue;
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Minds\Core\Blogs\Delegates\Feeds');
    }

    public function it_should_index(
        Blog $blog
    ) {
        $blog->getGuid()
            ->shouldBeCalled()
            ->willReturn(5000);

        $blog->getOwnerGuid()
            ->shouldBeCalled()
            ->willReturn(1000);

        $blog->getContainerGuid()
            ->shouldBeCalled()
            ->willReturn(1000);

        $this->feedsRepository->add(Argument::type(FeedItem::class))
            ->shouldBeCalledTimes(3)
            ->willReturn(null);

        $this
            ->index($blog)
            ->shouldNotThrow();
    }

    public function it_should_remove(
        Blog $blog
    ) {
        $blog->getGuid()
            ->shouldBeCalled()
            ->willReturn(5000);

        $blog->getOwnerGuid()
            ->shouldBeCalled()
            ->willReturn(1000);

        $blog->getContainerGuid()
            ->shouldBeCalled()
            ->willReturn(1000);

        $this->feedsRepository->delete(Argument::type(FeedItem::class))
            ->shouldBeCalledTimes(3)
            ->willReturn(null);

        $this
            ->remove($blog)
            ->shouldNotThrow();
    }

    public function it_should_get_feed_items(
        Blog $blog
    ) {
        $blog->getGuid()
            ->shouldBeCalled()
            ->willReturn(5000);

        $blog->getOwnerGuid()
            ->shouldBeCalled()
            ->willReturn(1000);

        $blog->getContainerGuid()
            ->shouldBeCalled()
            ->willReturn(1000);

        $this
            ->getFeedItems($blog)
            ->shouldBeAnArrayOf(3, FeedItem::class);
    }

    //

    public function getMatchers(): array
    {
        $matchers = [];

        $matchers['beAnArrayOf'] = function ($subject, $count, $class) {
            if (!is_array($subject) || ($count !== null && count($subject) !== $count)) {
                throw new FailureException("Subject should be an array of $count elements");
            }

            $validTypes = true;

            foreach ($subject as $element) {
                if (!($element instanceof $class)) {
                    $validTypes = false;
                    break;
                }
            }

            if (!$validTypes) {
                throw new FailureException("Subject should be an array of {$class}");
            }

            return true;
        };

        return $matchers;
    }
}
