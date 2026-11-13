<?php

declare(strict_types=1);

namespace Tapomix\Castor\Tools;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\io;

define('TAPOMIX_NAMESPACE_TOOLS', 'tapomix-tools');

/**
 * Generate a random secure password
 *
 * @param int $length Desired length (minimum 12)
 *
 * @return string Generated password
 */
function generatePassword(int $length = 16): string
{
    $lowercase = \range('a', 'z');
    $uppercase = \range('A', 'Z');
    $numbers = \range('0', '9');
    // safe in a .env and in the user info of a URL: no $ (compose interpolation), % (encoding, %env(resolve:)%),
    // @ # ? (DATABASE_URL delimiters)
    $special = \str_split('!&*-_+=');

    $allCharacters = [...$lowercase, ...$uppercase, ...$numbers, ...$special];

    // ensure minimum length
    $length = \max(12, $length);

    // at least one character from each category, the rest from all of them
    $characters = [pick($lowercase), pick($uppercase), pick($numbers), pick($special)];
    for ($i = 4; $i < $length; ++$i) {
        $characters[] = pick($allCharacters);
    }

    // Fisher-Yates on random_int(): str_shuffle() is not cryptographically secure
    for ($i = \count($characters) - 1; $i > 0; --$i) {
        $j = \random_int(0, $i);
        [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
    }

    return \implode('', $characters);
}

/**
 * One element, unbiased: random_int(), not a byte modulo the size.
 *
 * @param list<string> $characters
 */
function pick(array $characters): string
{
    return $characters[\random_int(0, \count($characters) - 1)];
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_TOOLS, description: 'Generate a random password', aliases: ['password'])]
function password(
    #[AsOption(shortcut: 'l', description: 'Choose password length (min: 12)')]
    int $length = 16,
): void {
    $password = generatePassword($length);
    io()->text('Copy+Paste your new password : ' . $password);
}

/**
 * Generate a random hexadecimal token
 *
 * @param int $length Desired length (minimum 4)
 *
 * @return string Generated token
 */
function generateToken(int $length = 32): string
{
    return \bin2hex(\random_bytes(\max(4, $length)));
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_TOOLS, description: 'Generate a random token', aliases: ['token'])]
function token(
    #[AsOption(shortcut: 'l', description: 'Choose password length (min: 4)')]
    int $length = 32,
): void {
    $token = generateToken($length);
    io()->text('Copy+Paste your new token : ' . $token);
}
