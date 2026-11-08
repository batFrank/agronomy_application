<?php

declare(strict_types=1);

namespace App\Application\Hydrator\User;

use App\Application\Hydrator\Hydrator;
use App\Application\Hydrator\HydratorCollection;
use App\Domain\User\User;

/**
 * @extends HydratorCollection<User>
 */
class UserHydratorCollection extends HydratorCollection
{
    /**
     * @param Hydrator<User>[] $hydrators
     */
    public function __construct(array $hydrators)
    {
        parent::__construct($hydrators);
    }
}
