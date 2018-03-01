<?php

namespace LaravelExtendedErrors;

class Utils {

    static public function getMoreInformationAboutRequest(): array {
        $ret = [
            '$_GET' => static::cleanPasswordsInArray((array)$_GET),
            '$_POST' => static::cleanPasswordsInArray((array)$_POST),
            '$_FILES' => static::cleanPasswordsInArray((array)$_FILES),
            '$_COOKIE' => static::cleanPasswordsInArray(
                (array)(class_exists('\Cookie') ? \Cookie::get() : (!empty($_COOKIE) ? $_COOKIE : []))
            ),
            '$_SERVER' => array_intersect_key($_SERVER, array_flip([
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
            ]))
        ];
        if (!empty($ret['$_SERVER']['QUERY_STRING'])) {
            $ret['$_SERVER']['QUERY_STRING'] = static::cleanPasswordsInUrlQuery($ret['$_SERVER']['QUERY_STRING']);
        }
        return $ret;
    }

    static public function cleanPasswordsInArray(array $data): array {
        foreach ($data as $key => &$value) {
            if (preg_match('(pass(word)?)', $key)) {
                $value = '*****';
            }
        }
        return $data;
    }

    static public function cleanPasswordsInUrlQuery($queryString) {
        return preg_replace('%(pass(?:word)?[^=]*?=)[^&^"]*(&|$|")%im', '$1*****$2', $queryString);
    }
}