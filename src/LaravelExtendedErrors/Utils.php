<?php

declare(strict_types=1);

namespace LaravelExtendedErrors;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;

class Utils
{
    public static function getMoreInformationAboutRequest(): array
    {
        $ret = [
            '$_GET' => static::cleanPasswordsInArray($_GET),
            '$_POST' => static::cleanPasswordsInArray($_POST),
            '$_FILES' => static::cleanPasswordsInArray($_FILES),
            '$_COOKIE' => static::cleanPasswordsInArray(Cookie::get()),
            '$_SERVER' => array_intersect_key(
                $_SERVER, array_flip([
                    'HTTP_ACCEPT_LANGUAGE',
                    'HTTP_ACCEPT_ENCODING',
                    'HTTP_REFERER',
                    'HTTP_USER_AGENT',
                    'HTTP_ACCEPT',
                    'HTTP_CONNECTION',
                    'HTTP_HOST',
                    'REMOTE_PORT',
                    'REMOTE_ADDR',
                    'REQUEST_URI',
                    'REQUEST_METHOD',
                    'QUERY_STRING',
                    'DOCUMENT_URI',
                    'REQUEST_TIME_FLOAT',
                    'REQUEST_TIME',
                    'argv',
                    'argc',
                ])
            ),
        ];
        if (empty($_POST) && isset($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            // json-encoded or PUT/DELETE
            $ret['$_POST'] = static::cleanPasswordsInArray(Request::input());
        }
        if (!empty($ret['$_SERVER']['QUERY_STRING'])) {
            $ret['$_SERVER']['QUERY_STRING'] = static::cleanPasswordsInUrlQuery($ret['$_SERVER']['QUERY_STRING']);
        }
        return $ret;
    }

    public static function getUserInfo(Authenticatable $user): array
    {
        return [
            'class' => get_class($user),
            $user->getAuthIdentifierName() => $user->getAuthIdentifier(),
        ];
    }

    public static function cleanPasswordsInArray(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (preg_match('(pass(word)?)', $key)) {
                $value = '*****';
            }
        }
        return $data;
    }

    public static function cleanPasswordsInUrlQuery(string $queryString): string
    {
        return preg_replace('%(pass(?:word)?[^=]*?=)[^&^"]*(&|$|")%im', '$1*****$2', $queryString);
    }
}