<?php
/**
 * Manager
 * @author edgebal
 */

namespace Minds\Core\Pro;

use Exception;
use Minds\Core\Di\Di;
use Minds\Core\Entities\Actions\Save;
use Minds\Core\EntitiesBuilder;
use Minds\Core\Util\StringValidator;
use Minds\Entities\User;

class Manager
{
    /** @var Repository */
    protected $repository;

    /** @var Save */
    protected $saveAction;

    /** @var Delegates\InitializeSettingsDelegate */
    protected $initializeSettingsDelegate;

    /** @var Delegates\HydrateSettingsDelegate */
    protected $hydrateSettingsDelegate;

    /** @var User */
    protected $user;

    /** @var User */
    protected $actor;

    /** @var EntitiesBuilder */
    protected $entitiesBuilder;

    /**
     * Manager constructor.
     * @param Repository $repository
     * @param Save $saveAction
     * @param Delegates\InitializeSettingsDelegate $initializeSettingsDelegate
     * @param Delegates\HydrateSettingsDelegate $hydrateSettingsDelegate
     * @param EntitiesBuilder $entitiesBuilder
     */
    public function __construct(
        $repository = null,
        $saveAction = null,
        $initializeSettingsDelegate = null,
        $hydrateSettingsDelegate = null,
        $entitiesBuilder = null
    )
    {
        $this->repository = $repository ?: new Repository();
        $this->saveAction = $saveAction ?: new Save();
        $this->initializeSettingsDelegate = $initializeSettingsDelegate ?: new Delegates\InitializeSettingsDelegate();
        $this->hydrateSettingsDelegate = $hydrateSettingsDelegate ?: new Delegates\HydrateSettingsDelegate();
        $this->entitiesBuilder = $entitiesBuilder ?: Di::_()->get('EntitiesBuilder');
    }

    /**
     * @param User $user
     * @return Manager
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param User $actor
     * @return Manager
     */
    public function setActor(User $actor)
    {
        $this->actor = $actor;
        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isActive()
    {
        if (!$this->user) {
            throw new Exception('Invalid user');
        }

        return $this->user->isPro();
    }

    /**
     * @param $until
     * @return bool
     * @throws Exception
     */
    public function enable($until)
    {
        if (!$this->user) {
            throw new Exception('Invalid user');
        }

        $this->user
            ->setProExpires($until);

        $saved = $this->saveAction
            ->setEntity($this->user)
            ->save();

        $this->initializeSettingsDelegate
            ->onEnable($this->user);

        return (bool) $saved;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function disable()
    {
        if (!$this->user) {
            throw new Exception('Invalid user');
        }

        // TODO: Disable subscription instead, let Pro expire itself at the end of the sub

        $this->user
            ->setProExpires(0);

        $saved = $this->saveAction
            ->setEntity($this->user)
            ->save();

        return (bool) $saved;
    }

    /**
     * @return Settings|null
     * @throws Exception
     */
    public function get()
    {
        if (!$this->user) {
            throw new Exception('Invalid user');
        }

        $settings = $this->repository->getList([
            'user_guid' => $this->user->guid,
        ])->first();

        if (!$settings) {
            return null;
        }

        return $this->hydrate($settings);
    }

    /**
     * @param $settings
     * @return Settings
     */
    public function hydrate($settings)
    {
        return $this->hydrateSettingsDelegate
            ->onGet($this->user, $settings);
    }

    /**
     * @param array $values
     * @return bool
     * @throws Exception
     */
    public function set(array $values = [])
    {
        if (!$this->user) {
            throw new Exception('Invalid user');
        }

        $settings = $this->get() ?: new Settings();

        $settings
            ->setUserGuid($this->user->guid);

        if (isset($values['domain'])) {
            $domain = trim($values['domain']);

            if (!StringValidator::isDomain($domain)) {
                throw new \Exception('Invalid domain');
            }

            $settings
                ->setDomain($domain);
        }

        if (isset($values['title'])) {
            $title = trim($values['title']);

            if (strlen($title) > 60) {
                throw new \Exception('Title must be 60 characters or less');
            }

            $settings
                ->setTitle($title);
        }

        if (isset($values['headline'])) {
            $headline = trim($values['headline']);

            if (strlen($headline) > 80) {
                throw new \Exception('Headline must be 80 characters or less');
            }

            $settings
                ->setHeadline($headline);
        }

        if (isset($values['text_color'])) {
            if (!StringValidator::isHexColor($values['text_color'])) {
                throw new \Exception('Text color must be a valid hex color');
            }

            $settings
                ->setTextColor($values['text_color']);
        }

        if (isset($values['primary_color'])) {
            if (!StringValidator::isHexColor($values['primary_color'])) {
                throw new \Exception('Primary color must be a valid hex color');
            }

            $settings
                ->setPrimaryColor(StringValidator::isHexColor($values['primary_color']));
        }

        if (isset($values['plain_background_color'])) {
            if (!StringValidator::isHexColor($values['plain_background_color'])) {
                throw new \Exception('Plain background color must be a valid hex color');
            }
            $settings
                ->setPlainBackgroundColor(StringValidator::isHexColor($values['plain_background_color']));
        }

        if (isset($values['tile_ratio'])) {
            if (!in_array($values['tile_ratio'], Settings::TILE_RATIOS)) {
                throw new \Exception('Invalid tile ratio');
            }

            $settings
                ->setTileRatio($values['tile_ratio']);
        }

        if (isset($values['logo_guid'])) {
            $image = $this->entitiesBuilder->single($values['logo_guid']);

            // if the image doesn't exist or the guid doesn't correspond to an image
            if(!$image || ($image->type !== 'object' || $image->subtype !== 'image')) {
                throw new \Exception('logo_guid must be a valid image guid');
            }

            $settings
                ->setLogoGuid(trim($values['logo_guid']));
        }

        if (isset($values['footer_text'])) {
            $footer_text = trim($values['footer_text']);

            if (strlen($footer_text) > 80) {
                throw new \Exception('Footer text must be 80 characters or less');
            }

            $settings
                ->setFooterText($footer_text);
        }

        if (isset($values['footer_links']) && is_array($values['footer_links'])) {
            $footerLinks = array_map(function ($item) {
                $href = $item['href'];
                $title = ($item['title'] ?? null) ?: $item['href'];

                return compact('title', 'href');
            }, array_filter($values['footer_links'], function ($item) {
                return $item && $item['href'] && filter_var($item['href'], FILTER_VALIDATE_URL);
            }));

            $settings
                ->setFooterLinks(array_values($footerLinks));
        }

        if (isset($values['tag_list']) && is_array($values['tag_list'])) {
            $tagList = array_map(function ($item) {
                $tag = trim($item['tag'], "#\t\n\r");
                $label = ($item['label'] ?? null) ?: "#{$item['tag']}";

                return compact('label', 'tag');
            }, array_filter($values['tag_list'], function ($item) {
                return $item && $item['tag'];
            }));

            $settings
                ->setTagList(array_values($tagList));
        }

        if (isset($values['scheme'])) {
            if (!in_array($values['scheme'], Settings::COLOR_SCHEMES)) {
                throw new \Exception('Invalid tile ratio');
            }
            $settings
                ->setScheme($values['scheme']);
        }

        if (isset($values['custom_head']) && $this->actor->isAdmin()) {
            $settings
                ->setCustomHead($values['custom_head']);
        }

        return $this->repository->update($settings);
    }
}
