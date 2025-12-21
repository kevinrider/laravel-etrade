<?php

namespace KevinRider\LaravelEtrade\Dtos;

use Illuminate\Support\Carbon;

class EtradeAccessTokenDTO extends BaseDTO
{
    public string $oauthToken;
    public string $oauthTokenSecret;
    public ?Carbon $inactiveAt = null;
}
