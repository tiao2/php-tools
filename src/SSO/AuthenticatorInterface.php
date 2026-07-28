<?php

declare(strict_types=1);

namespace PhpTools\SSO;

interface AuthenticatorInterface
{
    public function authenticate(string $username, string $password): ?int;
}