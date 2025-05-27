<?php

namespace Modera\BackendSecurityBundle\Contributions;

use Modera\BackendSecurityBundle\ModeraBackendSecurityBundle;
use Modera\ExpanderBundle\Ext\AsContributorFor;
use Modera\ExpanderBundle\Ext\ContributorInterface;
use Modera\FoundationBundle\Translation\T;
use Modera\SecurityBundle\Model\Permission;

/**
 * @copyright 2014 Modera Foundation
 */
#[AsContributorFor('modera_security.permissions')]
class PermissionsProvider implements ContributorInterface
{
    /**
     * @var Permission[]
     */
    private ?array $items = null;

    public function getItems(): array
    {
        if (!$this->items) {
            $this->items = [
                new Permission(
                    T::trans('Access Security Manager'),
                    ModeraBackendSecurityBundle::ROLE_ACCESS_SECURITY_MANAGER,
                    'administration',
                    null,
                    [ModeraBackendSecurityBundle::ROLE_ACCESS_BACKEND_TOOLS_SECURITY_SECTION],
                ),
                new Permission(
                    T::trans('Manage User Preferences'),
                    ModeraBackendSecurityBundle::ROLE_MANAGE_USER_PREFERENCES,
                    'administration',
                    null,
                    [ModeraBackendSecurityBundle::ROLE_MANAGE_USER_PROFILE_INFORMATION],
                ),
                new Permission(
                    T::trans('Manage User Profiles'),
                    ModeraBackendSecurityBundle::ROLE_MANAGE_USER_PROFILES,
                    'administration',
                ),
                new Permission(
                    T::trans('Manage User Accounts'),
                    ModeraBackendSecurityBundle::ROLE_MANAGE_USER_ACCOUNTS,
                    'administration',
                ),
                new Permission(
                    T::trans('Manage Groups and Permissions'),
                    ModeraBackendSecurityBundle::ROLE_MANAGE_PERMISSIONS,
                    'administration',
                ),
            ];
        }

        return $this->items;
    }
}
