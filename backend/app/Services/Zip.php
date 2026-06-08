<?php

namespace App\Services;

use RuntimeException;

/*
| Zip
| --------------------------------------------------------------------------
| ZIP minimo en PHP puro (sin la extension `zip`/ZipArchive, que NO esta
| disponible en produccion). Usa DEFLATE via gzdeflate/gzinflate (ext-zlib,
| que si esta) y crc32. Suficiente para empaquetar/leer archivos .xlsx.
*/
class Zip
{
    /** Construye los bytes de un ZIP a partir de [nombre => contenido]. */
    public static function create(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($files as $name => $content) {
            $content = (string) $content;
            $crc = crc32($content);
            $uncompLen = strlen($content);
            $comp = gzdeflate($content, 6);
            if ($comp === false) {
                throw new RuntimeException('Fallo gzdeflate al crear el ZIP.');
            }
            $compLen = strlen($comp);
            $nameLen = strlen($name);

            // 0x0021 = fecha DOS valida (1980-01-01); hora 0.
            $lfh = pack('V', 0x04034b50)
                 . pack('v', 20) . pack('v', 0) . pack('v', 8)
                 . pack('v', 0) . pack('v', 0x0021)
                 . pack('V', $crc) . pack('V', $compLen) . pack('V', $uncompLen)
                 . pack('v', $nameLen) . pack('v', 0)
                 . $name;

            $local .= $lfh . $comp;

            $central .= pack('V', 0x02014b50)
                 . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 8)
                 . pack('v', 0) . pack('v', 0x0021)
                 . pack('V', $crc) . pack('V', $compLen) . pack('V', $uncompLen)
                 . pack('v', $nameLen) . pack('v', 0) . pack('v', 0)
                 . pack('v', 0) . pack('v', 0) . pack('V', 0)
                 . pack('V', $offset)
                 . $name;

            $offset += strlen($lfh) + $compLen;
            $count++;
        }

        $eocd = pack('V', 0x06054b50)
              . pack('v', 0) . pack('v', 0)
              . pack('v', $count) . pack('v', $count)
              . pack('V', strlen($central))
              . pack('V', strlen($local))
              . pack('v', 0);

        return $local . $central . $eocd;
    }

    /** Lee un ZIP (bytes) y devuelve [nombre => contenido]. Soporta stored y deflate. */
    public static function read(string $bytes): array
    {
        $eocd = strrpos($bytes, "\x50\x4b\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Archivo invalido: no es un ZIP/xlsx.');
        }

        $total  = unpack('v', substr($bytes, $eocd + 10, 2))[1];
        $cdOff  = unpack('V', substr($bytes, $eocd + 16, 4))[1];

        $files = [];
        $p = $cdOff;
        for ($i = 0; $i < $total; $i++) {
            if (substr($bytes, $p, 4) !== "\x50\x4b\x01\x02") {
                break;
            }
            $method      = unpack('v', substr($bytes, $p + 10, 2))[1];
            $compSize    = unpack('V', substr($bytes, $p + 20, 4))[1];
            $nameLen     = unpack('v', substr($bytes, $p + 28, 2))[1];
            $extraLen    = unpack('v', substr($bytes, $p + 30, 2))[1];
            $commentLen  = unpack('v', substr($bytes, $p + 32, 2))[1];
            $localOff    = unpack('V', substr($bytes, $p + 42, 4))[1];
            $name        = substr($bytes, $p + 46, $nameLen);

            // Header local: el dato real empieza tras su propio nombre + extra.
            $lNameLen  = unpack('v', substr($bytes, $localOff + 26, 2))[1];
            $lExtraLen = unpack('v', substr($bytes, $localOff + 28, 2))[1];
            $dataStart = $localOff + 30 + $lNameLen + $lExtraLen;
            $comp      = substr($bytes, $dataStart, $compSize);

            if ($method === 8) {
                $content = gzinflate($comp);
            } elseif ($method === 0) {
                $content = $comp;
            } else {
                $content = false;
            }

            if ($content !== false) {
                $files[$name] = $content;
            }

            $p += 46 + $nameLen + $extraLen + $commentLen;
        }

        return $files;
    }
}
