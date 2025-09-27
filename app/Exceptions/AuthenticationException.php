<?php
declare(strict_types=1);

namespace App\Exceptions;

use Nette\Security\AuthenticationException as NetteAuthenticationException;

/**
 * Vlastní autentikační výjimka aplikace.
 * Zachovává kompatibilitu s Nette Security, lze chytat buď naši nebo Nette výjimku.
 */
class AuthenticationException extends NetteAuthenticationException
{
    // Žádná další logika zatím není potřeba – dědíme vše z NetteAuthenticationException
}
