<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\RecoverUserPassword;
use App\Application\Email\Template\EmailTemplate;
use App\Application\Email\Template\EmailTemplateRendererInterface;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

readonly class RecoverUserPasswordCommandHandler
{
    private const int TOKEN_EXPIRY_HOURS = 24;

    public function __construct(
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag,
        private EmailTemplateRendererInterface $emailTemplateRenderer,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws RandomException
     * @throws \DateMalformedStringException
     * @throws TransportExceptionInterface
     */
    public function handle(RecoverUserPassword $command): void
    {
        $email = new Email($command->getEmail());

        try {
            $user = $this->userRepository->findOneBy(
                UserCriteria::createEmpty()->filterByEmail($email)
            );
        } catch (UserNotFoundException) {
            // For security reasons, we don't reveal if the email exists or not
            // Just return without sending an email
            return;
        }

        $resetToken = bin2hex(random_bytes(32));
        $expirationDate = new \DateTimeImmutable('+' . self::TOKEN_EXPIRY_HOURS . ' hours');

        $updatedUser = new User(
            id: $user->getId(),
            email: $user->getEmail(),
            roles: $user->getRoles(),
            passwordHash: $user->getPasswordHash(),
            createdAt: $user->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            deletedAt: $user->getDeletedAt(),
            passwordResetToken: $resetToken,
            passwordResetTokenExpiration: $expirationDate,
        );

        $this->userRepository->put($updatedUser);

        $fromEmail = $this->parameterBag->get('app.email_from');
        $resetUrl = $this->parameterBag->get('app.reset_password_url');

        $fromEmail = \is_string($fromEmail) ? $fromEmail : '';
        $resetUrl = \is_string($resetUrl) ? str_replace('{token}', $resetToken, $resetUrl) : null;

        $htmlContent = $this->emailTemplateRenderer->render(
            EmailTemplate::named(
                'user/reset_pasword',
                [
                    'reset_url' => $resetUrl,
                ]
            )
        );

        $email = (new \Symfony\Component\Mime\Email())
            ->from($fromEmail)
            ->to($email->value())
            ->subject('Recuperar contraseña')
            ->html($htmlContent);

        $this->mailer->send($email);
    }
}
