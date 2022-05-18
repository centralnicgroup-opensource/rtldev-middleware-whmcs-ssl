<?php

namespace CNIC\WHMCS\SSL;

use Exception;

interface IRegistrar
{
    /**
     * @return array<string, array<string, mixed>>
     * @throws Exception
     */
    public static function getProducts(): array;

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function getExchangeRates(): array;

    /**
     * @param string $certId
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function getCertStatus(string $certId): array;

    /**
     * @param string $certClass
     * @param array<string, string> $contact
     * @param string $approvalMethod
     * @param string $email
     * @param string $serverType
     * @param string $csr
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function createCertificate(string $certClass, array $contact, string $approvalMethod, string $email, string $serverType, string $csr): array;

    /**
     * @param string $certId
     * @return array<string, mixed>
     *     @throws Exception
     */
    public static function renewCertificate(string $certId): array;

    /**
     * @param string $certId
     * @param string $csr
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function reissueCertificate(string $certId, string $csr): array;

    /**
     * @param string $certId
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function revokeCertificate(string $certId): array;

    /**
     * Resend SSL activation e-mail
     * @param string $certId
     * @param string $email
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function resendEmail(string $certId, string $email): array;
}
