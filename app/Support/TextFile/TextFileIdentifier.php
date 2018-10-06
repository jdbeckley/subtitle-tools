<?php

namespace App\Support\TextFile;

use Exception;

class TextFileIdentifier
{
    protected $textEncoding;

    public function __construct(TextEncoding $textEncoding = null)
    {
        $this->textEncoding = $textEncoding ?? new TextEncoding();
    }

    public function isTextFile($filePath)
    {
        if (! file_exists($filePath)) {
            throw new Exception('File does not exist: '.$filePath);
        }

        // Empty files are treated as text files
        if (filesize($filePath) === 0) {
            return true;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if (strpos($mimeType, 'text/') === 0 || $mimeType === 'application/xml') {
            return true;
        }

        // Files with even a single invalid character (such as NULL) return an
        // "application/octet-stream" mime. Manually check them to decide
        // if they are text files.
        if ($mimeType === 'application/octet-stream') {
            return $this->hasAcceptableAmountOfValidText($filePath);
        }

        return false;
    }

    protected function hasAcceptableAmountOfValidText($filePath)
    {
        $fromEncoding = $this->textEncoding->detectFromFile($filePath);

        $handle = fopen($filePath, 'rb');

        while (! feof($handle)) {
            $chunk = fread($handle, 4096);

            $controlCharCount = 0;

            if (strlen($chunk) === 0) {
                continue;
            }

            $chunk = $this->textEncoding->toUtf8($chunk, $fromEncoding);

            // significant speed improvement when only evaluating this once
            $mbStrLength = mb_strlen($chunk);

            for ($i = 0; $i < $mbStrLength; $i++) {
                if ($chunk[$i] !== "\r" && $chunk[$i] !== "\n" && $chunk[$i] !== "\t" && ctype_cntrl($chunk[$i])) {
                    $controlCharCount++;
                }
            }

            $validTextPercentage = round(100 - $controlCharCount / $mbStrLength * 100, 3);

            // If any chunk has less than 99% valid text, it is unacceptable
            if ($validTextPercentage < 99.0) {
                return false;
            }
        }

        return true;
    }
}
