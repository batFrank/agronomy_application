<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Template;

use App\Application\Email\Template\EmailTemplate;
use App\Application\Email\Template\EmailTemplateRendererInterface;
use App\Infrastructure\Email\Template\Exception\EmailTemplateRenderingException;
use Twig\Environment;
use Twig\Error\Error as TwigError;

final readonly class TwigEmailTemplateRenderer implements EmailTemplateRendererInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function render(EmailTemplate $template): string
    {
        try {
            return $this->twig->render(
                $this->resolveTemplatePath($template),
                $template->parameters()
            );
        } catch (TwigError $e) {
            throw new EmailTemplateRenderingException(
                \sprintf(
                    'Error rendering email template "%s".',
                    $template->name()
                ),
                0,
                $e
            );
        }
    }

    private function resolveTemplatePath(EmailTemplate $template): string
    {
        return \sprintf('email/%s.html.twig', $template->name());
    }
}
