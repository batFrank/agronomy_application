<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\OTP\DeliveryChannel;

use App\Application\Email\Template\EmailTemplate;
use App\Application\Email\Template\EmailTemplateRendererInterface;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelException;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\DeliveryChannel\Type;
use App\Domain\Shared\Email\Email;
use App\Infrastructure\OTP\DeliveryChannel\EmailDeliveryChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;

#[CoversClass(EmailDeliveryChannel::class)]
class EmailDeliveryChannelTest extends TestCase
{
    #[DataProvider('provideSupportsCases')]
    public function testSupports(Type $type, bool $expected): void
    {
        $this->assertEquals($expected, $this->getChannel()->supports($type));
    }

    public static function provideSupportsCases(): \Generator
    {
        foreach (Type::cases() as $type) {
            yield $type->name => [
                'type' => $type,
                'expected' => Type::Email === $type,
            ];
        }
    }

    /**
     * @throws DeliveryChannelException
     */
    public function testSendWhenValueIsNull(): void
    {
        $channel = $this->getChannel();
        $this->expectExceptionObject(DeliveryChannelException::valueCannotBeNull());
        $channel->send(new DeliveryChannelValue(
            destination: null,
            type: Type::Email,
            value: null
        ));
    }

    /**
     * @throws DeliveryChannelException
     */
    #[DataProvider('provideWhenDestinationIsNotAValidEmailCases')]
    public function testWhenDestinationIsNotAValidEmail(mixed $destination): void
    {
        $channel = $this->getChannel();
        $this->expectExceptionObject(DeliveryChannelException::unsupportedDestinationType(
            current: \is_object($destination) ? \get_class($destination) : \gettype($destination),
            expected: Email::class,
        ));
        $channel->send(new DeliveryChannelValue(
            destination: $destination,
            type: Type::Email,
            value: '123456'
        ));
    }

    public static function provideWhenDestinationIsNotAValidEmailCases(): \Generator
    {
        yield 'string' => [
            'destination' => 'invalid-email',
        ];

        yield 'integer' => [
            'destination' => 123456,
        ];

        yield 'array' => [
            'destination' => [],
        ];

        yield 'object' => [
            'destination' => new \stdClass(),
        ];
    }

    /**
     * @throws DeliveryChannelException
     */
    public function testSendWhenAllConditionsAreMet(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $emailTemplateRenderer = $this->createMock(EmailTemplateRendererInterface::class);
        $channel = $this->getChannel(
            mailer: $mailer,
            parameterBag: $parameterBag,
            emailTemplateRenderer: $emailTemplateRenderer,
        );

        $parameterBag->expects($this->once())
            ->method('get')
            ->with('app.email_from')
            ->willReturn('source@example.com');

        $emailTemplateRenderer->expects($this->once())
            ->method('render')
            ->with(
                EmailTemplate::named(
                    'otp/send_otp',
                    [
                        'otp' => '123456',
                    ]
                )
            )->willReturn('<p>example 123456</p>');

        $mailer->expects($this->once())
            ->method('send')
            ->with(
                new \Symfony\Component\Mime\Email()
                    ->from('source@example.com')
                    ->to('destination@example.com')
                    ->subject('OTP')
                    ->html('<p>example 123456</p>')
            );

        $channel->send(new DeliveryChannelValue(
            destination: new Email('destination@example.com'),
            type: Type::Email,
            value: '123456'
        ));
    }

    private function getChannel(
        ?MailerInterface $mailer = null,
        ?ParameterBagInterface $parameterBag = null,
        ?EmailTemplateRendererInterface $emailTemplateRenderer = null,
    ): EmailDeliveryChannel {
        if (null === $mailer) {
            $mailer = $this->createMock(MailerInterface::class);
            $mailer->expects($this->never())
                ->method($this->anything());
        }

        if (null === $parameterBag) {
            $parameterBag = $this->createMock(ParameterBagInterface::class);
            $parameterBag->expects($this->never())
                ->method($this->anything());
        }

        if (null === $emailTemplateRenderer) {
            $emailTemplateRenderer = $this->createMock(EmailTemplateRendererInterface::class);
            $emailTemplateRenderer->expects($this->never())
                ->method($this->anything());
        }

        return new EmailDeliveryChannel(
            mailer: $mailer,
            parameterBag: $parameterBag,
            emailTemplateRenderer: $emailTemplateRenderer,
        );
    }
}
