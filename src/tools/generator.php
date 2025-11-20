<?php

namespace Tapomix\Castor\Tools;

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
    $special = \str_split('!@#$%&*-_+=?');

    $allCharacters = \array_merge($lowercase, $uppercase, $numbers, $special);
    $allLength = \count($allCharacters);

    // ensure minimum length
    $length = \max(12, $length);

    // generate password ensuring at least one character from each category
    $password = '';
    $password .= $lowercase[\random_int(0, \count($lowercase) - 1)];
    $password .= $uppercase[\random_int(0, \count($uppercase) - 1)];
    $password .= $numbers[\random_int(0, \count($numbers) - 1)];
    $password .= $special[\random_int(0, \count($special) - 1)];

    // fill the rest randomly using random_bytes for more entropy
    for ($i = 4; $i < $length; ++$i) {
        $randomIndex = \ord(\random_bytes(1)) % $allLength;
        $password .= $allCharacters[$randomIndex];
    }

    return \str_shuffle($password);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_TOOLS, description: 'Generate a random password', aliases: ['password'])]
function password(int $length = 16): void
{
    $password = generatePassword($length);
    io()->text('Copy+Paste your new password : ' . $password);
}

/**
 * Generate a random hexadecimal token
 *
 * @param int $length Desired length (minimum 1)
 *
 * @return string Generated token
 */
function generateToken(int $length = 32): string
{
    return \bin2hex(\random_bytes(\max(1, $length)));
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_TOOLS, description: 'Generate a random token', aliases: ['token'])]
function token(int $length = 32): void
{
    $token = generateToken($length);
    io()->text('Copy+Paste your new token : ' . $token);
}
