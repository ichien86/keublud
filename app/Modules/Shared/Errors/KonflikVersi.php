<?php

declare(strict_types=1);

namespace App\Modules\Shared\Errors;

final class KonflikVersi extends KesalahanDomain
{
    public function __construct(string $objek)
    {
        parent::__construct('KONFLIK_VERSI', "{$objek} telah diubah pengguna lain. Muat ulang lalu ulangi.", statusHttp: 409);
    }
}
