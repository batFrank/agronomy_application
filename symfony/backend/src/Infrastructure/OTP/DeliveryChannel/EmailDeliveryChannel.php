<?php

declare(strict_types=1);

namespace App\Infrastructure\OTP\DeliveryChannel;

use App\Application\Email\Template\EmailTemplate;
use App\Application\Email\Template\EmailTemplateRendererInterface;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelException;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelInterface;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\DeliveryChannel\Type;
use App\Domain\Shared\Email\Email;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;

final readonly class EmailDeliveryChannel implements DeliveryChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag,
        private EmailTemplateRendererInterface $emailTemplateRenderer,
    ) {
    }

    public function supports(Type $type): bool
    {
        return Type::Email === $type;
    }

    #[\Override]
    public function send(DeliveryChannelValue $value): void
    {
        if (null === $value->getValue()) {
            throw DeliveryChannelException::valueCannotBeNull();
        }

        $destination = $value->getDestination();
        if (!$destination instanceof Email) {
            throw DeliveryChannelException::unsupportedDestinationType(
                current: \is_object($destination) ? \get_class($destination) : \gettype($destination),
                expected: Email::class,
            );
        }

        try {
            $fromEmail = $this->parameterBag->get('app.email_from');
            $fromEmail = \is_string($fromEmail) ? $fromEmail : '';

            $htmlContent = $this->emailTemplateRenderer->render(
                EmailTemplate::named(
                    'otp/send_otp',
                    [
                        'otp' => $value->getValue(),
                    ]
                )
            );

            $email = new \Symfony\Component\Mime\Email()
                ->from($fromEmail)
                ->to($destination->value())
                ->subject('OTP')
                ->html($htmlContent);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            throw DeliveryChannelException::fromPrevious($e);
        }
    }
}
