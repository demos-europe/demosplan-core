<?php

namespace demosplan\DemosPlanCoreBundle\Story;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\RoleFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use Symfony\Contracts\Translation\TranslatorTrait;
use Zenstruck\Foundry\Story;

final class RoleStory extends Story
{
    use TranslatorTrait;

    public function build(): void
    {
        RoleFactory::createSequence(
            function() {
                foreach (Role::ROLE_CODE_NAME_MAP as $key => $value) {
                    yield [
                            'code' => $key,
                            'name' => $this->trans($value),
                            'groupCode' => Role::GLAUTH,
                            'groupName' => 'Kommune',
                        ];
                }
            }
        );
    }

    private function enrichMapping()
    {
        $roleMap = collect(Role::ROLE_CODE_NAME_MAP)->mapToGroups();

    }
}
