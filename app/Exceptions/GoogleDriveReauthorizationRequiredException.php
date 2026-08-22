<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleDriveReauthorizationRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Akses Google Drive telah dicabut atau kedaluwarsa. Hubungkan ulang akun Google Drive.');
    }
}
