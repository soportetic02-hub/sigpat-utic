<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cifrado simétrico AES-256-GCM con la clave APP_KEY del .env.
 *
 * Formato guardado: "v1:" + base64(iv[12] + tag[16] + texto cifrado). GCM autentica
 * el contenido: un valor alterado o cifrado con otra clave no se puede descifrar.
 */
final class Cifrado
{
    private const METODO = 'aes-256-gcm';
    private const PREFIJO = 'v1:';
    private const LARGO_IV = 12;
    private const LARGO_TAG = 16;

    public static function cifrar(string $texto): string
    {
        $iv = random_bytes(self::LARGO_IV);
        $tag = '';
        $cifrado = openssl_encrypt($texto, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $tag, '', self::LARGO_TAG);
        if ($cifrado === false) {
            throw new CifradoException('No se pudo cifrar el valor.');
        }

        return self::PREFIJO . base64_encode($iv . $tag . $cifrado);
    }

    public static function descifrar(string $valor): string
    {
        $binario = str_starts_with($valor, self::PREFIJO) ? base64_decode(substr($valor, strlen(self::PREFIJO)), true) : false;
        if ($binario === false || strlen($binario) < self::LARGO_IV + self::LARGO_TAG) {
            throw new CifradoException('El valor cifrado está dañado.');
        }

        $iv = substr($binario, 0, self::LARGO_IV);
        $tag = substr($binario, self::LARGO_IV, self::LARGO_TAG);
        $texto = openssl_decrypt(substr($binario, self::LARGO_IV + self::LARGO_TAG), self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($texto === false) {
            throw new CifradoException('No se pudo descifrar: la APP_KEY del .env no es la que se usó al guardar el valor, o el valor fue alterado.');
        }

        return $texto;
    }

    /** Genera una clave nueva en el formato que espera APP_KEY. */
    public static function generarClave(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private static function clave(): string
    {
        $config = (string) config('app.key', '');
        $clave = str_starts_with($config, 'base64:') ? base64_decode(substr($config, 7), true) : false;
        if ($clave === false || strlen($clave) !== 32) {
            throw new CifradoException('Falta APP_KEY en el .env o no es válida. Genérela con: php database/generar_app_key.php');
        }

        return $clave;
    }
}
