<?php

declare(strict_types=1);

namespace App\Application\Email\Template\Exception;

class MissingTemplateParameterException extends \Exception
{
    public function __construct(string $parameter, string $template)
    {
        parent::__construct(\sprintf('Missing parameter "%s" for email template "%s"', $parameter, $template));
    }
}
