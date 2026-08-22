<?php

class Wirepusher {

    private static function custom_base64($string) {
        return strtr(base64_encode($string), array("+" => "-", "/" => ".", "=" => "_"));
    }

    private static function hash_password($password) {
        return substr(strtolower(sha1($password)), 0,32);
    }

    static function send($id, $title, $message, $type = 'video',$action, $image_url, $encryption_password = 'video') {

        $parameters = [
            'id'      => $id,
            'title'   => $title,
            'message' => $message,
			 'action' => $action,
			  'image_url' => $image_url,
        ];

        if (isset($encryption_password) && $encryption_password != '') {
            $parameters['type'] = $type;
        }

        if (isset($encryption_password) && $encryption_password != '') {
            $iv = openssl_random_pseudo_bytes(16, $secure);

            if (false === $secure || false === $iv) {
                throw new \RuntimeException('iv generation failed');
            }

            $iv_hex = strtolower(bin2hex($iv));
            $hashed_password = self::hash_password($encryption_password);
            $method = 'aes-128-cbc';

            $parameters['title'] = self::custom_base64(openssl_encrypt ($title, $method, hex2bin($hashed_password), OPENSSL_RAW_DATA, $iv));
            $parameters['message'] = self::custom_base64(openssl_encrypt ($message, $method, hex2bin($hashed_password), OPENSSL_RAW_DATA, $iv));
            $parameters['iv'] = $iv_hex;
        }

        $url = "https://wirepusher.com/send";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array($http_status, $response);
    }
}
