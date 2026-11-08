<?php

declare(strict_types=1);

namespace App\Domain\OTP\Provider;

use App\Domain\OTP\Purpose;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Resource\Resource;

interface OtpProviderInterface
{
    /**
     * Request a new otp code. Code is not known to the caller. The result is sent to the channel specified in the request.
     *
     * @throws InfrastructureException
     */
    public function generate(Request $request): void;

    /**
     * @throws InfrastructureException
     */
    public function verify(Verification $verification): VerificationResult;

    /**
     * @throws InfrastructureException
     */
    public function invalidate(Resource $resource, Purpose $purpose): void;
}
