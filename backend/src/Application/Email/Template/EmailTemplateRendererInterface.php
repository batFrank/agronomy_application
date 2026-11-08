<?php

declare(strict_types=1);

namespace App\Application\Email\Template;

interface EmailTemplateRendererInterface
{
    public function render(EmailTemplate $template): string;
}
