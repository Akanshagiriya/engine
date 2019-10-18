<?php

namespace Minds\Core\Permissions\Roles;

class ModeratedChannelSubscriberRole extends BaseRole
{
    public function __construct()
    {
        parent::__construct(Roles::ROLE_MODERATED_CHANNEL_SUBSCRIBER);
        $this->addPermission(Flags::FLAG_CREATE_POST);
        $this->addPermission(Flags::FLAG_VIEW);
        $this->addPermission(Flags::FLAG_VOTE);
        $this->addPermission(Flags::FLAG_CREATE_COMMENT);
        $this->addPermission(Flags::FLAG_REMIND);
        $this->addPermission(Flags::FLAG_WIRE);
        $this->addPermission(Flags::FLAG_MESSAGE);
        $this->addPermission(Flags::FLAG_INVITE);
        $this->addPermission(Flags::FLAG_INTERACT);
    }
}
