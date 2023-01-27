<?php

declare(strict_types=1);

namespace App\Service;

use Illuminate\Http\UploadedFile;

class PhotoVerificationService
{
    private bool $isFaked = false;

    public function faking()
    {
        $this->isFaked = true;
    }

    public function verify(UploadedFile $file): bool
    {
        if ($this->isFaked) {
            return true;
        }

        return false;
    }
}
