<?php

declare(strict_types=1);

namespace App\Domain\OTP;

use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\Shared\Exception\InfrastructureException;

interface OtpRepository
{
    /**
     * @throws InfrastructureException
     */
    public function put(Otp $otp): void;

    /**
     * @throws OtpNotFoundException
     * @throws InfrastructureException
     */
    public function findOneBy(OtpCriteria $criteria): Otp;

    /**
     * @throws InfrastructureException
     */
    public function findBy(OtpCriteria $criteria): OtpCollection;

    /**
     * @throws InfrastructureException
     */
    public function delete(Otp $otp): void;
}
