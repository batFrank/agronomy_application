<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\RecoverUserPassword;
use App\Application\CommandHandler\RecoverUserPasswordCommandHandler;
use App\Application\Email\Template\EmailTemplateRendererInterface;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

#[AllowMockObjectsWithoutExpectations]
class RecoverUserPasswordCommandHandlerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private MailerInterface&MockObject $mailer;
    private ParameterBagInterface&MockObject $parameterBag;
    private EmailTemplateRendererInterface&MockObject $emailTemplateRenderer;
    private RecoverUserPasswordCommandHandler $handler;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->emailTemplateRenderer = $this->createMock(EmailTemplateRendererInterface::class);

        $this->handler = new RecoverUserPasswordCommandHandler(
            $this->userRepository,
            $this->mailer,
            $this->parameterBag,
            $this->emailTemplateRenderer,
        );
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws RandomException
     * @throws \DateMalformedStringException
     */
    public function testHandleRecoverPasswordSuccessfully(): void
    {
        $email = EmailMother::create('test@example.com');
        $user = UserMother::create(email: $email);
        $command = new RecoverUserPassword($email->value());

        $fromEmail = 'noreply@example.com';
        $resetUrl = 'https://example.com/reset-password?token={token}';
        $htmlContent = '<html>Reset your password</html>';

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(function (UserCriteria $criteria) use ($email) {
                return $criteria->getEmail()?->equals($email) ?? false;
            }))
            ->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $updatedUser) use ($user) {
                return $updatedUser->getId()->equals($user->getId())
                    && $updatedUser->getEmail()->equals($user->getEmail())
                    && null !== $updatedUser->getPasswordResetToken()
                    && 64 === \strlen($updatedUser->getPasswordResetToken())
                    && null !== $updatedUser->getPasswordResetTokenExpiration()
                    && $updatedUser->getPasswordResetTokenExpiration() > new \DateTimeImmutable();
            }));

        $this->parameterBag
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['app.email_from', $fromEmail],
                ['app.reset_password_url', $resetUrl],
            ]);

        $this->emailTemplateRenderer
            ->expects($this->once())
            ->method('render')
            ->willReturn($htmlContent);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (MimeEmail $email) use ($fromEmail) {
                return 1 === \count($email->getFrom())
                    && $email->getFrom()[0]->getAddress() === $fromEmail
                    && 1 === \count($email->getTo())
                    && 'test@example.com' === $email->getTo()[0]->getAddress()
                    && 'Recuperar contraseña' === $email->getSubject();
            }));

        $this->handler->handle($command);
    }

    /**
     * @throws InfrastructureException
     * @throws RandomException
     * @throws \DateMalformedStringException
     * @throws TransportExceptionInterface
     */
    public function testHandleDoesNotSendEmailWhenUserNotFound(): void
    {
        $email = EmailMother::create('nonexistent@example.com');
        $command = new RecoverUserPassword($email->value());

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willThrowException(new UserNotFoundException());

        $this->userRepository
            ->expects($this->never())
            ->method('put');

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Should not throw exception, just return silently for security reasons
        $this->handler->handle($command);
    }
}
